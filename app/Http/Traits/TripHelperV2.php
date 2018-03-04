<?php

namespace App\Http\Traits;

use App\Models\AttractionCategory;
use App\Models\Attraction;
use App\Models\City;
use Carbon\Carbon;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


trait TripHelperV2 {
    use FourSquareHelper;

    private $preferences;
    private $preferenceIdx;
    private $cityId;
    private $location;
    private $visitDate;
    private $visitLength;
    protected $lodging;
    protected $meal;
    protected $currentDay;
    protected $currentTime;
    protected $lastMealTime;
    protected $timetable;
    protected $prevLocation;

    public function setPreference($preferences)
    {
        $this->preferenceIdx = 0;
        $this->perferences = $preferences->shuffle();
    }

    public function init($options)
    {
        $this->currentDay = 1;
        $this->preferenceIdx = 0;
        $this->visitedPlaces = [];
        $this->timetable = [];
        $this->cityId = $options['cityId'];
        $this->location = $options['location'];
        $this->visitDate = $options['visitDate'];
        $this->visitLength = $options['visitLength'];
        $this->currentTime = Carbon::parse($options['visitDate'])->setTime(11, 0, 0);
        $this->lastMealTime = $this->currentTime->copy()->setTime(8, 30, 0);
        $this->setPreference($options['preferences']);
        $this->resetMeal();
        $visitDate = Carbon::parse($options['visitDate']);
        for ($i = 0; $i < $options['visitLength']; $i++) {
            $this->timetable[$visitDate->format('Y-m-d')] = [];
            $visitDate->addDay();
        }
        $this->initLodging();
    }
    
    public function generateTimetable()
    {
        $currentTime = $this->currentTime;
        while (!$this->finishedDinner()) {
            $tags = $this->getCorrespondingTags()->getIterator();
            if ($this->isMealTime()) {
                $this->setMealTime($this->currentTime);
                $tags = '4d4b7105d754a06374d81259';
            }
            $place = $this->getPlaces($tags, $this->location);
            $this->location = [$place->latitude, $place->longitude];
            $this->visitedPlaces[] = $place->place_id;
            $this->appendTimeslot($place, is_string($tags) ? 3600 : $this->getStayDuration($place));
        }
        $this->backToLodging();
        $this->updateWakeupTime();
        if ($this->currentDay < $this->visitLength) {
            $this->nextDay();
            $this->currentDay++;
        }
        return $this->timetable;
    }

    public function appendTimeslot($place, $stayDuration = 0)
    {
        $travel = [];
        if (isset($this->prevLocation)) {
            $travel = $this->getTravelInfo($this->prevLocation, [$place->latitude, $place->longitude]);
            $this->currentTime->addSeconds($travel['travel_duration']);
        }
        $currentDate = $this->currentTime->format('Y-m-d');
        $currentTime = $this->currentTime->format('H:i');    
        $this->timetable[$currentDate][] = array_merge($travel, [
            'attraction_id' => $place->id,
            'peakHour' => $place->popularHour($this->currentTime) !== null,
            'type' => $place->tags,
            'time' => $currentTime,
            'duration' => $stayDuration
        ]);
        $this->prevLocation = [$place->latitude, $place->longitude];
        $this->currentTime->addSeconds($stayDuration);
    }

    private function getOpeningHour($place)
    {
        $hours = $this->fetchHours($place->place_id);
        if (isset($hours->error)) {
            throw new ServiceUnavailableHttpException('', $hours->message);
        }
        return $hours;
    }

    private function nextDay()
    {
        $this->prevLocation = null;
        $this->resetMeal();
        $this->setPreference($this->perferences);
        $this->currentTime->addDay()->setTime(11, 0, 0);
        $this->lastMealTime = $this->currentTime->copy()->setTime(8, 30, 0);
        $this->location = [$this->lodging->latitude, $this->lodging->longitude];
        $this->appendTimeslot($this->lodging);
    }

    private function backToLodging()
    {
        $this->appendTimeslot($this->lodging);
    }

    private function updateWakeupTime()
    {
        $currentDate = $this->currentTime->format('Y-m-d');
        $lodging = &$this->timetable[$currentDate][0];
        $firstPlace = $this->timetable[$currentDate][1];
        $lodgingTime = Carbon::parse($lodging['time']);
        $arrivalTime = Carbon::parse($firstPlace['time']);
        $correctTime = $arrivalTime->subSeconds($firstPlace['travel_duration']);
        if ($correctTime->ne($lodgingTime)) {
            $lodging['time'] = $correctTime->format('H:i');
        }
    }

    private function initLodging()
    {
        $tag = '4bf58dd8d48988d1fa931735';
        $lodging = $this->getPlaces($tag, $this->location);
        $this->location = [$lodging->latitude, $lodging->longitude];
        $this->appendTimeslot($lodging);
        $this->lodging = $lodging;
    }

    private function getPlaces(&$tags, $location, $count = 0)
    {
        if ($count > 10) {
            return null;
        }
        if (!is_string($tags) && !$tags->valid()) {
            $tags = $this->getCorrespondingTags()->getIterator();
            return $this->getPlaces($tags, $location, ++$count);
        }
        $tag = is_string($tags) ? $tags : $tags->current();
        $places = $this->fetchRecommandedPlaces($tag, $location, [
            'localDay' => $this->currentTime->dayOfWeek === 0 ? 7 : $this->currentTime->dayOfWeek,
            'localTime' => $this->currentTime->format('H:i')
        ]);
        if (isset($places->error)) {
            return $this->getPlaces($tags, $location, ++$count);
        }
        $place = $this->filterAttraction($places);
        if (!is_string($tags)) {
            $tags->next();
        }
        if ($place == null) {
            return $this->getPlaces($tags, $location, ++$count);
        }
        return $place;
    }

    private function getStayDuration($place)
    {
        if ($place->stay_duration > 0) {
            return $place->stay_duration * 3600;
        }
        // Temp. Future plan: Extract from DB by using attraction tag.
        return rand(3600, 10800);
    }
    
    private function getTravelInfo($origin, $destination)
    {
        $options = [
            'mode' => 'transit',
            'departure_time' => $this->currentTime->timestamp
        ];
        $result = $this->fetchDirection($origin, $destination, $options);
        if (isset($result->error)) {
            throw new ServiceUnavailableHttpException('', $result->message);
        }
        $step = $result->legs[0];
        return [
            'travel_duration' => $step->duration->value,
            'distance' => $step->distance->value,
            'fare' => $result->fare ?? [],
            'mode' => empty($result->fare) ? $result->mode : 'transit',
        ];
    }

    private function transformCategories($categories)
    {
        $result = [];
        foreach ($categories as $category) {
            $result[] = $category->id;
        }
        return $result;
    }

    private function filterAttraction($places)
    {
        $picked = null;
        foreach ($places as $place) {
            $venue = $place->venue;
            $placeId = $venue->id;
            if (!in_array($placeId, $this->visitedPlaces)) {
                $place = Attraction::firstOrCreate([
                    'place_id' => $placeId
                ], [
                    'city_id'     => $this->cityId,
                    'name'        => $venue->name,
                    'address'     => join(' ', $venue->location->formattedAddress),
                    'tags'        => $this->transformCategories($venue->categories),
                    'latitude'    => $venue->location->lat,
                    'longitude'   => $venue->location->lng,
                    'rating'      => $venue->rating ?? 0,
                    'website'     => $venue->url ?? '',
                    'phone'       => $venue->contact->phone ?? '',
                    'price_level' => $venue->price->tier ?? 0,
                ]);
                if (!isset($picked)) {
                    if (!isset($place->opening_hours) || Carbon::now()->diffInDays($place->updated_at) > 14) {
                        $result = $this->getOpeningHour($place);
                        $place->fill([
                            'opening_hours' => $result->hours,
                            'popular'       => $result->popular
                        ])->save();
                    }
                    $hours = $place->openingHour($this->currentTime);
                    if ($hours != null) {
                        if ($this->firstPlace() && $this->isMorning($hours->openAt)) {
                            $this->currentTime->setTime($hours->openAt->hour, $hours->openAt->minute);
                        }
                        $stayDuration = $this->getStayDuration($place);
                        $stayTime = $this->currentTime->copy()->addSeconds($stayDuration);
                        $acutualStayDuration = abs($hours->closeAt->diffInSeconds($stayTime));
                        if ($acutualStayDuration >= $stayDuration * 0.7) {
                            $picked = $place;
                        } else {
                            $place = null;
                        }
                    } else {
                        $place = null;
                    }
                }
            } else {
                $place = null;
            }
        }
        return $picked;
    }

    private function getCorrespondingTags()
    {
        $preferences = $this->perferences->toArray();
        $tags = $preferences[$this->preferenceIdx]['attraction_tags'];
        if ($this->preferenceIdx < sizeof($preferences) - 1) {
            $this->preferenceIdx++;
        } else {
            $this->setPreference($this->perferences);
        }
        return collect($tags);
    }

    private function setMealTime($time)
    {
        $this->meal[$this->getMealIdx($time)]['value'] = true;
        $this->lastMealTime = $time;
    }

    private function getMealIdx($time)
    {
        $dinner = $time->copy()->setTime(18, 0, 0);
        $hours = $time->diffInHours($this->lastMealTime);
        if ($hours > 4 || $time->gt($dinner)) {
            foreach ($this->meal as $key => $meal) {
                if (!$meal['value']) {
                    return $key;
                }
            }
        }
        return -1;
    }

    private function isMealTime()
    {
        $mealIdx = $this->getMealIdx($this->currentTime);
        return $mealIdx !== -1 && !$this->meal[$mealIdx]['value'];;
    }

    private function isDinnerTime()
    {
        return $this->getMealIdx($this->currentTime) == 1;
    }

    private function finishedDinner()
    {
        return $this->meal[1]['value'];
    }

    private function resetMeal()
    {
        $this->meal = [
            ['key' => 'lunch', 'value' => false], 
            ['key' => 'dinner', 'value' => false]
        ];
    }

    private function isMorning($time)
    {
        if ($time === false) {
            return false;
        }
        $hour = $time->format('H');
        return $hour > 8 && $hour < 12;
    }

    private function firstPlace()
    {
        $currentDate = $this->currentTime->format('Y-m-d');
        return $this->timetable[$currentDate] == null || sizeof($this->timetable[$currentDate]) === 1;
    }
}

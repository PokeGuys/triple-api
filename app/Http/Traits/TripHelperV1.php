<?php

namespace App\Http\Traits;

use App\Models\AttractionCategory;
use App\Models\Attraction;
use App\Models\City;
use Carbon\Carbon;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


trait TripHelperV1 {
    use GoogleMapHelper;

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
    protected $prevPlaceId;

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
        $this->currentTime = Carbon::parse($options['visitDate'])->setTime(8, 30, 0);
        $this->lastMealTime = $this->currentTime->copy();
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
                $mealTags = $this->getMealTag()->getIterator();
                $this->setMealTime($this->currentTime);
                $restaurant = $this->getPlaces($mealTags, 2000, $this->location);
                if (!$restaurant) {
                    throw new NotFoundHttpException(trans('notfound.restaurants'));
                }
                $this->location = [$restaurant->latitude, $restaurant->longitude];
                $this->appendTimeslot($restaurant, 3600);
            } else if (!$this->isDinnerTime()) {
                $place = $this->getPlaces($tags, 5000, $this->location);
                if (!$place) {
                    throw new NotFoundHttpException(trans('notfound.attractions'));
                }
                $this->location = [$place->latitude, $place->longitude];
                $this->appendTimeslot($place, $this->getStayDuration($place));
            }
        }
        $this->backToLodging();
        $this->updateWakeupTime();
        if ($this->currentDay < $this->visitLength) {
            $this->nextDay();
            $this->currentDay++;
        }
        return $this->timetable[$currentTime];
    }

    public function appendTimeslot($place, $stayDuration = 0)
    {
        $travel = [];
        if (isset($this->prevPlaceId)) {
            $travel = $this->getTravelInfo($this->prevPlaceId, $place->place_id);
            $this->currentTime->addSeconds($travel['travel_duration']);
            if (isset($place->closeAt)) {
                $stayTime = $this->currentTime->copy()->addSeconds($stayDuration);
                if ($stayTime->gte($place->closeAt)) {
                    $stayDuration = abs($place->closeAt->diffInSeconds($stayTime));
                }
            }
        }
        $currentDate = $this->currentTime->format('Y-m-d');
        $currentTime = $this->currentTime->format('H:i');    
        $this->timetable[$currentDate][] = array_merge($travel, [
            'attraction_id' => $place->id,
            'type' => $place->tags,
            'time' => $currentTime,
            'duration' => $stayDuration
        ]);
        $this->prevPlaceId = $place->place_id;
        $this->visitedPlaces[] = $place->place_id;
        $this->currentTime->addSeconds($stayDuration);
    }

    private function nextDay()
    {
        $this->prevPlaceId = null;
        $this->resetMeal();
        $this->setPreference($this->perferences);
        $this->currentTime->addDay()->setTime(8, 30, 0);
        $this->lastMealTime = $this->currentTime->copy();
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
        $tag = 'lodging';
        $lodging = $this->getPlaces($tag, 50000, $this->location);
        if (!$lodging) {
            throw new NotFoundHttpException(trans('notfound.lodging'));
        }
        $this->location = [$lodging->latitude, $lodging->longitude];
        $this->appendTimeslot($lodging);
        $this->lodging = $lodging;
    }
    
    private function getPlaces(&$tags, $radius, $location, $count = 0)
    {
        if ($count > 5 || (!is_string($tags) && !$tags->valid())) {
            return null;
        }
        $tag = is_string($tags) ? $tags : $tags->current();
        $places = $this->fetchPlaces($location, $radius, ['type' => $tag]);
        if (isset($places->error)) {
            throw new ServiceUnavailableHttpException('', $places->message);
        }
        $this->filterAttraction($places, $tag);
        if (!is_string($tags)) {
            $tags->next();
        }
        if ($places == null) {
            return $this->getPlaces($tags, $radius + rand(500, 1000), $location, ++$count);
        }
        return $places->first();
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
        $result = $this->fetchDistance($origin, $destination, [
            'mode' => 'transit',
            'departure_time' => $this->currentTime->timestamp
        ]);
        if (isset($result->error)) {
            throw new ServiceUnavailableHttpException('', $result->message);
        }
        $step = $result->legs[0];
        return [
            'travel_duration' => $step->duration->value,
            'distance' => $step->distance->value,
            'fare' => $result->fare ?? [],
            'mode' => empty($result->fare) ? $result->mode : 'transit',
            'route' => $step->steps ?? []
        ];
    }

    private function filterAttraction(&$places, $tag)
    {
        foreach ($places as &$place) {
            $placeId = $place->place_id;
            if (isset($place->opening_hours->open_now) && !in_array($placeId, $this->visitedPlaces)) {
                $newAdded = false;
                $attraction = Attraction::where('place_id', $placeId)->first();
                if ($attraction == null) {
                    $newAdded = true;
                    $attraction = Attraction::create([
                        'place_id' => $placeId,
                        'city_id'  => $this->cityId
                    ]);
                }
                if ($newAdded || Carbon::now()->diffInDays($attraction->updated_at) > 14) {
                    $info = $this->fetchPlaceInfo($placeId);
                    if (isset($info->error)) {
                        throw new ServiceUnavailableHttpException('', $info->message);
                    }
                    $attraction->fill([
                        'name'          => $place->name,
                        'opening_hours' => $info->opening_hours->periods ?? null,
                        'address'       => $info->formatted_address,
                        'tags'          => $place->types,
                        'latitude'      => $place->geometry->location->lat,
                        'longitude'     => $place->geometry->location->lng,
                        'rating'        => $place->rating ?? 0,
                        'website'       => $info->website ?? '',
                        'phone'         => $info->international_phone_number ?? '',
                        'price_level'   => $place->price_level ?? 0
                    ])->save();
                }
                $attraction->tags = $tag;
                $openTime = $attraction->openTime($this->currentTime);
                if ($openTime !== false) {
                    $openAt = $openTime[0];
                    $closeAt = $openTime[1] ?? null;
                    $attraction->closeAt = $closeAt != null ? $closeAt : null;
                    if ($this->firstPlace() && $this->isMorning($openAt)) {
                        $this->currentTime->setTime($openAt->hour, $openAt->minute, 0);
                    }
                    if (($closeAt != null && !$this->currentTime->between($openAt, $closeAt)) || $this->currentTime->lt($openAt)) {
                        $place = null;
                    } else {
                        $place = $attraction;
                    }
                } else {
                    $place = null;
                }
            } else {
                $place = null;
            }
        }
        $places = collect(array_filter($places));
    }

    private function getMealTag()
    {
        return collect([
            'meal_takeaway',
            'meal_delivery',
            'restaurant'
        ]);
        // $breakfast = $this->currentTime->copy()->setTime(10, 0, 0);
        // if ($this->currentTime->lt($breakfast)) {
        //     return collect([
        //         'bakery',
        //         'cafe',
        //         'convenience_store'
        //     ]);
        // } else {
        //     return collect([
        //         'meal_takeaway',
        //         'meal_delivery',
        //         'restaurant'
        //     ]);
        // }
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

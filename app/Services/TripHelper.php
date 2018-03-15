<?php

namespace App\Services;

use App\Models\Attraction;
use App\Services\Foursquare\ExploreAPI;
use App\Services\Foursquare\RecommandationAPI;
use App\Services\Foursquare\OpeningHourAPI;
use App\Services\Google\DirectionAPI;
use GuzzleHttp\Promise;
use Carbon\Carbon;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Validator;

class TripHelper
{
    private $preferences;
    private $preferenceIdx;
    private $cityId;
    private $location;
    private $visitDate;
    private $visitLength;

    protected $explore;
    protected $lodging;
    protected $meal;
    protected $currentDay;
    protected $currentTime;
    protected $lastMealTime;
    protected $timetable;
    protected $prevLocation;
    public $visitedPlaces;

    public function __construct($options) {
        $this->currentDay = 1;
        $this->preferenceIdx = 0;
        $this->visitedPlaces['place_id'] = [];
        $this->visitedPlaces['id'] = [];
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

    public function setPreference($preferences) {
        $this->preferenceIdx = 0;
        $this->perferences = $preferences->shuffle();
    }

    public function generateTimetable() {
        $currentTime = $this->currentTime->format('Y-m-d');
        while (!$this->finishedDinner()) {
            $type = null;
            $duration = null;
            $recommand = new RecommandationAPI();
            $tags = $this->getCorrespondingTags();
            if ($this->isMealTime()) {
                $this->setMealTime($this->currentTime);
                $type = 'MEAL';
                $tags = ['4d4b7105d754a06374d81259'];
                $duration = 3600;
            }
            $place = $this->getPlace($tags, $this->location);
            $this->location = [$place->latitude, $place->longitude];
            $this->visitedPlaces['place_id'][] = $place->place_id;
            $this->visitedPlaces['id'][] = $place->id;
            $this->appendTimeslot($place, $duration ?? $this->getStayDuration($place), $type);
        }
        $this->backToLodging();
        $this->updateWakeupTime();
        if ($this->currentDay < $this->visitLength) {
            $this->nextDay();
            $this->currentDay++;
        }
        return $this->timetable[$currentTime];
    }

    private function appendTimeslot($place, $duration = 0, $type = null) {
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
            'type' => $type,
            'time' => $currentTime,
            'duration' => $duration
        ]);
        $this->prevLocation = [$place->latitude, $place->longitude];
        $this->currentTime->addSeconds($duration);
    }

    private function getPlace($tags, $location) {
        $explore = new ExploreAPI();
        $recommand = new RecommandationAPI();
        if ($this->explore === null) {
            $requestQueue[] = $explore->fetch(30000, $this->location);
        }
        // 1. All tags
        // foreach ($tags as $tag) {
        //     $requestQueue[] = $recommand->fetch($tag, $location, [
        //         'localDay' => $this->currentTime->dayOfWeek === 0 ? 7 : $this->currentTime->dayOfWeek,
        //         'localTime' => $this->currentTime->format('H:i')
        //     ]);
        // }
        // $responses = Promise\settle($requestQueue)->wait();
        // $numItems = count($requestQueue) + 1;
        // $i = 0;
        // $places = [];
        // foreach ($responses as $response) {
        //     if ($this->explore === null && ++$i === $numItems) {
        //         $places['explore'] = $explore->parse($response);
        //     } else {
        //         $places['recommand'] = array_merge($places['recommand'], $recommand->parse($response));
        //     }
        // }

        // 2. Current tag & explore api.
        $requestQueue[] = $recommand->fetch($tags[0], $this->location, [
            'localDay' => $this->currentTime->dayOfWeek === 0 ? 7 : $this->currentTime->dayOfWeek,
            'localTime' => $this->currentTime->format('H:i')
        ]);
        $response = Promise\settle($requestQueue)->wait();
        $places = count($response) > 1 ? [
            'explore' => $explore->parse($response[0]),
            'recommand' => $recommand->parse($response[1])
        ] : [
            'recommand' => $recommand->parse($response[0])
        ];

        return $this->filterAttraction($places);
    }

    private function filterAttraction($placesWithType) {
        $api = new OpeningHourAPI();
        list($missingPlace, $requestQueue) = $this->insertPlaces($api, $placesWithType['recommand']);
        $places = $placesWithType['recommand'];
        if (isset($placesWithType['explore'])) {
            foreach ($placesWithType['explore'] as $key => $explore) {
                $venue = $explore->venue;
                if ($this->isGym($venue->categories[0]->name) || $this->isGym($venue->name) || $this->isFood($venue->categories[0]->name)) {
                    unset($placesWithType['explore'][$key]);
                }
            }
            list($missingExplore, $exploreRequestQueue) = $this->insertPlaces($api, $placesWithType['explore']);
            $missingPlace = array_merge($missingPlace, $missingExplore);
            $requestQueue = array_merge($requestQueue, $exploreRequestQueue);
            $this->explore = $placesWithType['explore'];
            $places = array_merge($places, $placesWithType['explore']);
        } else {
            $places = array_merge($places, $this->explore);
        }
        if (count($requestQueue) > 0) {
            $responses = Promise\settle($requestQueue)->wait();
            for ($i = 0; $i < count($responses); ++$i) {
                $place = $places[$missingPlace[$i]];
                $hour = $api->parse($responses[$i]);
                $place->fill([
                    'opening_hours' => $hour->hours,
                    'popular'       => $hour->popular
                ])->save();
            }
        }
        foreach ($places as $place) {
            if (!in_array($place->place_id, $this->visitedPlaces['place_id'])) {
                $picked = $place;
                $hours = $place->openingHour($this->currentTime);
                if ($hours != null) {
                    if ($this->firstPlace() && $this->isMorning($hours->openAt)) {
                        $this->currentTime->setTime($hours->openAt->hour, $hours->openAt->minute);
                    }
                    $stayDuration = $this->getStayDuration($place);
                    $stayTime = $this->currentTime->copy()->addSeconds($stayDuration);
                    $acutualStayDuration = abs($hours->closeAt->diffInSeconds($stayTime));
                    if ($acutualStayDuration >= $stayDuration * 0.7) {
                        return $place;
                    }
                }
            }
        }
        return $picked;
    }

    private function insertPlaces($api, &$places) {
        $missingPlace = $requestQueue = [];
        foreach ($places as $key => $place) {
            $venue = $places[$key]->venue;
            $placeId = $venue->id;
            if (!in_array($placeId, $this->visitedPlaces['place_id'])) {
                if (isset($venue->photos->count)) {
                    $photo = $this->transformPhoto($venue->photos->groups[0]->items[0]);
                }
                if (isset($venue->photo)) {
                    $photo = $this->transformPhoto($venue->photo);
                }
                $places[$placeId] = Attraction::firstOrCreate([
                    'place_id' => $placeId
                ], [
                    'city_id'     => $this->cityId,
                    'name'        => $venue->name,
                    'address'     => join(' ', $venue->location->formattedAddress),
                    'photos'      => $photo ?? [],
                    'tags'        => $this->transformCategories($venue->categories),
                    'latitude'    => $venue->location->lat,
                    'longitude'   => $venue->location->lng,
                    'rating'      => $venue->rating ?? 0,
                    'website'     => $venue->url ?? '',
                    'phone'       => $venue->contact->phone ?? '',
                    'price_level' => $venue->price->tier ?? 0,
                ]);
                if (!isset($places[$placeId]->opening_hours) || Carbon::now()->diffInDays($places[$placeId]->updated_at) > 14) {
                    $missingPlace[] = $placeId;
                    $requestQueue[] = $api->fetch($placeId);
                }
            }
            unset($places[$key]);
        }
        return [$missingPlace, $requestQueue];
    }

    private function nextDay() {
        $this->prevLocation = null;
        $this->resetMeal();
        $this->setPreference($this->perferences);
        $this->currentTime->addDay()->setTime(11, 0, 0);
        $this->lastMealTime = $this->currentTime->copy()->setTime(8, 30, 0);
        $this->location = [$this->lodging->latitude, $this->lodging->longitude];
        $this->appendTimeslot($this->lodging, 0, 'LODGING');
    }

    private function backToLodging()
    {
        $this->appendTimeslot($this->lodging, 0, 'LODGING');
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

    private function initLodging() {
        $lodging = $this->getPlace(['4bf58dd8d48988d1fa931735'], $this->location);
        $this->location = [$lodging->latitude, $lodging->longitude];
        $this->appendTimeslot($lodging, 0, 'LODGING');
        $this->lodging = $lodging;
    }

    private function getStayDuration($place) {
        if ($place->stay_duration > 0) {
            return $place->stay_duration * 3600;
        }
        // Temp. Future plan: Extract from DB by using attraction tag.
        return rand(3600, 10800);
    }
    
    private function getTravelInfo($origin, $destination) {
        $api = new DirectionAPI();
        $options = [
            'mode' => 'transit',
            'departure_time' => $this->currentTime->timestamp
        ];
        $result = $api->fetch($origin, $destination, $options);
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

    private function isGym($category) {
        return Validator::make(['category' => $category], [
            'category' => ['regex:/dojo|fitness|fittness/i']
        ])->passes();
    }

    private function isFood($category) {
        return Validator::make(['category' => $category], [
            'category' => ['regex:/bar|restaurant|kitchen|grill|buffet|sandwich|steak|walmart|pub|brewery|warehouse|big\sbox\sstore|grocrey/i']
        ])->passes();
    }

    private function transformPhoto($photo) {
        return $photo->prefix . 'original' . $photo->suffix;
    }

    private function transformCategories($categories) {
        $result = [];
        foreach ($categories as $category) {
            $result[] = $category->id;
        }
        return $result;
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
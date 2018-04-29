<?php

namespace App\Services;

use App\Models\Attraction;
use App\Models\CategoryDuration;
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
    /**
     * [User preference list]
     * @var array
     */
    private $preferences;

    /**
     * [Current index for itierating preference list]
     * @var integer
     */
    private $preferenceIdx;

    /**
     * [The initial city id]
     * @var integer
     */
    private $cityId;

    /**
     * [The current position in geolocation]
     * @var array
     */
    private $location;

    /**
     * [The start date of the trip]
     * @var Carbon
     */
    private $visitDate;

    /**
     * [The trip length]
     * @var integer
     */
    private $visitLength;

    /**
     * [The featured attraction list]
     * @var array
     */
    protected $explore;

    /**
     * [The starting point (Hotel)]
     * @var Attraction
     */
    protected $lodging;

    /**
     * [The status of having meal]
     * @var array
     */
    protected $meal;

    /**
     * [Current Date used for appending attraction to timetable]
     * @var Carbon
     */
    protected $currentDay;

    /**
     * [Current time used for appending attraction to timetable]
     * @var Carbon
     */
    protected $currentTime;

    /**
     * [Last meal time]
     * @var Carbon
     */
    protected $lastMealTime;

    /**
     * [The trip timetable]
     * @var array
     */
    protected $timetable;

    /**
     * [The previous position in geolocation]
     * @var array
     */
    protected $prevLocation;

    /**
     * [History of visited places]
     * @var array
     */
    public $visitedPlaces;

    /**
     * [Initializing setting and required variable]
     * @param array $options [Trip related settings]
     */
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

    /**
     * [Setup preference list, shuffle to randomize the itinerary item]
     * @param Collection $preferences [User preferences]
     */
    public function setPreference($preferences) {
        $this->preferenceIdx = 0;
        $this->preferences = $preferences->shuffle();
    }

    /**
     * [Generate whole timetable by using the user preferences]
     * @return array [The itinerary about that day]
     */
    public function generateTimetable() {
        /**
         * [Clone current time avoid change of date]
         * @var Carbonm
         */
        $currentTime = $this->currentTime->format('Y-m-d');

        // Loop through the whole day until it reached dinner time.
        // Assume dinner time is the last activity and all attractions are closed.
        while (!$this->finishedDinner()) {
            // Initializing ${type} and ${duration} variable to avoid repeating the code.
            $type = null;
            $duration = null;
            $recommand = new RecommandationAPI();
            // Getting the attraction category by using user preference
            $tags = $this->getCorrespondingTags();
            // Check is it time to have lunch / dinner
            if ($this->isMealTime()) {
                // Set current time as meal time
                $this->setMealTime($this->currentTime);
                $type = 'MEAL';
                // Forcing the attraction category to food.
                $tags = ['4d4b7105d754a06374d81259'];
                // Forcing the stay duration to one hour.
                $duration = 3600;
            }
            // Get list of attraction by using the attraction category and current position.
            $place = $this->getPlace($tags, $this->location);
            // Update current position to featured attraction
            $this->location = [$place->latitude, $place->longitude];
            // Update visited history
            $this->visitedPlaces['place_id'][] = $place->place_id;
            $this->visitedPlaces['id'][] = $place->id;
            // Append it into timetable.
            $this->appendTimeslot($place, $duration ?? $this->getStayDuration($place), $type);
        }
        // When the day is finished (after dinner). Back to the hotel.
        // Finished a day
        $this->backToLodging();
        $this->updateWakeupTime();
        if ($this->currentDay < $this->visitLength) {
            $this->nextDay();
            $this->currentDay++;
        }
        // Response today's timetable.
        return $this->timetable[$currentTime];
    }

    /**
     * [Append attraction to timeslot]
     * @param  Attraction  $place    [The chosen one]
     * @param  integer $duration [Stay duration]
     * @param  string  $type     [Meal or not]
     * @return void
     */
    private function appendTimeslot($place, $duration = 0, $type = null) {
        $travel = [];
        // Check is it required to calculate the travel duration
        // By checking is the previous location variable is empty.
        if (isset($this->prevLocation)) {
            // Geting the travel information (duration, distance, route)
            $travel = $this->getTravelInfo($this->prevLocation, [$place->latitude, $place->longitude]);
            $this->currentTime->addSeconds($travel['travel_duration']);
        }
        // Clone variable to avoid any changes.
        $currentDate = $this->currentTime->format('Y-m-d');
        $currentTime = $this->currentTime->format('H:i');
        /**
         * [Append required information to timetable]
         * @var integer $attraction_id  [Attraction id from Database]
         * @var boolean $peakHour   [Is it peak hour at that time]
         * @var string  $type   [Is this a resturant for meal]
         * @var string  $time   [Current time]
         * @var integer $duration   [The stay duration for this place]
         */
        $this->timetable[$currentDate][] = array_merge($travel, [
            'attraction_id' => $place->id,
            'peakHour' => $place->popularHour($this->currentTime) !== null,
            'type' => $type,
            'time' => $currentTime,
            'duration' => $duration
        ]);
        // Update previous location as this place location
        $this->prevLocation = [$place->latitude, $place->longitude];
        // Add stay duration to current time.
        $this->currentTime->addSeconds($duration);
    }

    /**
     * [Get a featured place which is suitable for the user]
     * @param  array $tags     [The suitable attraction category]
     * @param  array $location [The current location]
     * @see    $this->filterAttraction
     * @return Attraction           [The chosen one]
     */
    private function getPlace($tags, $location) {
        $explore = new ExploreAPI();
        $recommand = new RecommandationAPI();
        // Skip explore api if it is the initialize stage (Which means choose the suitable hotel)
        if ($tags[0] != '4bf58dd8d48988d1fa931735' && $this->explore === null) {
            // Append explore api to request queue.
            $requestQueue[] = $explore->fetch(30000, $this->location);
        }

        // Current tag & explore api.
        // Append the required field to 3rd-party api, Foursquare, to fetch related attraction and is opening at that moment.
        // And add it to the request queue.
        $requestQueue[] = $recommand->fetch($tags[0], $this->location, [
            'localDay' => $this->currentTime->dayOfWeek === 0 ? 7 : $this->currentTime->dayOfWeek,
            'localTime' => $this->currentTime->format('H:i')
        ]);
        // Start sending all the requests in the queue.
        $response = Promise\settle($requestQueue)->wait();
        // Append post-processing method to an raw list of attractions
        // Which may contains the place without opening hour etc.
        $places = count($response) > 1 ? [
            'explore' => $explore->parse($response[0]),
            'recommand' => $recommand->parse($response[1])
        ] : [
            'recommand' => $recommand->parse($response[0])
        ];

        // Start filtering the unqualified attractions
        return $this->filterAttraction($places);
    }

    /**
     * [Filtering the unqualified attractions]
     * @param  array $placesWithType [The hanlding method]
     * @return Attraction                 [The chosen one]
     */
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
        } else if (isset($this->explore)) {
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
                    'rating_count' => $venue->ratingSignals ?? 0,
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

    /**
     * [Reset all related itinerary variable to the starting point for generating the next day trip items]
     * @return void
     */
    private function nextDay() {
        $this->prevLocation = null;
        $this->resetMeal();
        $this->setPreference($this->preferences);
        $this->currentTime->addDay()->setTime(11, 0, 0);
        $this->lastMealTime = $this->currentTime->copy()->setTime(8, 30, 0);
        $this->location = [$this->lodging->latitude, $this->lodging->longitude];
        $this->appendTimeslot($this->lodging, 0, 'LODGING');
    }

    /**
     * [Append back to the hotel as last acitivity.]
     * @return void
     */
    private function backToLodging()
    {
        $this->appendTimeslot($this->lodging, 0, 'LODGING');
    }

    /**
     * [Fix all incorrect arrival time]
     * @return void
     */
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

    /**
     * [Initializing hotel as itinerary first item]
     */
    private function initLodging() {
        $lodging = $this->getPlace(['4bf58dd8d48988d1fa931735'], $this->location);
        $this->location = [$lodging->latitude, $lodging->longitude];
        $this->appendTimeslot($lodging, 0, 'LODGING');
        $this->lodging = $lodging;
    }

    /**
     * [Determine how long should he/she stay in that place]
     * @param  Attraction $place [The chosen attraction]
     * @return integer        [The stay duration]
     */
    private function getStayDuration($place) {
        if ($place->stay_duration > 0) {
            return $place->stay_duration * 3600;
        }
        $defaultDuration = CategoryDuration::where('category', 'FIXED_DURATION')->first();
        $category = CategoryDuration::where('category', $place->tags[0])->first();
        if ($category) {
            return $category->duration;
        }
        return $defaultDuration->duration;
    }

    /**
     * [Fetching Google Direction API to obtain travel information between two spot]
     * @param  array $origin      [Starting point]
     * @param  array $destination [Ending point]
     * @return array              [Travel Information]
     */
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

    /**
     * [Is it a gym room?]
     * @param  string  $category [Attraction category]
     * @return boolean           [It is a gym room]
     */
    private function isGym($category) {
        return Validator::make(['category' => $category], [
            'category' => ['regex:/dojo|fitness|fittness/i']
        ])->passes();
    }

    /**
     * [Is it a food place?]
     * @param  string  $category [Attraction category]
     * @return boolean           [It is a food place]
     */
    private function isFood($category) {
        return Validator::make(['category' => $category], [
            'category' => ['regex:/bar|restaurant|kitchen|grill|buffet|sandwich|steak|walmart|pub|brewery|warehouse|big\sbox\sstore|grocrey/i']
        ])->passes();
    }

    /**
     * [Combine the prefix and suffix to restore the full path of attraction photo from Foursquare]
     * @param  object $photo [Photo info]
     * @return array        [The correct url]
     */
    private function transformPhoto($photo) {
        return [$photo->prefix . 'original' . $photo->suffix];
    }

    /**
     * [Extract category id from attraction category object]
     * @param  object $categories [The whole list of category with other unrelated information]
     * @return array             [List of category id]
     */
    private function transformCategories($categories) {
        $result = [];
        foreach ($categories as $category) {
            $result[] = $category->id;
        }
        return $result;
    }

    /**
     * [Obtaining the suitable category from the user preference]
     * @return Collection [List of categories]
     */
    private function getCorrespondingTags()
    {
        $preferences = $this->preferences->toArray();
        $tags = $preferences[$this->preferenceIdx]['attraction_tags'];
        if ($this->preferenceIdx < sizeof($preferences) - 1) {
            $this->preferenceIdx++;
        } else {
            $this->setPreference($this->preferences);
        }
        return collect($tags);
    }

    /**
     * [Update the meal is finished]
     * @param Carbon $time [Current time]
     */
    private function setMealTime($time)
    {
        $this->meal[$this->getMealIdx($time)]['value'] = true;
        $this->lastMealTime = $time;
    }

    /**
     * [Determine which type of meal it is by using current time]
     * @param  Carbon $time [Current time]
     * @return integer       [Current meal index]
     */
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

    /**
     * [Verify is it time to have a meal or is it finished]
     * @return boolean [It is a meal time]
     */
    private function isMealTime()
    {
        // Determine which type of meal it is by using current time
        $mealIdx = $this->getMealIdx($this->currentTime);
        return $mealIdx !== -1 && !$this->meal[$mealIdx]['value'];
    }

    /**
     * [Validate is the dinner finished]
     * @return boolean [It is finished]
     */
    private function finishedDinner()
    {
        return $this->meal[1]['value'];
    }

    /**
     * [Reset meal setting to the first step]
     */
    private function resetMeal()
    {
        $this->meal = [
            ['key' => 'lunch', 'value' => false],
            ['key' => 'dinner', 'value' => false]
        ];
    }

    /**
     * [Verify it is morning at that moment used for changing the itinerary start time]
     * @param  Carbon  $time [Current time]
     * @return boolean       [It is in the morning]
     */
    private function isMorning($time)
    {
        if ($time === false) {
            return false;
        }
        $hour = $time->format('H');
        return $hour > 8 && $hour < 12;
    }

    /**
     * [Verify is it the first item of the itinerary]
     * @return boolean [It is the first itinerary item]
     */
    private function firstPlace()
    {
        $currentDate = $this->currentTime->format('Y-m-d');
        return $this->timetable[$currentDate] == null || sizeof($this->timetable[$currentDate]) === 1;
    }
}

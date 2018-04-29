<?php

namespace App\Http\Controllers\Auth;

use Auth;
use Cache;
use Log;
use Validator;
use Carbon\Carbon;
use App\Models\Attraction;
use App\Models\City;
use App\Models\ItineraryItem;
use App\Models\Trip;
use App\Models\TripItinerary;
use App\Models\User;
use App\Services\TripHelper;
use App\Services\Wikipedia\SearchAPI;
use App\Services\Wikipedia\SummaryAPI;
use App\Services\Foursquare\DetailAPI;
use App\Http\Controllers\Controller;
use App\Transformers\AttractionTransformer;
use App\Transformers\TripTransformer;
use Dingo\Api\Routing\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Dingo\Api\Exception\StoreResourceFailedException;

/**
 * @Resource("Trip", uri="/trip")
 */
class TripController extends Controller
{
    use Helpers;

    /**
     * Get trip list created by user
     *
     * @Get("/")
     * @Versions({"v1"})
     * @Response(200, body=)
     */
    public function listTrip() {
        try {
            $user = Auth::getUser();
            $trips = Cache::remember("trips_user_{$user->id}", 10, function() use ($user) {
                return $user->trips()->orderBy('visit_date', 'desc')->get()->filter(function ($item) {
                    return Carbon::parse($item->visit_date)->gte(Carbon::now()->startOfDay()->subDays($item->visit_length - 1));
                });
            });
        } catch (\PDOException $e) {
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->collection($trips, new TripTransformer(['include' => 'city']));
    }

    public function listEndedTrip() {
        try {
            $user = Auth::getUser();
            $trips = Cache::remember("trips_user_{$user->id}_ended", 10, function() use ($user) {
                return $user->trips()->orderBy('visit_date', 'desc')->get()->filter(function ($item) {
                    return Carbon::parse($item->visit_date)->lt(Carbon::now()->startOfDay()->subDays($item->visit_length - 1));
                });
            });
        } catch (\PDOException $e) {
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->collection($trips, new TripTransformer(['include' => 'city']));
    }

    /**
     * Get filtered trip list created by user
     *
     * @Get("/search/{keyword}")
     * @Versions({"v1"})
     * @Parameters({
     *     @Parameter("keyword", type="string", required=true, description="The keyword of trip title to filter."),
     * })
     * @Response(200, body={ "data": { { "id": 298, "title": "Testing Trip", "owner_id": 27, "owner": "Test ing", "visit_date": "2018-02-20", "visit_length": 3, "created_at": 1518771616, "updated_at": 1518771616, "collaborators": {}, "image": "" } } })
     */
    public function listTripByKeyword($keyword) {
        try{
            $trips = Auth::User()->trips()->where('title', 'like', '%'.$keyword.'%')->orderBy('updated_at', 'desc')->get();
        } catch (\PDOException $e) {
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->collection($trips, new TripTransformer(['include' => 'city']));
    }

    /**
     * Get trip detail created by user
     *
     * @Get("/{id}")
     * @Versions({"v1"})
     * @Parameters({
     *     @Parameter("id", type="integer", required=true, description="The id of trip to filter."),
     * })
     * @Response(200, body={ "id": 298, "title": "Testing Trip", "owner_id": 27, "owner": "Test ing", "visit_date": "2018-02-20", "visit_length": 1, "created_at": 1518771616, "updated_at": 1518771616, "collaborators": { { "id": 166641, "user": { "id":"1", "first_name":"test", "last_name": "test" }, "created_at": 0, "updated_at": 0 } }, "itinerary": { { "id": 29402, "visit_date": "2018-02-20", "created_at": 1518771616, "updated_at": 1518771616, "nodes": { { "id": 1, "attraction_id": 796, "name": "晴空巷咖啡", "image": "", "type": {}, "tag": "BREAKFAST", "time": "08:30", "duration": 3600, "distance": 0, "travel_duration": 0, "fare": {}, "mode": "", "route": {} }, { "id": 2, "attraction_id": 148, "name": "Café Showroom", "image": "https://lh3.googleusercontent.com/p/AF1QipNr1xJMVekvqd1bqg8PjWF8t5DiFJU7-2C54os=s1600-w400", "type": {"ART_AND_ARCHITECTURE_LOVER", "FOODIE"}, "tag": "art_gallery", "time": "9:32", "duration": 3600, "distance": 225, "travel_duration": 167, "fare": {}, "mode": "walking", "route": {} } } } } })
     */
    public function listTripByUser($id) {
        try {
            $user = Auth::getUser();
            $trip = Cache::remember("trip_{$id}_user_{$user->id}", 10, function() use ($user, $id) {
                return $user->trips()->find($id);
            });
            if (!$trip) {
                throw new NotFoundHttpException(trans('notfound.trip'));
            }
        } catch (Exception $e) {
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->item($trip, new TripTransformer(['include' => ['city', 'collaborators', 'itinerary']]));
    }

    public function getBookmarks() {
        try {
            $user = Auth::getUser();
            $trips = Cache::remember("bookmark_trip_user_{$user->id}", 20, function() use ($user) {
                return $user->bookmarkedTrip()->orderBy('updated_at', 'desc')->get();
            });
        } catch (Exception $e) {
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->collection($trips, new TripTransformer(['include' => 'city']));
    }

    public function setBookmark($id) {
        try {
            $user = Auth::getUser();
            if (!Cache::remember("trip_{$id}_user_{$user->id}", 10, function() use ($user) { return $user->trips; }))
                throw new NotFoundHttpException(trans('notfound.trip'));
            $user->bookmarkedTrip()->syncWithoutDetaching(['trip_id' => $id]);
            Cache::put("bookmark_trip_user_{$user->id}", $user->bookmarkedTrip, 20);
        } catch (Exception $e) {
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->noContent();
    }

    /**
     * Create a new trip
     *
     * @Post("/")
     * @Versions({"v1"})
     * @Request("title=foo&visit_date=2018-02-21&visit_length=3&city_id=1&auto_generate=1", contentType="application/x-www-form-urlencoded")
     * @Response(201)
     */
    public function createTrip(Request $request) {
        $validator = Validator::make($request->all(), [
            'title'         => 'required|min:1|max:255',
            'visit_date'    => 'required|date|after:today',
            'visit_length'  => 'required|integer|min:1|max:7',
            'city_id'       => 'required|exists:cities,id',
            'auto_generate' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            throw new StoreResourceFailedException($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            $user = Auth::getUser();
            $city = City::find($request->city_id);
            $trip = $user->trips()->create([
                'title' => $request->title,
                'city_id' => $city->id,
                'visit_date' => $request->visit_date,
                'visit_length' => $request->visit_length
            ]);
            $visit_date = Carbon::parse($request->visit_date);
            $itinerary = [];
            for ($i = 0; $i < $request->visit_length; $i++) {
                $itinerary[] = [
                    'visit_date' => $visit_date->format('Y-m-j')
                ];
                $visit_date->addDay();
            }
            $itinerary_item = $trip->itinerary()->createMany($itinerary);
            if ($request->auto_generate) {
                $this->generateItinerary($city, $itinerary_item);
            }
            DB::commit();
            Cache::put("trips_user_{$user->id}", $user->trips, 10);
            Cache::put("trip_{$trip->id}_user_{$user->id}", $trip, 10);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->item($trip, new TripTransformer(['include' => ['city', 'collaborators', 'itinerary']]));
    }

    /**
     * Edit an existing trip
     *
     * @Put("/{id}")
     * @Versions({"v1"})
     * @Request("title=foo&visit_date=2018-02-21&visit_length=3", contentType="application/x-www-form-urlencoded")
     * @Parameters({
     *     @Parameter("id", type="integer", required=true, description="The id of trip to edit."),
     * })
     * @Response(204)
     */
    public function editTrip(Request $request, $id) {
        $validator = Validator::make($request->all(), [
            'title'        => 'present|max:255',
            'visit_date'   => 'present|date|after:today',
            'visit_length' => 'present|min:1|max:7'
        ]);
        if ($validator->fails()) {
            throw new StoreResourceFailedException($validator->errors()->first());
        }
        try {
            $user = Auth::User();
            if (!$trip = $user->trips()->find($id)) throw new NotFoundHttpException(trans('notfound.trip'));
            DB::beginTransaction();
            $orgin_date = Carbon::parse($trip->visit_date);
            $new_date = Carbon::parse($request->visit_date);
            $keep_date = $orgin_date->eq($new_date) && $trip->visit_length == $request->visit_length;
            $trip->fill([
                'title' => $request->title,
                'visit_date' => $request->visit_date,
                'visit_length' => $request->visit_length
            ])->save();
            Cache::put("trip_{$id}_user_{$user->id}", $trip, 10);
            if (!$keep_date) {
                $trip->itinerary()->forceDelete();
                $visit_date = Carbon::parse($request->visit_date);
                $itinerary = [];
                for ($i = 0; $i < $request->visit_length; $i++) {
                    $itinerary[] = [
                        'visit_date' => $visit_date->addDay()->format('Y-m-j')
                    ];
                }
                $trip->itinerary()->createMany($itinerary);
            }
            DB::commit();
        } catch (\PDOException $e) {
            DB::rollback();
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->noContent();
    }

    /**
     * Delete an existing trip
     *
     * @Delete("/{id}")
     * @Versions({"v1"})
     * @Parameters({
     *     @Parameter("id", type="integer", required=true, description="The id of trip to delete."),
     * })
     * @Response(204)
     */
    public function deleteTrip(Request $request, $id) {
        try {
            $user = Auth::User();
            if (!$trip = $user->trips()->find($id)) throw new NotFoundHttpException(trans('notfound.trip'));
            $trip->delete();
            Cache::put("trips_user_{$user->id}", $user->trips, 10);
            Cache::forget("trip_{$id}_user_{$user->id}");
        } catch (\PDOException $e) {
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->noContent();
    }

    public function assignItineraryItem(Request $request, $id) {
        $validator = Validator::make($request->all(), [
            'attraction_id' => 'required|integer|min:1|max:10',
            'visit_time'    => 'required|time',
            'duration' => 'required|min:600|max:18000'
        ]);
        if ($validator->fails()) {
            throw new StoreResourceFailedException($validator->errors()->first());
        }
        try {
            $tripItinerary = Auth::User()->itinerary()->find($id);
            $attraction = Attraction::find($request->attraction_id);
            if (!$tripItinerary || !$attraction) {
                throw new NotFoundHttpException(trans('notfound.trip'));
            }
            $tripItinerary->items()->create([
                'attraction_id' => $attraction->id,
                'visit_time' => $request->visit_time
            ]);
        } catch (Exception $e) {
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->created();
    }

    public function editItineraryItem(Request $request, $itinerary_id, $item_id) {
        $validator = Validator::make($request->all(), [
            'attraction_id' => 'required|integer|min:1|max:10',
            'visit_time'    => 'required|time',
            'duration'      => 'required|min:600|max:18000'
        ]);
        if ($validator->fails()) {
            throw new StoreResourceFailedException($validator->errors()->first());
        }
        try {
            $itinerary = Auth::User()->itinerary()->find($itinerary_id);
            if (!$itineraryItem = ItineraryItem::find($item_id)) throw new NotFoundHttpException(trans('notfound.itineraryItem'));
            if (!$attraction = Attraction::find($request->attraction_id)) throw new NotFoundHttpException(trans('notfound.attraction'));
            $itineraryItem->fill([
                'attraction_id' => $attraction->id,
                'visit_date' => $request->visit_date
            ])->save();
        } catch (Exception $e) {
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->noContent();
    }

    public function deleteItineraryItem(Request $request, $itinerary_id, $item_id) {
        try {
            $itinerary = Auth::User()->itinerary()->find($itinerary_id);
            if (!$itinerary) throw new NotFoundHttpException(trans('notfound.itinerary'));
            if (!$items = $itinerary->items()->find($item_id)) throw new NotFoundHttpException(trans('notfound.itineraryItem'));
            $itineraryItem->delete();
        } catch (\PDOException $e) {
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->noContent();
    }

    public function generateArticle($id) {
        try {
            $user = Auth::getUser();
            $trip = Cache::remember("trip_{$id}_userid_{$user->id}", 10, function() use ($user, $id) {
                return $user->trips()->find($id);
            });
            if (!$trip) {
                throw new NotFoundHttpException(trans('notfound.trip'));
            }
            $tripItem = Cache::remember("itinerary_item_trip_{$trip->id}", 60, function () use ($trip) {
                return $trip->items;
            });
            $articles = $tripItem->map(function ($value, $key) {
                $attraction = Cache::remember("attraction_{$value->attraction_id}", 60, function() use ($value) {
                    return $value->attraction;
                });
                if (!isset($attraction->description) || Carbon::now()->diffInDays($attraction->updated_at) > 14) {
                    $api = new DetailAPI();
                    $info = $api->fetch($attraction->place_id);
                    $bestName = $info->venueChains[0]->bestName->name ?? '';
                    $photos = $info->photos->groups[0]->items ?? [];
                    $existedPhotos = $attraction->photos;
                    $totalNum = 0;
                    if (isset($info->description)) {
                        $description = $info->description;
                    } else {
                        $searchAPI = new SearchAPI();
                        $keyword = !empty($bestName) ? $bestName : $info->name;
                        $openParenthesesIdx = strpos($keyword, '(');
                        $endParenthesesIdx = strpos($keyword, ')');
                        if ($openParenthesesIdx !== false && $endParenthesesIdx !== false) {
                            $keyword = trim(substr($keyword, 0, $openParenthesesIdx));
                        }
                        if (!empty($keyword)) {
                            $result = $searchAPI->fetch($keyword);
                            if (!isset($result->error)) {
                                if (StringSimilarity::compare($result->title, $keyword) > 0.7) {
                                    $summaryAPI = new SummaryAPI();
                                    $summary = $summaryAPI->fetch($result->title);
                                    if (!isset($summary->error)) {
                                        $description = $summary;
                                    }
                                }
                            }
                        }
                    }
                    foreach ($photos as $photo) {
                        $photo = $photo->prefix.'original'.$photo->suffix;
                        if (!in_array($photo, $existedPhotos)) {
                            $existedPhotos[] = $photo;
                            $totalNum++;
                        }
                    }
                    $attraction->increment('photo_count', $totalNum);
                    $attraction->fill([
                        'name' => $info->name,
                        'local_name' => $bestName,
                        'description' => $description ?? '',
                        'website' => $info->website ?? '',
                        'rating'      => $info->rating ?? 0,
                        'rating_count' => $info->ratingSignals ?? 0,
                        'website'     => $info->url ?? '',
                        'phone' => $info->contact->phone ?? '',
                        'price_level' => $info->price->tier ?? 0,
                        'photos' => $existedPhotos,
                        'address' => join(' ', $info->location->formattedAddress),
                        'price_level' => $info->price->tier ?? 0,
                    ])->save();
                    Cache::put("attraction_{$value->attraction_id}", $attraction, 60);
                }
                return $attraction;
            })->filter(function ($value, $key) {
                return !empty($value->description) && !in_array('4bf58dd8d48988d1fa931735', $value->tags);
            })->sortByDesc(function ($value, $key) {
                return $value->rating;
            });
        } catch (\Exception $e) {
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->collection($articles, new AttractionTransformer([
            'only' => ['id', 'name', 'local_name', 'photos', 'rating', 'description']
        ]));
    }

    private function generateItinerary($city, $itineraries) {
        try {
            // Set user preferences to helper class.
            // Create remaining timeslot array for each preferences.
            $tripPlanner = new TripHelper([
                'cityId' => $city->id,
                'location' => [$city->latitude, $city->longitude],
                'preferences' => Auth::User()->tags,
                'visitDate' => $itineraries->first()->visit_date,
                'visitLength' => sizeof($itineraries)
            ]);
            DB::beginTransaction();
            foreach ($itineraries as $node) {
                $timetable = $tripPlanner->generateTimetable();
                foreach ($timetable as $item) {
                    $node->items()->create([
                        'attraction_id' => $item['attraction_id'],
                        'visit_time' => $item['time'],
                        'duration' => $item['duration'],
                        'travel_duration' => $item['travel_duration'] ?? 0,
                        'distance' => $item['distance'] ?? 0,
                        'peak_hour' => $item['peakHour'] ?? false,
                        'type' => $item['type'],
                        'fare' => $item['fare'] ?? [],
                        'mode' => $item['mode'] ?? null
                    ]);
                }
            }
            $history = Auth::User()->visited();
            foreach ($tripPlanner->visitedPlaces['id'] as $visited) {
                $history->syncWithoutDetaching(['attraction_id' => $visited]);
            }
            DB::commit();
        } catch (\PDOException $e) {
            DB::rollback();
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
    }
}

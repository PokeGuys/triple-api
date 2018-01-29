<?php

namespace App\Http\Controllers;

use Auth;
use Log;
use Validator;
use Carbon\Carbon;
use App\Models\Attraction;
use App\Models\ItineraryItem;
use App\Models\Trip;
use App\Models\TripItinerary;
use App\Models\User;
use App\Http\Traits\TripHelper;
use App\Http\Controllers\Controller;
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

class TripController extends Controller
{
    use Helpers, TripHelper;

    public function listTrip()
    {
        try {
            $trips = Auth::User()->trips()->get();
        } catch (\PDOException $e) {
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->collection($trips, new TripTransformer(['include' => ['collaborators', 'attractions']]));
    }

    public function listTripByKeyword($keyword)
    {
        try{
            $trips = Auth::User()->trips()->where('title', 'like', '%'.$keyword.'%')->get();
            if (!$trips) {
                throw new NotFoundHttpException(trans('notfound.trips'));
            }
        } catch (\PDOException $e) {
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->collection($trips, new TripTransformer(['include' => ['collaborators', 'attractions']]));
    }

    public function listTripByUser($id)
    {
        try {
            $trip = Auth::User()->trips()->find($id);
            if (!$trip) {
                throw new NotFoundHttpException(trans('notfound.trip'));
            }
        } catch (Exception $e) {
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->item($trip, new TripTransformer(['include' => ['collaborators', 'itinerary']]));
    }

    public function createTrip(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'         => 'required|min:1|max:255',
            'visit_date'    => 'required|date',
            'visit_length'  => 'required|integer|min:1|max:30'
        ]);

        try {
            DB::beginTransaction();
            $trip = Auth::User()->trips()->create([
                'title' => $request->title,
                'visit_date' => $request->visit_date,
                'visit_length' => $request->visit_length
            ]);
            $visit_date = Carbon::parse($request->visit_date);
            $itinerary = [];
            for ($i = 0; $i < $request->visit_length; $i++) {
                $itinerary[] = [
                    'visit_date' => $visit_date->addDay()->format('Y-m-j')
                ];
            }
            $trip->itinerary()->createMany($itinerary);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->created();
    }

    public function editTrip(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title'         => 'present|max:255',
            'visit_date'    => 'present|date',
            'visit_length'  => 'present|min:1|max:30'
        ]);
        try {
            if (!$trip = Auth::User()->trips()->find($id)) throw new NotFoundHttpException(trans('notfound.trip'));
            DB::beginTransaction();
            $orgin_date = Carbon::parse($trip->visit_date);
            $new_date = Carbon::parse($request->visit_date);
            $keep_date = $orgin_date->eq($new_date) && $trip->visit_length == $request->visit_length;
            $trip->fill([
                'title' => $request->title,
                'visit_date' => $request->visit_date,
                'visit_length' => $request->visit_length
            ])->save();
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

    public function deleteTrip(Request $request, $id)
    {
        try {
            if (!$trip = Auth::User()->trips()->find($id)) throw new NotFoundHttpException(trans('notfound.trip'));
            $trip->delete();
        } catch (\PDOException $e) {
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->noContent();
    }

    //add item to Trip Itinerary
    public function assignItineraryItem(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'attraction_id'     => 'required|integer|min:1|max:10',
            'visit_time'        => 'required|time'
        ]);
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

    //edit Itinerary Item
    public function editItineraryItem(Request $request, $itinerary_id, $item_id)
    {
        $validator = Validator::make($request->all(), [
            'attraction_id'     => 'required|integer|min:1|max:10',
            'visit_time'    => 'required|time'
        ]);
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

    //delete Itinerary Item
    public function deleteItineraryItem(Request $request, $itinerary_id, $item_id)
    {
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

}

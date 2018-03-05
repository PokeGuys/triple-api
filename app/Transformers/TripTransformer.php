<?php

namespace App\Transformers;

use App\Models\Trip;
use Cache;

class TripTransformer extends TransformerAbstract
{
    protected $availableIncludes = ['collaborators', 'itinerary'];

    public function __construct($fields = null)
    {
        $this->fields = $fields;
    }

    public function transform(Trip $trip)
    {
        $this->model = $trip;
        $city = Cache::remember("city_{$trip->city_id}", 60, function() use ($trip) {
            return $trip->city;
        });
        $user = Cache::remember("user_{$trip->user_id}", 60, function() use ($trip) {
            return $trip->user;
        });
        return $this->transformWithField([
            'id' => $trip->id,
            'title' => $trip->title,
            'owner_id' => $trip->user_id,
            'image' => $city->photo_url,
            'owner' => $user->name(),
            'visit_date' => $trip->visit_date,
            'visit_length' => $trip->visit_length,
            'created_at' => strtotime($trip->created_at),
            'updated_at' => strtotime($trip->updated_at)
        ]);
    }

    public function includecollaborators(Trip $trip)
    {
        $collaborators = Cache::remember("trip_collaborators_{$trip->user_id}", 60, function () use ($trip) {
            return $trip->collaborators;
        });
        return $this->collection($collaborators, new UserTransformer([
            'only' => [
                'id',
                'username',
                'first_name',
                'last_name'
            ]
        ]));
    }

    public function includeitinerary(Trip $trip)
    {
        $itinerary = Cache::remember("trip_itinerary_{$trip->id}", 60, function () use ($trip) {
            return $trip->itinerary;
        });
        return $this->collection($itinerary, new TripItineraryTransformer);
    }
}

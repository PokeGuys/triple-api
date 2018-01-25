<?php

namespace App\Transformers;

use App\Models\Trip;

class TripTransformer extends TransformerAbstract
{
    protected $availableIncludes = ['collaborators', 'attractions', 'itinerary'];

    public function __construct($fields = null)
    {
        $this->fields = $fields;
    }

    public function transform(Trip $trip)
    {
        $this->model = $trip;
        return $this->transformWithField([
            'id' => $trip->id,
            'title' => $trip->title,
            'owner_id' => $trip->user_id,
            'owner' => $trip->user->name(),
            'visit_date' => $trip->visit_date,
            'visit_length' => $trip->visit_length,
            'created_at' => strtotime($trip->created_at),
            'updated_at' => strtotime($trip->updated_at)
        ]);
    }

    public function includecollaborators(Trip $trip)
    {
        return $this->collection($trip->collaborators, new UserTransformer([
            'only' => [
                'id',
                'first_name',
                'last_name'
            ]
        ]));
    }

    public function includeattractions(Trip $trip)
    {
        $itinerary = $trip->items()->whereHas('attraction')->get();
        return $this->collection($itinerary, new ItineraryItemTransformer);
    }

    public function includeitinerary(Trip $trip)
    {
        return $this->collection($trip->itinerary, new TripItineraryTransformer);
    }
}

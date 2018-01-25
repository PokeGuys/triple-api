<?php

namespace App\Transformers;

use App\Models\TripItinerary;

class TripItineraryTransformer extends TransformerAbstract
{
    protected $defaultIncludes = ['nodes'];

    public function __construct($fields = null)
    {
        $this->fields = $fields;
    }

    public function transform(TripItinerary $itinerary)
    {
        $this->model = $itinerary;
        return $this->transformWithField([
            'id' => $itinerary->id,
            'visit_date' => $itinerary->visit_date,
            'created_at' => strtotime($itinerary->created_at),
            'updated_at' => strtotime($itinerary->updated_at)
        ]);
    }

    public function includenodes(TripItinerary $itinerary)
    {
        return $this->collection($itinerary->items, new ItineraryItemTransformer);
    }
}

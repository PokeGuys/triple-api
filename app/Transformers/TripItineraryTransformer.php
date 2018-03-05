<?php

namespace App\Transformers;

use App\Models\TripItinerary;
use Cache;

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
        $items = Cache::remember("trip_itinerary_item_{$itinerary->id}", 60, function () use ($itinerary) {
            return $itinerary->items;
        });
        return $this->collection($items, new ItineraryItemTransformer);
    }
}

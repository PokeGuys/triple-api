<?php

namespace App\Transformers;

use App\Models\ItineraryItem;

class ItineraryItemTransformer extends TransformerAbstract
{
    protected $defaultIncludes = ['attraction'];

    public function __construct($fields = null)
    {
        $this->fields = $fields;
    }

    public function transform(ItineraryItem $item)
    {
        $this->model = $item;
        return $this->transformWithField([
            'id' => $item->id,
            'attraction_id' => $item->attraction_id,
            'visit_time' => $item->visit_time,
            'duration' => $item->duration,
            'travel_duration' => $item->travel_duration,
            'distance' => $item->distance,
            'fare' => $item->fare,
            'type' => $item->type,
            'peak_hour' => $item->peak_hour,
            'mode' => $item->mode,
            'created_at' => strtotime($item->created_at),
            'updated_at' => strtotime($item->updated_at)
        ]);
    }

    public function includeattraction(ItineraryItem $item)
    {
        return $this->item($item->attraction, new AttractionTransformer);
    }
}

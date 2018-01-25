<?php

namespace App\Transformers;

use App\Models\ItineraryItem;

class ItineraryItemTransformer extends TransformerAbstract
{
    protected $defaultIncludes = ['photo', 'attraction'];

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
            'created_at' => strtotime($item->created_at),
            'updated_at' => strtotime($item->updated_at)
        ]);
    }

    public function includephoto(ItineraryItem $item)
    {
        $comment = $item->attraction->comments()->orderBy('upvote', 'desc')->first();
        return $this->item($comment, new AttractionCommentTransformer(['only' => ['images']]));
    }

    public function includeattraction(ItineraryItem $item)
    {
        return $this->item($item->attraction, new AttractionTransformer);
    }
}

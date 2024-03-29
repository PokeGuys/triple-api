<?php

namespace App\Transformers;

use App\Models\Attraction;
use Cache;

class AttractionTransformer extends TransformerAbstract
{
    protected $availableIncludes = ['comments'];

    public function __construct($fields = null)
    {
        $this->fields = $fields;
    }

    public function transform(Attraction $attraction)
    {
        $this->model = $attraction;
        return $this->transformWithField([
            'id' => $attraction->id,
            'name' => $attraction->name,
            'local_name' => $attraction->local_name,
            'phone' => $attraction->phone,
            'website' => $attraction->website,
            'address' => $attraction->address,
            'tags' => $attraction->tags,
            'photos' => $attraction->photos,
            'bestPhoto' => $attraction->photos[0] ?? null,
            'latitude' => $attraction->latitude,
            'longitude' => $attraction->longitude,
            'rating' => $attraction->rating,
            'description' => $attraction->description,
            'opening_hours' => $attraction->opening_hours,
            'popular' => $attraction->popular,
            'comment_count' => $attraction->comment_count,
            'photo_count' => $attraction->photo_count,
            'created_at' => strtotime($attraction->created_at),
            'updated_at' => strtotime($attraction->updated_at)
        ]);
    }

    public function includeComments(Attraction $attraction)
    {
        $comments = Cache::remember("attraction_comment_by_attracion_$attraction->id", 10, function () use ($attraction) {
            return $attraction->comments;
        });
        return $this->collection($comments, new AttractionCommentTransformer);
    }
}

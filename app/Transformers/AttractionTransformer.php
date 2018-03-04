<?php

namespace App\Transformers;

use App\Models\Attraction;

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
            'phone' => $attraction->phone,
            'email' => $attraction->email,
            'website' => $attraction->website,
            'address' => $attraction->address,
            'tags' => $attraction->tags,
            'photos' => $attraction->photos,
            'latitude' => $attraction->latitude,
            'longitude' => $attraction->longitude,
            'rating' => $attraction->rating,
            'comment_count' => $attraction->comment_count,
            'photo_count' => $attraction->photo_count,
            'created_at' => strtotime($attraction->created_at),
            'updated_at' => strtotime($attraction->updated_at)
        ]);
    }

    public function includecomments(Attraction $attraction)
    {
        return $this->collection($attraction->comments, new AttractionCommentTransformer);
    }
}

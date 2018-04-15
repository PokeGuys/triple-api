<?php

namespace App\Transformers;

use App\Models\Tag;

class PreferenceTransformer extends TransformerAbstract
{
    public function __construct($fields = null)
    {
        $this->fields = $fields;
    }

    public function transform(Tag $tag)
    {
        $this->model = $tag;
        return $this->transformWithField([
            'id' => $tag->id,
            "tag" => $tag->tag,
            "image_url" => $tag->image_url,
            "attraction_tags" => $tag->attraction_tags,
            'created_at' => strtotime($tag->created_at),
            'updated_at' => strtotime($tag->updated_at)
        ]);
    }
}

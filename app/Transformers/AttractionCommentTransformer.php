<?php

namespace App\Transformers;

use App\Models\AttractionComment;

class AttractionCommentTransformer extends TransformerAbstract
{
    public function __construct($fields = null)
    {
        $this->fields = $fields;
    }

    public function transform(AttractionComment $comment)
    {
        $this->model = $comment;
        return $this->transformWithField([
            'id' => $comment->id,
            'rating' => $comment->rating,
            'title' => $comment->title,
            'content' => $comment->content,
            'upvote' => $comment->upvote,
            'images' => json_decode($comment->images),
            'created_at' => strtotime($comment->created_at),
            'updated_at' => strtotime($comment->updated_at)
        ]);
    }
}
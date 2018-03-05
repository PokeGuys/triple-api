<?php

namespace App\Transformers;

use App\Models\AttractionComment;
use Cache;

class AttractionCommentTransformer extends TransformerAbstract
{
    protected $defaultIncludes = ['user'];

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

    public function includeUser(AttractionComment $comment)
    {
        $user = Cache::remember("user_by_comment_$comment->id", 60, function () use ($comment) {
            return $comment->user;
        });
        return $this->collection($user, new UserTransformer([
            'only' => [
                'id',
                'username',
                'first_name',
                'last_name'
            ]
        ]));
    }
}
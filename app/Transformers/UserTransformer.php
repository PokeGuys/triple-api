<?php

namespace App\Transformers;

use App\Models\User;
use Cache;

class UserTransformer extends TransformerAbstract
{
    public function __construct($fields = null)
    {
        $this->fields = $fields;
    }

    public function transform(User $user)
    {
        $this->model = $user;
        return $this->transformWithField([
            'id' => $user->id,
            'status' => $user->status,
            'username' => $user->username,
            'email' => $user->email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'age' => $user->age,
            'gender' => $user->gender,
            'income' => $user->income,
            'created_at' => strtotime($user->created_at),
            'updated_at' => strtotime($user->updated_at)
        ]);
    }

    public function includePreferences(User $user)
    {
        $tags = Cache::remember("preference_user_{$user->id}", 60, function() use ($user) {
            return $user->tags;
        });
        return $this->collection($tags, new PreferenceTransformer([
            'only' => [
                'id',
                'tag'
            ]
        ]));
    }
}

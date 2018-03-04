<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    protected $dateFormat = 'U';
    protected $dates = ['created_at', 'updated_at'];
    protected $casts = [
        'attraction_tags' => 'array',
    ];

    public function user()
    {
        return $this->belongsToMany(User::class);
    }
}

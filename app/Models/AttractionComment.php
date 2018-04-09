<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttractionComment extends Model
{
    use SoftDeletes;

    protected $dateFormat = 'U';
    protected $fillable = ['attraction_id', 'user_id', 'rating', 'title', 'content', 'photos'];
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [
        'photos' => 'array',
    ];

    public function attraction()
    {
        return $this->belongsTo(Attraction::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

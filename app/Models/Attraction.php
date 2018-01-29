<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attraction extends Model
{
    use SoftDeletes;

    protected $dateFormat = 'U';
    protected $fillable = ['name', 'phone', 'website', 'email', 'address', 'tags', 'photos', 'latitude', 'longitude', 'rating', 'comment_count', 'photo_count'];
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    public function category()
    {
        return $this->belongsTo(AttractionCategory::class, 'id', 'category');
    }

    public function comments()
    {
        return $this->hasMany(AttractionComment::class);
    }
}
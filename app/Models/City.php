<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class City extends Model
{
    use SoftDeletes;

    protected $dateFormat = 'U';
    protected $fillable = ['name', 'latitude', 'longitude', 'photo_url', 'timezone', 'description', ];
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function attractions()
    {
        return $this->hasMany(Attraction::class);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Trip extends Model
{
    use SoftDeletes;

    protected $dateFormat = 'U';
    protected $fillable = ['status', 'title', 'user_id', 'city_id', 'visit_date', 'visit_length'];
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function collaborators()
    {
        return $this->belongsToMany(User::class, 'trip_collaborators');
    }

    public function itinerary()
    {
        return $this->hasMany(TripItinerary::class);
    }

    public function items()
    {
        return $this->hasManyThrough(ItineraryItem::class, TripItinerary::class);
    }
}

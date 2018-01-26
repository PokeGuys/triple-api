<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItineraryItem extends Model
{
    protected $dateFormat = 'U';
    protected $fillable = ['trip_itinerary_id', 'attraction_id', 'visit_time'];
    protected $dates = ['created_at', 'updated_at'];

    public function trip_itinerary()
    {
        return $this->belongsTo(TripItinerary::Class);
    }

    public function attraction()
    {
        return $this->belongsTo(Attraction::Class);
    }
}

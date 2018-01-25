<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItineraryItem extends Model
{
    protected $dateFormat = 'U';
    protected $fillable = ['itinerary_id', 'attraction_id', 'visit_time', 'visit_length'];
    protected $dates = ['created_at', 'updated_at'];

    public function itinerary()
    {
        return $this->belongsTo(TripItinerary::Class);
    }

    public function attraction()
    {
        return $this->belongsTo(Attraction::Class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripItinerary extends Model
{
    protected $dateFormat = 'U';
    protected $fillable = ['trip_id', 'visit_date'];
    protected $dates = ['created_at', 'updated_at'];

    public function trip()
    {
        return $this->belongsTo(Trip::Class);
    }

    public function items()
    {
        return $this->hasMany(ItineraryItem::Class);
    }
}

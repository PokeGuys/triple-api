<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Trip extends Model
{
    use SoftDeletes;

    protected $dateFormat = 'U';
    protected $fillable = ['status', 'title', 'user_id', 'visit_date', 'visit_length'];
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function collaborators()
    {
        return $this->hasMany(TripCollaborator::class);
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

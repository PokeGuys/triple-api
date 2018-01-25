<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripCollaborator extends Model
{
    protected $dateFormat = 'U';
    protected $dates = ['created_at', 'updated_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }
}

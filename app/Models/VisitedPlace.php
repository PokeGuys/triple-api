<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisitedPlace extends Model
{
    protected $dateFormat = 'U';
    protected $fillable = ['user_id', 'attraction_id'];
    protected $dates = ['created_at', 'updated_at'];

    /**
     * Get the user record associated with the token.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attraction()
    {
        return $this->belongsTo(Attraction::class);
    }
}

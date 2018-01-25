<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgeGroup extends Model
{
    protected $dateFormat = 'U';
    protected $fillable = ['description'];

    public function users()
    {
        return $this->hasMany(User::class, 'age');
    }
}
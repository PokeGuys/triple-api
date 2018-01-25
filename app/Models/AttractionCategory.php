<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttractionCategory extends Model
{
    use SoftDeletes;

    protected $dateFormat = 'U';
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    public function attractions()
    {
        return $this->hasMany(Attraction::class, 'category');
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryDuration extends Model
{
    protected $dateFormat = 'U';
    protected $dates = ['created_at', 'updated_at'];
    protected $fillable = ['category', 'duration'];

}

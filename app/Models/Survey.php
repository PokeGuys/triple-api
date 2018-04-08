<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Survey extends Model
{
    protected $fillable = ['gender', 'age_group', 'income_group', 'occupation', 'destination',
                           'must_see', 'cuisine', 'adventure', 'entertainment', 'history',
                           'shopping'];
}

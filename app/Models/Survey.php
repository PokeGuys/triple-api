<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Survey extends Model
{
    protected $fillable = ['gender',
                           'age_group',
                           'income_group',
                           'nation',
                           'education',
                           'destination',
                           'family_holiday_maker',
                           'foodie',
                           'backpacker',
                           'history_buff',
                           'nightlife_seeker',
                           'eco_tourist',
                           'trendsetter',
                           'nature_lover',
                           'urban_explorer',
                           'thrill_seeker',
                           'beach_goer',
                           'sixtyPlus_traveller',
                           'like_a_local',
                           'luxury_traveller',
                           'vegetarian',
                           'shopping_fanatic',
                           'thrifty_traveller',
                           'art_and_architecture_lover',
                           'peace_and_quiet_seeker'
                         ];
}

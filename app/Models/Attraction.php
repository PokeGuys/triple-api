<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Attraction extends Model
{
    use SoftDeletes;

    protected $dateFormat = 'U';
    protected $fillable = ['description', 'place_id', 'city_id', 'name', 'phone', 'website', 'email', 'opening_hours', 'popular', 'address', 'tags', 'photos', 'latitude', 'longitude', 'rating', 'price_level', 'comment_count', 'photo_count'];
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [
        'tags' => 'array',
        'photos' => 'array',
        'opening_hours' => 'object',
        'popular' => 'object'
    ];

    public function comments()
    {
        return $this->hasMany(AttractionComment::class);
    }

    // TripHelperV1
    // public function openTime($time)
    // {
    //     $dayOfWeek = $time->dayOfWeek;
    //     $openTime = null;
    //     if (!empty($this->opening_hours)) {
    //         if (sizeof($this->opening_hours) === 7) {
    //             $openTime = $this->opening_hours[$dayOfWeek];
    //         } else {
    //             foreach ($this->opening_hours as $openHour) {
    //                 if ($openHour['open']['day'] == $dayOfWeek || (!isset($openHour['close']) && $openHour['open']['day'] <= $dayOfWeek)) {
    //                     $openTime = $openHour;
    //                 }
    //             }
    //         }
    //         if ($openTime != null) {
    //             $openAt = Carbon::createFromFormat('Hi', $openTime['open']['time']);
    //             $openAt = $time->copy()->setTime($openAt->hour, $openAt->minute);
    //             if (isset($openTime['close'])) {
    //                 $closeAt = Carbon::createFromFormat('Hi', $openTime['close']['time']);
    //                 $closeAt = $time->copy()->setTime($closeAt->hour, $closeAt->minute);
    //                 if ($openTime['close']['day'] != $openTime['open']['day']) {
    //                     $closeAt->addDay();
    //                 }
    //             }
    //             return [$openAt, $closeAt ?? null];
    //         }
    //     }
    //     return false;
    // }

    // TripHelperV2
    public function openingHour($currentTime)
    {
        if (!isset($this->opening_hours->timeframes)) {
            return null;
        }
        $dayOfWeek = $currentTime->dayOfWeek == 0 ? 7 : $currentTime->dayOfWeek;
        foreach ($this->opening_hours->timeframes as $time) {
            if (in_array($dayOfWeek, $time->days)) {
                foreach ($time->open as $period) {
                    $startAt = Carbon::createFromFormat('Hi', $period->start);
                    $startAt = $currentTime->copy()->setTime($startAt->hour, $startAt->minute);
                    $nextDay = strpos($period->end, '+') !== false;
                    $endAt = Carbon::createFromFormat($nextDay ? '\+Hi' : 'Hi', $period->end);
                    $endAt = $currentTime->copy()->setTime($endAt->hour, $endAt->minute);
                    if ($nextDay) {
                        $endAt->addDay();
                    }
                    if ($currentTime->between($startAt, $endAt)) {
                        return (object) ['openAt' => $startAt, 'closeAt' => $endAt];
                    }
                }
            }
        }
        return null;
    }

    // TripHelperV2
    public function popularHour($currentTime)
    {
        if (!isset($this->popular->timeframes)) {
            return null;
        }
        $dayOfWeek = $currentTime->dayOfWeek == 0 ? 7 : $currentTime->dayOfWeek;
        foreach ($this->popular->timeframes as $time) {
            if (in_array($dayOfWeek, $time->days)) {
                foreach ($time->open as $period) {
                    $startAt = Carbon::createFromFormat('Hi', $period->start);
                    $startAt = $currentTime->copy()->setTime($startAt->hour, $startAt->minute);
                    $nextDay = strpos($period->end, '+') !== false;
                    $endAt = Carbon::createFromFormat($nextDay ? '\+Hi' : 'Hi', $period->end);
                    $endAt = $currentTime->copy()->setTime($endAt->hour, $endAt->minute);
                    if ($nextDay) {
                        $endAt->addDay();
                    }
                    if ($currentTime->between($startAt, $endAt)) {
                        return (object) ['openAt' => $startAt, 'closeAt' => $endAt];
                    }
                }
            }
        }
        return null;
    }
}

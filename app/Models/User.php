<?php

namespace App\Models;

use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements JWTSubject
{
    use Notifiable, SoftDeletes;

    protected $dateFormat = 'U';
    protected $dates = ['created_at', 'updated_at', 'deleted_at', 'last_login_at'];
    protected $fillable = ['username', 'password', 'email', 'first_name', 'last_name', 'age', 'gender', 'income', 'regip', 'login_count', 'last_login_at'];
    protected $hidden = ['password', 'regip'];

    public function verification()
    {
        return $this->hasOne(Verification::class);
    }

    public function trips()
    {
        return $this->hasMany(Trip::class);
    }

    public function name()
    {
        return $this->first_name . " " . $this->last_name;
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
}

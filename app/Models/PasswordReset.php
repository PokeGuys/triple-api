<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordReset extends Model
{
    protected $dateFormat = 'U';
    protected $fillable = ['user_id', 'token'];
    protected $dates = ['created_at', 'updated_at'];

    /**
     * Get the user record associated with the token.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

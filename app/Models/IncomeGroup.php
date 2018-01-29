<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncomeGroup extends Model
{
    protected $dateFormat = 'U';
    protected $fillable = ['description'];

    public function users()
    {
        return $this->hasMany(User::class, 'age');
    }
}
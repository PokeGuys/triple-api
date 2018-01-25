<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttractionComment extends Model
{
    use SoftDeletes;

    protected $dateFormat = 'U';
    protected $fillable = ['rating', 'title', 'content'];
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    public function attraction()
    {
        return $this->belongsTo(Attraction::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function images()
    {
        return $this->hasMany(AttractionImage::class, 'comment_id', 'id');
    }
}
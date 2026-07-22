<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPoint extends Model
{
    protected $table = 'user_points';

    protected $fillable = [
        'user_id',
        'points',
        'source',
        'description',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

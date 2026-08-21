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

    protected static function booted(): void
    {
        static::saved(function () {
            \App\Http\Controllers\LeaderboardController::clearCache();
        });

        static::deleted(function () {
            \App\Http\Controllers\LeaderboardController::clearCache();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

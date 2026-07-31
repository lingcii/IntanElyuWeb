<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Classification extends Model
{
    protected $table = 'classifications';

    protected $fillable = [
        'name',
        'points',
    ];

    protected $casts = [
        'points' => 'integer',
    ];
}

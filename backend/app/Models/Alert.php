<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'title', 'message', 'type', 'is_active', 'is_read', 'start_at', 'end_at'
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Merchandise extends Model
{
    use HasFactory;

    protected $table = 'merchandises';

    protected $fillable = [
        'title',
        'category',
        'price_xp',
        'image',
        'badge',
        'stock'
    ];

    public function reservations()
    {
        return $this->hasMany(MerchReservation::class, 'merchandise_id');
    }
}

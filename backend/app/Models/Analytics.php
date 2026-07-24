<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Analytics extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'municipality_id', 'tourist_spot_id', 'metric', 'value', 'date',
        'year', 'month', 'visits', 'transport_car', 'transport_bus', 'transport_van', 'transport_other'
    ];
    
    public function municipality()
    {
        return $this->belongsTo(Municipality::class);
    }
    
    public function touristSpot()
    {
        return $this->belongsTo(TouristSpot::class);
    }
}

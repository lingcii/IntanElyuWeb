<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Itinerary extends Model
{
    protected $table = 'itineraries';

    protected $fillable = [
        'user_id',
        'title',
        'trip_date',
        'budget',
        'status',
        'total_cost',
    ];

    protected $casts = [
        'trip_date'  => 'date',
        'budget'     => 'float',
        'total_cost' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(ItineraryItem::class);
    }

    /**
     * Only items the tourist has actually visited with a proof image.
     */
    public function proofItems()
    {
        return $this->hasMany(ItineraryItem::class)
            ->where('is_visited', true)
            ->whereNotNull('proof_image');
    }
}

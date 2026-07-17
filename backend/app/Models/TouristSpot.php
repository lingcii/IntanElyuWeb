<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class TouristSpot extends Model
{
    protected $table = 'tourist_spots';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'municipality_id',
        'barangay',
        'category',
        'entrance_fee',
        'environmental_fee',
        'fee_types',
        'description',
        'photo_url',
        'latitude',
        'longitude',
        'opening_time',
        'closing_time',
        'is_maintenance',
        'status',
        'classification_status',
        'rejection_reason',
        'visits',
        'rating',
        'points',
        'approved_by',
        'approved_at',
        'created_by',
        'creator_role',
    ];

    protected $casts = [
        'entrance_fee'        => 'float',
        'environmental_fee'   => 'float',
        'fee_types'           => 'array',
        'latitude'       => 'float',
        'longitude'      => 'float',
        'is_maintenance' => 'boolean',
        'visits'         => 'integer',
        'rating'         => 'float',
        'points'         => 'integer',
        'approved_at'    => 'datetime',
    ];

    public static array $VALID_CATEGORIES = [
        'Beach', 'Mountain', 'Waterfalls', 'River', 'Lake', 'Island',
        'Cave', 'Volcano', 'Forest', 'Nature Park', 'Marine Sanctuary',
        'Wildlife Sanctuary', 'Historical', 'Cultural Heritage', 'Religious',
        'Museum', 'Monument', 'Landmark', 'Viewpoint', 'Adventure', 'Hiking',
        'Camping', 'Farm', 'Eco-Tourism', 'Garden', 'Park', 'Recreation',
        'Hot Spring', 'Cold Spring', 'Food Destination', 'Shopping',
        'Festival Venue', 'Resort', 'Other'
    ];

    public static array $VALID_STATUSES = ['EXIST', 'POTENTIAL', 'EMERGE'];

    public static array $STATUS_MAP = [
        'EXISTING'  => 'EXIST',
        'EMERGING'  => 'EMERGE',
        'POTENTIAL' => 'POTENTIAL',
        'EXIST'     => 'EXIST',
        'EMERGE'    => 'EMERGE',
    ];

    public function municipality()
    {
        return $this->belongsTo(Municipality::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function images()
    {
        return $this->hasMany(TouristSpotImage::class, 'spot_id')->orderBy('sort_order')->orderBy('id');
    }

    public function audits()
    {
        return $this->hasMany(TouristSpotAudit::class, 'spot_id');
    }
}

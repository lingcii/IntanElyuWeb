<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceCenter extends Model
{
    protected $table = 'service_centers';
    public $timestamps = true;

    protected $fillable = [
        'name',
        'type',
        'custom_type',
        'contact_number',
        'address',
        'description',
        'status',
        'municipality_id',
        'created_by',
    ];

    protected $casts = [
        'municipality_id' => 'integer',
        'created_by'      => 'integer',
    ];

    /**
     * Predefined service center types.
     */
    public static array $TYPES = [
        'Transportation Terminal',
        'Parking Area',
        'Tourist Information Center',
        'Vehicle Rental',
        'Shuttle Service',
        'Transport Service',
        'Other',
    ];

    /**
     * Get the display type label (uses custom_type when type is "Other").
     */
    public function getDisplayTypeAttribute(): string
    {
        if ($this->type === 'Other' && !empty($this->custom_type)) {
            return $this->custom_type;
        }
        return $this->type;
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function municipality()
    {
        return $this->belongsTo(Municipality::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function touristSpots()
    {
        return $this->belongsToMany(
            TouristSpot::class,
            'tourist_spot_service_center',
            'service_center_id',
            'tourist_spot_id'
        );
    }
}

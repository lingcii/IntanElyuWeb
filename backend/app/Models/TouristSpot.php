<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

/**
 * @property \Illuminate\Database\Eloquent\Collection|\App\Models\VehicleType[] $vehicleTypes
 */
class TouristSpot extends Model
{
    protected $table = 'tourist_spots';
    public $timestamps = true;

    protected $fillable = [
        'name',
        'municipality_id',
        'barangay',
        'category',
        'entrance_fee',
        'environmental_fee',
        'fee_types',
        'description',
        'route_guide',
        'tour_guide_notice',
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
        'created_at',
        'updated_at',
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
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
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

    public function feedbacks()
    {
        return $this->hasMany(SiteFeedback::class, 'tourist_spot_id');
    }

    public function vehicleTypes()
    {
        return $this->belongsToMany(VehicleType::class, 'tourist_spot_vehicle_type', 'tourist_spot_id', 'vehicle_type_id');
    }

    public function serviceCenters()
    {
        return $this->belongsToMany(ServiceCenter::class, 'tourist_spot_service_center', 'tourist_spot_id', 'service_center_id');
    }

    public static function getDefaultPointsForClassification(?string $classification): int
    {
        $upper = strtoupper((string)$classification);
        $key = match ($upper) {
            'EMERGING', 'EMERGE' => 'EMERGING',
            'POTENTIAL'          => 'POTENTIAL',
            'EXISTING', 'EXIST'  => 'EXISTING',
            default              => 'EXISTING',
        };

        $pointsMap = \Illuminate\Support\Facades\Cache::remember('classification_points', 3600, function () {
            $map = [
                'EXISTING'  => 50,
                'EMERGING'  => 100,
                'POTENTIAL' => 75,
            ];
            try {
                $records = \App\Models\Classification::all();
                foreach ($records as $record) {
                    $map[strtoupper($record->name)] = (int) $record->points;
                }
            } catch (\Throwable $e) {
                // Fallback to hardcoded defaults if table doesn't exist yet
            }
            return $map;
        });

        return (int) ($pointsMap[$key] ?? 50);
    }

    public function getPointsAttribute($value): int
    {
        $val = (int) $value;
        if ($val > 0) {
            return $val;
        }
        return self::getDefaultPointsForClassification($this->attributes['classification_status'] ?? null);
    }
}

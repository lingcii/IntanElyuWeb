<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleType extends Model
{
    protected $table = 'vehicle_types';

    protected $fillable = [
        'category',
        'name',
    ];

    public static array $LUPTO_ALLOWED = [
        'Public Vehicle'  => ['TAXI', 'UVE', 'PUB_Regular', 'PUB_Aircon', 'MPUJ', 'TPUJ'],
        'Private Vehicle' => ['Car', 'Motorcycle', 'Van', 'Tricycle'],
    ];

    public static array $MUNICIPAL_ALLOWED = [
        'Public Vehicle'  => ['Tricycle'],
        'Private Vehicle' => ['Car', 'Motorcycle', 'Van', 'Tricycle'],
    ];

    public function touristSpots()
    {
        return $this->belongsToMany(TouristSpot::class, 'tourist_spot_vehicle_type', 'vehicle_type_id', 'tourist_spot_id');
    }

    /**
     * Get allowed vehicle types for a given user role ('lupto' vs 'municipal')
     */
    public static function getAllowedForRole(string $role)
    {
        $roleLower = strtolower($role);
        $allowedMap = in_array($roleLower, User::$MUNICIPAL_ROLES ?? ['municipal', 'mto'])
            ? self::$MUNICIPAL_ALLOWED
            : self::$LUPTO_ALLOWED;

        $query = self::query();
        $query->where(function ($q) use ($allowedMap) {
            foreach ($allowedMap as $category => $names) {
                $q->orWhere(function ($sub) use ($category, $names) {
                    $sub->where('category', $category)->whereIn('name', $names);
                });
            }
        });

        return $query->get();
    }

    /**
     * Check if a set of vehicle type IDs are valid and allowed for a specific user role.
     */
    public static function validateIdsForRole(array $ids, string $role): bool
    {
        if (empty($ids)) {
            return true;
        }

        $allowedIds = self::getAllowedForRole($role)->pluck('id')->toArray();
        foreach ($ids as $id) {
            if (!in_array((int)$id, $allowedIds, true)) {
                return false;
            }
        }

        return true;
    }
}

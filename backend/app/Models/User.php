<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'users';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'municipality_id',
        'last_activity',
        'api_token',
        'xp',
        'level',
        'avatar',
        'is_default_password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'api_token',
    ];

    protected $casts = [
        'email_verified_at'   => 'datetime',
        'last_activity'       => 'datetime',
        'created_at'          => 'datetime',
        'is_default_password' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(function ($user) {
            \App\Http\Controllers\LeaderboardController::clearCache();
        });

        static::deleted(function ($user) {
            \App\Http\Controllers\LeaderboardController::clearCache();
        });
    }

    /** Valid roles in the system */
    public static array $ALL_ROLES = [
        'picto', 'lupto', 'municipal', 'tourist',
        'san_juan_mto', 'san_fernando_mto', 'bauang_mto', 'agoo_mto', 'luna_mto',
        'san_gabriel_mto', 'balaoan_mto', 'aringay_mto', 'rosario_mto', 'bacnotan_mto',
        'naguilian_mto', 'tubao_mto', 'pugo_mto', 'caba_mto', 'santo_tomas_mto',
        'bangar_mto', 'burgos_mto', 'bagulin_mto', 'santol_mto', 'sudipen_mto',
    ];

    /** Municipal (MTO) roles */
    public static array $MUNICIPAL_ROLES = [
        'san_juan_mto', 'san_fernando_mto', 'bauang_mto', 'agoo_mto', 'luna_mto',
        'san_gabriel_mto', 'balaoan_mto', 'aringay_mto', 'rosario_mto', 'bacnotan_mto',
        'naguilian_mto', 'tubao_mto', 'pugo_mto', 'caba_mto', 'santo_tomas_mto',
        'bangar_mto', 'burgos_mto', 'bagulin_mto', 'santol_mto', 'sudipen_mto', 'municipal',
    ];

    public function municipality()
    {
        return $this->belongsTo(Municipality::class);
    }

    public function getRoleAttribute($value)
    {
        return $value === 'pitco' ? 'picto' : $value;
    }

    public function setRoleAttribute($value)
    {
        $this->attributes['role'] = $value === 'picto' ? 'pitco' : $value;
    }

    public function isMunicipal(): bool
    {
        return in_array($this->role, self::$MUNICIPAL_ROLES);
    }

    public function itineraries()
    {
        return $this->hasMany(Itinerary::class);
    }

    public function feedbacks()
    {
        return $this->hasMany(SiteFeedback::class);
    }

    public function vouchers()
    {
        return $this->hasMany(Voucher::class, 'created_by');
    }

    public function voucherRedemptions()
    {
        return $this->hasMany(VoucherRedemption::class, 'user_id');
    }

    public function userPoint()
    {
        return $this->hasOne(UserPoint::class, 'user_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Voucher extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'vouchers';

    protected $fillable = [
        'voucher_code',
        'voucher_name',
        'description',
        'image',
        'discount_type',
        'discount_value',
        'required_points',
        'available_quantity',
        'redeemed_quantity',
        'remaining_quantity',
        'municipality_id',
        'partner_establishment',
        'maximum_redemption_per_user',
        'valid_from',
        'expires_at',
        'terms_and_conditions',
        'status',
        'created_by',
    ];

    protected $casts = [
        'discount_value' => 'float',
        'required_points' => 'integer',
        'available_quantity' => 'integer',
        'redeemed_quantity' => 'integer',
        'remaining_quantity' => 'integer',
        'maximum_redemption_per_user' => 'integer',
        'valid_from' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['status_badge', 'is_expired'];

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function municipality()
    {
        return $this->belongsTo(Municipality::class, 'municipality_id');
    }

    public function redemptions()
    {
        return $this->hasMany(VoucherRedemption::class, 'voucher_id');
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at ? now()->greaterThan($this->expires_at) : false;
    }

    public function getStatusBadgeAttribute(): string
    {
        if ($this->status === 'archived') {
            return 'purple';
        }
        if ($this->is_expired) {
            return 'red';
        }
        if ($this->status === 'inactive') {
            return 'gray';
        }
        if ($this->remaining_quantity <= 5) {
            return 'orange';
        }
        return 'green';
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('valid_from', '<=', now())
            ->where('expires_at', '>=', now())
            ->where('remaining_quantity', '>', 0);
    }
}

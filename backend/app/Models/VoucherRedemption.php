<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VoucherRedemption extends Model
{
    use HasFactory;

    protected $table = 'voucher_redemptions';

    protected $fillable = [
        'voucher_id',
        'user_id',
        'redemption_code',
        'points_used',
        'status',
        'redeemed_at',
        'claimed_at',
    ];

    protected $casts = [
        'points_used' => 'integer',
        'redeemed_at' => 'datetime',
        'claimed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function voucher()
    {
        return $this->belongsTo(Voucher::class, 'voucher_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

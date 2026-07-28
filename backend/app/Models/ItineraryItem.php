<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItineraryItem extends Model
{
    protected $table = 'itinerary_items';

    protected $fillable = [
        'itinerary_id',
        'tourist_spot_id',
        'is_visited',
        'proof_image',
        'visited_at',
        'proof_status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected $casts = [
        'is_visited'  => 'boolean',
        'visited_at'  => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    /** Valid proof validation statuses */
    public static array $VALID_STATUSES = ['pending', 'under_review', 'approved', 'rejected'];

    public function itinerary()
    {
        return $this->belongsTo(Itinerary::class);
    }

    public function touristSpot()
    {
        return $this->belongsTo(TouristSpot::class);
    }

    /** The MTO user who reviewed this proof image */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    /** Only items that have a proof image submitted */
    public function scopeWithProof($query)
    {
        return $query->where('is_visited', true)->whereNotNull('proof_image');
    }

    public function scopePending($query)
    {
        return $query->withProof()->where('proof_status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->withProof()->where('proof_status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->withProof()->where('proof_status', 'rejected');
    }
}

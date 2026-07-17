<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FareGuide extends Model
{
    protected $fillable = [
        'title', 'vehicle_type', 'region', 'effective_date', 
        'status', 'created_by'
    ];
    
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    public function archivedBy()
    {
        return $this->belongsTo(User::class, 'archived_by');
    }
    
    public function matrices()
    {
        return $this->hasMany(FareMatrix::class);
    }
}

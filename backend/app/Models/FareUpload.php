<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FareUpload extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'file_name', 'file_path', 'file_size', 'mime_type', 'file_type',
        'uploaded_by', 'status', 'total_records', 'valid_records',
        'invalid_records', 'fare_guide_id'
    ];
    
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
    
    public function guide()
    {
        return $this->belongsTo(FareGuide::class, 'fare_guide_id');
    }
    
    public function importLogs()
    {
        return $this->hasMany(ImportLog::class);
    }
    
    public function validationErrors()
    {
        return $this->hasMany(ValidationError::class);
    }
}

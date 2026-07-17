<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'fare_upload_id', 'action', 'message', 'details', 'severity'
    ];
    
    public function upload()
    {
        return $this->belongsTo(FareUpload::class, 'fare_upload_id');
    }
}

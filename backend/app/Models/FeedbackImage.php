<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedbackImage extends Model
{
    protected $table = 'feedback_images';

    protected $fillable = [
        'feedback_id',
        'image_path',
    ];

    public function feedback()
    {
        return $this->belongsTo(SiteFeedback::class, 'feedback_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteFeedback extends Model
{
    protected $table = 'site_feedbacks';

    protected $fillable = [
        'user_id',
        'tourist_spot_id',
        'rating',
        'testimony',
        'policy_recommendation',
        'crowd_level',
        'cleanliness_level',
        'safety_level',
    ];

    protected $appends = ['comment'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function touristSpot()
    {
        return $this->belongsTo(TouristSpot::class, 'tourist_spot_id');
    }

    public function images()
    {
        return $this->hasMany(FeedbackImage::class, 'feedback_id');
    }

    public function getCommentAttribute()
    {
        return $this->testimony;
    }
}

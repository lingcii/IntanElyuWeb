<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = 'notifications';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'is_read',
        'module',
        'action_url',
        'spot_name',
        'municipality_name',
        'actor_name',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'created_at' => 'datetime',
    ];

    protected $appends = ['type_icon', 'type_color'];

    public function getTypeIconAttribute(): string
    {
        return self::typeIcons()[$this->type] ?? 'fa-bell';
    }

    public function getTypeColorAttribute(): string
    {
        return self::typeColors()[$this->type] ?? 'gray';
    }

    public static function typeIcons(): array
    {
        return [
            'tourist_spot_added'     => 'fa-map-marker-alt',
            'tourist_spot_updated'   => 'fa-edit',
            'tourist_spot_submitted' => 'fa-paper-plane',
            'tourist_spot_approved'  => 'fa-check-circle',
            'tourist_spot_rejected'  => 'fa-times-circle',
            'tourist_spot_archived'  => 'fa-archive',
            'tourist_spot_restored'  => 'fa-rotate-left',
            'tourist_spot_deleted'   => 'fa-trash',
            'user_created'           => 'fa-user-plus',
            'user_updated'           => 'fa-user-edit',
            'user_deleted'           => 'fa-user-slash',
            'user_archived'          => 'fa-folder',
            'user_restored'          => 'fa-user-check',
            'password_reset'         => 'fa-key',
            'user_login'             => 'fa-sign-in-alt',
            'user_logout'            => 'fa-sign-out-alt',
            'municipality_assigned'  => 'fa-building',
            'municipality_updated'   => 'fa-city',
            'spot_approved'          => 'fa-check-circle',
            'spot_rejected'          => 'fa-times-circle',
            'system_settings'        => 'fa-cog',
            'system_backup'          => 'fa-database',
            'system_restore'         => 'fa-rotate-left',
        ];
    }

    public static function typeColors(): array
    {
        return [
            'tourist_spot_added'     => 'blue',
            'tourist_spot_updated'   => 'blue',
            'tourist_spot_submitted' => 'yellow',
            'tourist_spot_approved'  => 'green',
            'tourist_spot_rejected'  => 'red',
            'tourist_spot_archived'  => 'orange',
            'tourist_spot_restored'  => 'green',
            'tourist_spot_deleted'   => 'red',
            'user_created'           => 'blue',
            'user_updated'           => 'blue',
            'user_deleted'           => 'red',
            'user_archived'          => 'orange',
            'user_restored'          => 'green',
            'password_reset'         => 'blue',
            'user_login'             => 'gray',
            'user_logout'            => 'gray',
            'municipality_assigned'  => 'purple',
            'municipality_updated'   => 'purple',
            'spot_approved'          => 'green',
            'spot_rejected'          => 'red',
            'system_settings'        => 'purple',
            'system_backup'          => 'purple',
            'system_restore'         => 'purple',
        ];
    }

    public static function createNotification(array $data): self
    {
        return self::create($data);
    }
}

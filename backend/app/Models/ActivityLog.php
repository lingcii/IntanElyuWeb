<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    protected $fillable = [
        'user_id',
        'action',
        'details',
        'ip_address',
        'module',
        'description',
        'user_name',
        'user_role',
        'municipality',
        'user_agent',
        'device',
        'browser',
        'os',
        'old_value',
        'new_value',
    ];

    protected $casts = [
        'old_value' => 'array',
        'new_value' => 'array',
    ];

    protected $appends = ['action_icon', 'action_color'];

    protected $with = ['user:id,name,email,role,avatar,municipality_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeLast24h($query)
    {
        return $query->where('created_at', '>=', now()->subHours(24));
    }

    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    public function scopeByModule($query, $module)
    {
        return $query->where('module', $module);
    }

    public function scopeByRole($query, $role)
    {
        return $query->where('user_role', $role);
    }

    public function scopeByMunicipality($query, $municipality)
    {
        return $query->where('municipality', $municipality);
    }

    public function scopeSearch($query, $term)
    {
        $term = '%' . $term . '%';
        return $query->where(function ($q) use ($term) {
            $q->where('action', 'like', $term)
              ->orWhere('module', 'like', $term)
              ->orWhere('description', 'like', $term)
              ->orWhere('user_name', 'like', $term)
              ->orWhere('municipality', 'like', $term);
        });
    }

    public function getActionIconAttribute(): string
    {
        return $this->iconMap()[$this->action] ?? 'fa-info-circle';
    }

    public function getActionColorAttribute(): string
    {
        return $this->colorMap()[$this->action] ?? 'gray';
    }

    public static function iconMap(): array
    {
        return [
            'User Logged In'         => 'fa-sign-in-alt',
            'User Logged Out'        => 'fa-sign-out-alt',
            'User Created'           => 'fa-user-plus',
            'User Updated'           => 'fa-user-edit',
            'User Deleted'           => 'fa-user-slash',
            'User Restored'          => 'fa-user-check',
            'User Archived'          => 'fa-folder',
            'User Activated'         => 'fa-toggle-on',
            'User Deactivated'       => 'fa-toggle-off',
            'Password Reset'         => 'fa-key',
            'Tourist Spot Added'     => 'fa-map-marker-alt',
            'Tourist Spot Updated'   => 'fa-edit',
            'Tourist Spot Deleted'   => 'fa-trash',
            'Tourist Spot Approved'  => 'fa-check-circle',
            'Tourist Spot Rejected'  => 'fa-times-circle',
            'Fare Data Uploaded'     => 'fa-upload',
            'Fare Data Updated'      => 'fa-bus',
            'Fare Data Deleted'      => 'fa-trash-alt',
            'System Settings Updated' => 'fa-cog',
            'Profile Updated'        => 'fa-user-circle',
            'Password Changed'       => 'fa-lock',
            'Data Imported'          => 'fa-file-import',
            'Data Exported'          => 'fa-file-export',
            'Database Backup Created' => 'fa-database',
            'Database Restored'       => 'fa-undo-alt',
            'Backup Deleted'          => 'fa-trash-alt',
        ];
    }

    public static function colorMap(): array
    {
        return [
            'User Logged In'         => 'gray',
            'User Logged Out'        => 'gray',
            'User Created'           => 'green',
            'User Updated'           => 'blue',
            'User Deleted'           => 'red',
            'User Restored'          => 'green',
            'User Archived'          => 'orange',
            'User Activated'         => 'green',
            'User Deactivated'       => 'orange',
            'Password Reset'         => 'blue',
            'Tourist Spot Added'     => 'green',
            'Tourist Spot Updated'   => 'blue',
            'Tourist Spot Deleted'   => 'red',
            'Tourist Spot Approved'  => 'green',
            'Tourist Spot Rejected'  => 'red',
            'Fare Data Uploaded'     => 'green',
            'Fare Data Updated'      => 'blue',
            'Fare Data Deleted'      => 'red',
            'System Settings Updated' => 'purple',
            'Profile Updated'        => 'blue',
            'Password Changed'       => 'blue',
            'Data Imported'          => 'purple',
            'Data Exported'          => 'purple',
            'Database Backup Created' => 'blue',
            'Database Restored'       => 'orange',
            'Backup Deleted'          => 'red',
        ];
    }
}

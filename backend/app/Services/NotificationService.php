<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    /**
     * Create a notification for a specific user.
     * Silently fails if the notifications table does not exist.
     */
    public static function notify(int $userId, string $type, string $title, string $message, array $data = []): ?Notification
    {
        try {
            return Notification::create([
                'user_id'           => $userId,
                'type'              => $type,
                'title'             => $title,
                'message'           => $message,
                'data'              => $data,
                'is_read'           => false,
                'module'            => $data['module'] ?? null,
                'action_url'        => $data['action_url'] ?? null,
                'spot_name'         => $data['spot_name'] ?? null,
                'municipality_name' => $data['municipality_name'] ?? null,
                'actor_name'        => $data['actor_name'] ?? null,
            ]);
        } catch (\Exception $e) {
            \Log::warning('NotificationService::notify failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Notify all users with specified roles.
     */
    public static function notifyRoles(array $roles, string $type, string $title, string $message, array $data = []): void
    {
        try {
            $users = User::whereIn('role', $roles)->where('status', 'active')->get();
            foreach ($users as $user) {
                self::notify($user->id, $type, $title, $message, $data);
            }
        } catch (\Exception $e) {
            \Log::warning('NotificationService::notifyRoles failed: ' . $e->getMessage());
        }
    }

    /**
     * Notify all municipal users of a specific municipality.
     */
    public static function notifyMunicipality(int $municipalityId, string $type, string $title, string $message, array $data = []): void
    {
        try {
            $users = User::where('municipality_id', $municipalityId)
                ->where('status', 'active')
                ->where(function ($q) {
                    $q->where('role', 'municipal')
                      ->orWhere('role', 'like', '%_mto');
                })
                ->get();

            foreach ($users as $user) {
                self::notify($user->id, $type, $title, $message, $data);
            }
        } catch (\Exception $e) {
            \Log::warning('NotificationService::notifyMunicipality failed: ' . $e->getMessage());
        }
    }

    /**
     * Notify all LUPTO users (provincial tourism office).
     */
    public static function notifyLupto(string $type, string $title, string $message, array $data = []): void
    {
        self::notifyRoles(['lupto'], $type, $title, $message, $data);
    }

    /**
     * Notify all PICTO users (provincial ICT office).
     */
    public static function notifyPicto(string $type, string $title, string $message, array $data = []): void
    {
        self::notifyRoles(['picto', 'pitco'], $type, $title, $message, $data);
    }

    /**
     * Notify all provincial admins (PICTO + LUPTO).
     */
    public static function notifyProvincial(string $type, string $title, string $message, array $data = []): void
    {
        self::notifyRoles(['picto', 'pitco', 'lupto'], $type, $title, $message, $data);
    }
}

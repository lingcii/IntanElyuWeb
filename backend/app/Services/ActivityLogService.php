<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class ActivityLogService
{
    /**
     * Create a standardized activity log entry.
     */
    public static function log(
        string $action,
        string $module,
        string $description,
        ?array $oldValue = null,
        ?array $newValue = null,
        ?Request $request = null
    ): ActivityLog {
        $userId = Session::get('user_id');
        $userName = Session::get('user_name');
        $userRole = Session::get('user_role');
        $municipalityId = Session::get('user_municipality_id');

        $municipality = self::resolveMunicipalityName($municipalityId);

        $ipAddress = $request ? $request->ip() : null;
        $userAgent = $request ? $request->userAgent() : null;
        $uaParsed = $userAgent ? self::parseUserAgent($userAgent) : [
            'device' => null, 'browser' => null, 'os' => null,
        ];

        $detailsJson = json_encode([
            'description' => $description,
            'module' => $module,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'device_browser' => $userAgent,
        ]);

        return ActivityLog::create([
            'user_id'      => $userId,
            'action'       => $action,
            'details'      => $detailsJson,
            'ip_address'   => $ipAddress,
            'module'       => $module,
            'description'  => $description,
            'user_name'    => $userName,
            'user_role'    => $userRole,
            'municipality' => $municipality,
            'user_agent'   => $userAgent,
            'device'       => $uaParsed['device'],
            'browser'      => $uaParsed['browser'],
            'os'           => $uaParsed['os'],
            'old_value'    => $oldValue,
            'new_value'    => $newValue,
        ]);
    }

    /**
     * Resolve municipality name from ID stored in session.
     */
    private static function resolveMunicipalityName($municipalityId): ?string
    {
        if (!$municipalityId) {
            return null;
        }

        return cache()->remember("municipality_name_{$municipalityId}", 3600, function () use ($municipalityId) {
            $municipality = \App\Models\Municipality::find($municipalityId);
            return $municipality ? $municipality->name : null;
        });
    }

    /**
     * Parse a raw User-Agent string into device, browser, and OS.
     */
    public static function parseUserAgent(string $ua): array
    {
        $device = 'Desktop';
        $browser = 'Unknown';
        $os = 'Unknown';

        // Device detection
        if (stripos($ua, 'mobile') !== false || stripos($ua, 'android') !== false && stripos($ua, 'tablet') === false) {
            $device = 'Mobile';
        } elseif (stripos($ua, 'tablet') !== false || stripos($ua, 'ipad') !== false) {
            $device = 'Tablet';
        }

        // Browser detection
        if (stripos($ua, 'edg') !== false) {
            $browser = 'Edge';
        } elseif (stripos($ua, 'chrome') !== false) {
            $browser = 'Chrome';
        } elseif (stripos($ua, 'firefox') !== false) {
            $browser = 'Firefox';
        } elseif (stripos($ua, 'safari') !== false) {
            $browser = 'Safari';
        } elseif (stripos($ua, 'opera') !== false || stripos($ua, 'opr') !== false) {
            $browser = 'Opera';
        }

        // OS detection
        if (stripos($ua, 'windows') !== false) {
            $os = 'Windows';
        } elseif (stripos($ua, 'macintosh') !== false || stripos($ua, 'mac os') !== false) {
            $os = 'macOS';
        } elseif (stripos($ua, 'linux') !== false && stripos($ua, 'android') === false) {
            $os = 'Linux';
        } elseif (stripos($ua, 'android') !== false) {
            $os = 'Android';
        } elseif (stripos($ua, 'iphone') !== false || stripos($ua, 'ipad') !== false || stripos($ua, 'ipod') !== false) {
            $os = 'iOS';
        }

        return compact('device', 'browser', 'os');
    }
}

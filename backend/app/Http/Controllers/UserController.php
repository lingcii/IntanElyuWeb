<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Models\Alert;
use App\Models\Municipality;
use App\Models\Notification;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    // ──────────────────────────────────────────────────────────────────────────
    //  GET
    // ──────────────────────────────────────────────────────────────────────────

    /** GET /api/{role}/users */
    public function index(Request $request): JsonResponse
    {
        $search       = $request->get('search', '');
        $roleFilter   = $request->get('role', '');
        $statusFilter = $request->get('status', '');
        $limit        = min(max((int) $request->get('limit', 25), 1), 100);
        $offset       = max((int) $request->get('offset', 0), 0);
        $sortCol      = in_array($request->get('sort'), ['id','name','email','role','status','last_activity','created_at'])
                            ? $request->get('sort') : 'created_at';
        $sortDir      = strtoupper($request->get('dir', 'DESC')) === 'ASC' ? 'asc' : 'desc';

        $sessionRole = $request->session()->get('user_role');
        $isLuptoFilter = ($sessionRole === 'lupto' || $request->is('api/lupto/*'));

        $query = User::select('id', 'name', 'email', 'role', 'status', 'municipality_id', 'created_at', 'last_activity')
            ->with('municipality:id,name');

        if ($isLuptoFilter) {
            $query->whereIn('role', User::$MUNICIPAL_ROLES);
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            if (is_numeric($search)) {
                $query->where('id', (int) $search);
            } else {
                $query->where(fn($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            }
        }
        if ($request->filled('role')) {
            $roleVal = $request->get('role');
            if ($isLuptoFilter) {
                if (in_array($roleVal, User::$MUNICIPAL_ROLES)) {
                    $query->where('role', $roleVal);
                }
            } else {
                $query->where('role', $roleVal);
            }
        }
        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        } else {
            $query->where('status', '!=', 'archived');
        }

        $total = $query->count();
        $users = $query->orderBy($sortCol, $sortDir)->skip($offset)->take($limit)->get();

        $statsCacheKey = $isLuptoFilter ? 'users:stats:lupto' : 'users:stats:all';
        $stats = Cache::remember($statsCacheKey, 300, function () use ($isLuptoFilter) {
            $sevenDaysAgo = now()->subDays(7);
            $dbQuery = DB::table('users');
            if ($isLuptoFilter) {
                $dbQuery->whereIn('role', User::$MUNICIPAL_ROLES);
            }
            $userStats = $dbQuery
                ->selectRaw("
                    COUNT(CASE WHEN status != 'archived' THEN 1 END) as total_users,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_users,
                    COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_users,
                    COUNT(CASE WHEN status = 'archived' THEN 1 END) as archived_users,
                    COUNT(CASE WHEN role IN ('picto', 'pitco') AND status != 'archived' THEN 1 END) as super_admins,
                    COUNT(CASE WHEN role = 'lupto' AND status != 'archived' THEN 1 END) as lupto_users,
                    COUNT(CASE WHEN (role LIKE '%_mto' OR role = 'municipal') AND status != 'archived' THEN 1 END) as municipal_users,
                    COUNT(CASE WHEN role = 'tourist' AND status != 'archived' THEN 1 END) as tourist_users,
                    COUNT(CASE WHEN created_at >= ? AND status != 'archived' THEN 1 END) as recently_added
                ", [$sevenDaysAgo])
                ->first();

            return [
                'total_users'     => (int) ($userStats->total_users ?? 0),
                'active_users'    => (int) ($userStats->active_users ?? 0),
                'inactive_users'  => (int) ($userStats->inactive_users ?? 0),
                'archived_users'  => (int) ($userStats->archived_users ?? 0),
                'super_admins'    => (int) ($userStats->super_admins ?? 0),
                'picto_users'     => (int) ($userStats->super_admins ?? 0),
                'lupto_users'     => (int) ($userStats->lupto_users ?? 0),
                'municipal_users' => (int) ($userStats->municipal_users ?? 0),
                'tourist_users'   => (int) ($userStats->tourist_users ?? 0),
                'recently_added'  => (int) ($userStats->recently_added ?? 0)
            ];
        });

        $roleStatsCacheKey = $isLuptoFilter ? 'users:role_stats:lupto' : 'users:role_stats:all';
        $roleStats = Cache::remember($roleStatsCacheKey, 300, function () use ($isLuptoFilter) {
            $dbQuery = User::selectRaw("role, COUNT(*) as cnt, SUM(status='active') as active_cnt")
                ->where('status', '!=', 'archived');
            if ($isLuptoFilter) {
                $dbQuery->whereIn('role', User::$MUNICIPAL_ROLES);
            }
            return $dbQuery->groupBy('role')->get()->toArray();
        });

        return $this->etagResponse($request, [
            'success'    => true,
            'users'      => $users,
            'total'      => $total,
            'offset'     => $offset,
            'limit'      => $limit,
            'role_stats' => $roleStats,
            'stats'      => $stats,
        ]);
    }

    /** GET /api/{role}/users/{id} */
    public function show(int $id): JsonResponse
    {
        $user = User::with('municipality:id,name')->find($id);
        if (!$user) return response()->json(['error' => 'User not found.'], 404);

        $sessionRole = request()->session()->get('user_role');
        if (($sessionRole === 'lupto' || request()->is('api/lupto/*')) && !in_array($user->role, User::$MUNICIPAL_ROLES)) {
            return response()->json(['error' => 'Unauthorized access to this user account.'], 403);
        }

        return response()->json(['success' => true, 'user' => $user]);
    }

    /** GET /api/{role}/users/municipalities */
    public function municipalities(): JsonResponse
    {
        // Cache for 1 hour (not forever) so municipality additions don't go stale
        $municipalities = Cache::remember('municipalities:list', 3600, function () {
            return Municipality::orderBy('name')->get(['id', 'name']);
        });
        return response()->json(['success' => true, 'municipalities' => $municipalities]);
    }

    /** GET /api/{role}/users/audit-logs */
    public function auditLogs(): JsonResponse
    {
        $logs = Alert::where('type', 'user_action')
            ->latest()
            ->take(50)
            ->get(['id', 'message', 'created_at']);

        return response()->json(['success' => true, 'logs' => $logs]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  POST / PUT
    // ──────────────────────────────────────────────────────────────────────────

    /** POST /api/{role}/users  – create user (PITCO) */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'            => 'required|string|max:255',
            'email'           => 'required|email|unique:users,email',
            'role'            => ['required', Rule::in(User::$ALL_ROLES)],
            'status'          => 'nullable|in:active,inactive,pending',
            'municipality_id' => 'nullable|integer',
            'password'        => 'required|string|min:6',
        ]);

        $sessionRole = $request->session()->get('user_role');
        $isLupto = ($sessionRole === 'lupto' || $request->is('api/lupto/*'));

        if ($isLupto && !in_array($request->role, User::$MUNICIPAL_ROLES)) {
            return response()->json(['error' => 'Forbidden: LUPTO accounts can only create Municipal users.'], 403);
        }

        $status = $request->get('status', 'active');
        $isDefaultPassword = true;

        $user = User::create([
            'name'                => $request->name,
            'email'               => $request->email,
            'password'            => Hash::make($request->password),
            'role'                => $request->role,
            'status'              => $status,
            'municipality_id'     => $request->municipality_id,
            'is_default_password' => $isDefaultPassword,
        ]);

        $user->load('municipality:id,name');

        $this->writeAuditLog($request, 'ADD_USER', $user->id, "Name: {$user->name} | Email: {$user->email} | Role: {$user->role}");

        $muniName = $user->municipality?->name;
        NotificationService::notifyProvincial(
            'user_created',
            'New User Created',
            "{$user->name} created a new " . ucfirst($user->role) . " account" . ($muniName ? " for {$muniName}" : ''),
            [
                'module'            => 'Users',
                'action_url'        => 'user-management.php',
                'municipality_name' => $muniName,
                'actor_name'        => $request->session()->get('user_name'),
            ]
        );

        ActivityLogService::log(
            ActivityAction::USER_CREATED,
            'Users',
            "New user \"{$user->name}\" created ({$user->role})",
            null,
            ['name' => $user->name, 'email' => $user->email, 'role' => $user->role],
            $request
        );

        Cache::forget('users:role_stats');
        Cache::forget('users:stats');
        Cache::forget('activity_stats');

        return response()->json(['success' => true, 'user' => $user, 'message' => 'User created successfully.'], 201);
    }

    /** PUT /api/{role}/users/{id}  – update user */
    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'name'            => 'required|string|max:255',
            'email'           => ['required', 'email', Rule::unique('users', 'email')->ignore($id)],
            'role'            => ['required', Rule::in(User::$ALL_ROLES)],
            'status'          => 'nullable|in:active,inactive,pending',
            'municipality_id' => 'nullable|integer',
        ]);

        $user = User::findOrFail($id);
        $sessionRole = $request->session()->get('user_role');
        if (($sessionRole === 'lupto' || $request->is('api/lupto/*')) && !in_array($user->role, User::$MUNICIPAL_ROLES)) {
            return response()->json(['error' => 'Unauthorized action on this user account.'], 403);
        }

        $user->update([
            'name'            => $request->name,
            'email'           => $request->email,
            'role'            => $request->role,
            'status'          => $request->get('status', 'active'),
            'municipality_id' => $request->municipality_id,
        ]);

        $user->load('municipality:id,name');

        $this->writeAuditLog($request, 'EDIT_USER', $id, "Name: {$request->name} | Role: {$request->role}");

        ActivityLogService::log(
            ActivityAction::USER_UPDATED,
            'Users',
            "User \"{$user->name}\" updated",
            ['name' => $user->getOriginal('name'), 'role' => $user->getOriginal('role')],
            ['name' => $request->name, 'role' => $request->role],
            $request
        );

        Cache::forget('users:role_stats');
        Cache::forget('users:stats');
        Cache::forget('activity_stats');

        return response()->json(['success' => true, 'user' => $user, 'message' => 'User updated successfully.']);
    }

    /** PATCH /api/{role}/users/{id}/status */
    public function toggleStatus(Request $request, int $id): JsonResponse
    {
        $request->validate(['status' => 'required|in:active,inactive']);

        $user = User::findOrFail($id);
        $sessionRole = $request->session()->get('user_role');
        if (($sessionRole === 'lupto' || $request->is('api/lupto/*')) && !in_array($user->role, User::$MUNICIPAL_ROLES)) {
            return response()->json(['error' => 'Unauthorized action on this user account.'], 403);
        }

        $user->update(['status' => $request->status]);

        $verb = $request->status === 'active' ? 'ACTIVATE_USER' : 'DEACTIVATE_USER';
        $this->writeAuditLog($request, $verb, $id, "Status set to {$request->status}");

        $actionKey = $request->status === 'active' ? ActivityAction::USER_ACTIVATED : ActivityAction::USER_DEACTIVATED;
        ActivityLogService::log(
            $actionKey,
            'Users',
            "User #{$id} {$request->status}",
            ['status' => $request->status === 'active' ? 'inactive' : 'active'],
            ['status' => $request->status],
            $request
        );

        Cache::forget('users:role_stats');
        Cache::forget('users:stats');
        Cache::forget('activity_stats');

        return response()->json(['success' => true, 'user' => User::select('id','status','role')->find($id), 'message' => 'Account status updated.']);
    }

    /** PATCH /api/{role}/users/{id}/password */
    public function resetPassword(Request $request, int $id): JsonResponse
    {
        $request->validate(['password' => 'required|string|min:6']);

        $user = User::findOrFail($id);
        $sessionRole = $request->session()->get('user_role');
        if (($sessionRole === 'lupto' || $request->is('api/lupto/*')) && !in_array($user->role, User::$MUNICIPAL_ROLES)) {
            return response()->json(['error' => 'Unauthorized action on this user account.'], 403);
        }

        $user->update(['password' => Hash::make($request->password)]);
        $this->writeAuditLog($request, 'RESET_PASSWORD', $id, 'Password reset by admin');

        ActivityLogService::log(
            ActivityAction::PASSWORD_RESET,
            'Users',
            "Password reset for user #{$id}",
            null,
            ['reset_by' => $request->session()->get('user_name')],
            $request
        );

        Cache::forget('activity_stats');

        return response()->json(['success' => true, 'message' => 'Password reset successfully.']);
    }

    /** DELETE /api/{role}/users/{id} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $sessionUserId = (int) $request->session()->get('user_id');
        if ($id === $sessionUserId) {
            return response()->json(['error' => 'You cannot delete your own account.'], 400);
        }

        $user = User::findOrFail($id);
        $sessionRole = $request->session()->get('user_role');
        if (($sessionRole === 'lupto' || $request->is('api/lupto/*')) && !in_array($user->role, User::$MUNICIPAL_ROLES)) {
            return response()->json(['error' => 'Unauthorized action on this user account.'], 403);
        }

        $name = $user->name;
        $user->delete();

        $this->writeAuditLog($request, 'DELETE_USER', $id, "User {$name} permanently deleted.");

        ActivityLogService::log(
            ActivityAction::USER_DELETED,
            'Users',
            "User \"{$name}\" deleted",
            ['name' => $name, 'role' => $user->role],
            null,
            $request
        );

        Cache::forget('users:role_stats');
        Cache::forget('users:stats');
        Cache::forget('activity_stats');

        return response()->json(['success' => true, 'message' => 'User deleted successfully.']);
    }

    /** PATCH /api/{role}/users/{id}/archive */
    public function archive(Request $request, int $id): JsonResponse
    {
        $sessionUserId = (int) $request->session()->get('user_id');
        if ($id === $sessionUserId) {
            return response()->json(['error' => 'You cannot archive your own account.'], 400);
        }

        $user = User::findOrFail($id);
        $sessionRole = $request->session()->get('user_role');
        if (($sessionRole === 'lupto' || $request->is('api/lupto/*')) && !in_array($user->role, User::$MUNICIPAL_ROLES)) {
            return response()->json(['error' => 'Unauthorized action on this user account.'], 403);
        }

        $user->update(['status' => 'archived']);

        $this->writeAuditLog($request, 'ARCHIVE_USER', $id, "User {$user->name} archived.");

        ActivityLogService::log(
            ActivityAction::USER_ARCHIVED,
            'Users',
            "User \"{$user->name}\" archived",
            ['status' => 'active'],
            ['status' => 'archived'],
            $request
        );

        Cache::forget('users:role_stats');
        Cache::forget('users:stats');
        Cache::forget('activity_stats');

        return response()->json(['success' => true, 'message' => 'User archived successfully.']);
    }

    /** PATCH /api/{role}/users/{id}/restore */
    public function restore(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $sessionRole = $request->session()->get('user_role');
        if (($sessionRole === 'lupto' || $request->is('api/lupto/*')) && !in_array($user->role, User::$MUNICIPAL_ROLES)) {
            return response()->json(['error' => 'Unauthorized action on this user account.'], 403);
        }

        $user->update(['status' => 'active']);

        $this->writeAuditLog($request, 'RESTORE_USER', $id, "User {$user->name} restored to active.");

        ActivityLogService::log(
            ActivityAction::USER_RESTORED,
            'Users',
            "User \"{$user->name}\" restored to active",
            ['status' => 'archived'],
            ['status' => 'active'],
            $request
        );

        Cache::forget('users:role_stats');
        Cache::forget('users:stats');
        Cache::forget('activity_stats');

        return response()->json(['success' => true, 'message' => 'User restored successfully.']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function writeAuditLog(Request $request, string $action, int $targetId, string $details): void
    {
        try {
            $actorId = (int) $request->session()->get('user_id');
            Alert::create([
                'type'    => 'user_action',
                'message' => "[#{$actorId}] {$action} on User #{$targetId} — {$details}",
                'is_read' => false,
            ]);
        } catch (\Exception) {}
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserPoint;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VoucherController extends Controller
{
    /**
     * Helper to get authenticated user from request session or auth guard.
     */
    private function getAuthUser(Request $request)
    {
        $userId = session('user_id');
        if ($userId) {
            return User::find($userId);
        }
        $sessionUser = session('user');
        if ($sessionUser && isset($sessionUser['id'])) {
            return User::find($sessionUser['id']);
        }
        return $request->user();
    }

    /**
     * List all vouchers with search, filters, sorting, and pagination.
     */
    public function index(Request $request)
    {
        $query = Voucher::with(['municipality:id,name', 'user:id,name']);

        // Search by voucher name, code, partner, or description
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('voucher_name', 'like', $search)
                    ->orWhere('voucher_code', 'like', $search)
                    ->orWhere('partner_establishment', 'like', $search)
                    ->orWhere('description', 'like', $search);
            });
        }

        // Filter by Municipality
        if ($request->filled('municipality_id')) {
            $query->where('municipality_id', $request->municipality_id);
        }

        // Filter by Status (active, inactive, archived, expired)
        if ($request->filled('status')) {
            if ($request->status === 'expired') {
                $query->where('expires_at', '<', now());
            } elseif ($request->status === 'low_stock') {
                $query->where('status', 'active')
                    ->where('remaining_quantity', '>', 0)
                    ->where('remaining_quantity', '<=', 5);
            } else {
                $query->where('status', $request->status);
            }
        } else {
            // By default hide soft deleted / archived unless specified
            $query->where('status', '!=', 'archived');
        }

        // Filter by Discount Type
        if ($request->filled('discount_type')) {
            $query->where('discount_type', $request->discount_type);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = strtolower($request->get('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['voucher_name', 'required_points', 'available_quantity', 'remaining_quantity', 'redeemed_quantity', 'expires_at', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = (int) $request->get('per_page', 15);
        $vouchers = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $vouchers->items(),
            'meta' => [
                'current_page' => $vouchers->currentPage(),
                'last_page' => $vouchers->lastPage(),
                'per_page' => $vouchers->perPage(),
                'total' => $vouchers->total(),
            ]
        ]);
    }

    /**
     * Single voucher details.
     */
    public function show($id)
    {
        $voucher = Voucher::with(['municipality', 'user:id,name,email', 'redemptions.user:id,name'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $voucher
        ]);
    }

    /**
     * Create a new voucher (LUPTO only).
     */
    public function store(Request $request)
    {
        $authUser = $this->getAuthUser($request);
        if (!$authUser || strtolower($authUser->role) !== 'lupto') {
            return response()->json(['success' => false, 'message' => 'Unauthorized. Only LUPTO can create vouchers.'], 403);
        }

        $request->validate([
            'voucher_name' => 'required|string|max:255',
            'discount_type' => 'required|string',
            'discount_value' => 'nullable|numeric|min:0',
            'required_points' => 'required|integer|min:0',
            'available_quantity' => 'required|integer|min:1',
            'valid_from' => 'nullable|date',
            'expires_at' => 'required|date',
            'status' => 'nullable|in:active,inactive',
            'municipality_id' => 'nullable|exists:municipalities,id',
            'partner_establishment' => 'nullable|string|max:255',
            'maximum_redemption_per_user' => 'nullable|integer|min:1',
            'description' => 'nullable|string',
            'terms_and_conditions' => 'nullable|string',
            'image' => 'nullable|string',
        ]);

        // Generate Unique Voucher Code if not provided
        $code = $request->voucher_code;
        if (empty($code)) {
            $code = 'INTAN-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
        }

        $availableQty = (int) $request->available_quantity;
        $validFrom = $request->filled('valid_from') ? $request->valid_from : now();
        $status = $request->filled('status') ? $request->status : 'active';

        $voucher = Voucher::create([
            'voucher_code' => $code,
            'voucher_name' => $request->voucher_name,
            'description' => $request->description,
            'image' => $request->image,
            'discount_type' => $request->discount_type,
            'discount_value' => $request->discount_value,
            'required_points' => $request->required_points,
            'available_quantity' => $availableQty,
            'redeemed_quantity' => 0,
            'remaining_quantity' => $availableQty,
            'municipality_id' => $request->municipality_id,
            'partner_establishment' => $request->partner_establishment,
            'maximum_redemption_per_user' => $request->maximum_redemption_per_user ?? 1,
            'valid_from' => $validFrom,
            'expires_at' => $request->expires_at,
            'terms_and_conditions' => $request->terms_and_conditions,
            'status' => $status,
            'created_by' => $authUser->id,
        ]);

        // Activity Log
        ActivityLog::create([
            'user_id' => $authUser->id,
            'user_name' => $authUser->name,
            'user_role' => $authUser->role,
            'action' => 'Voucher Created',
            'module' => 'Voucher & Rewards',
            'description' => "Created voucher '{$voucher->voucher_name}' ({$voucher->voucher_code}) with {$voucher->required_points} required points.",
            'ip_address' => $request->ip(),
        ]);

        // Notification
        Notification::create([
            'user_id' => $authUser->id,
            'type' => 'voucher_created',
            'title' => 'New Voucher Created',
            'message' => "Voucher '{$voucher->voucher_name}' has been created successfully.",
            'module' => 'Voucher & Rewards',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Voucher created successfully.',
            'data' => $voucher
        ], 201);
    }

    /**
     * Update an existing voucher (LUPTO only).
     */
    public function update(Request $request, $id)
    {
        $authUser = $this->getAuthUser($request);
        if (!$authUser || strtolower($authUser->role) !== 'lupto') {
            return response()->json(['success' => false, 'message' => 'Unauthorized. Only LUPTO can update vouchers.'], 403);
        }

        $voucher = Voucher::findOrFail($id);

        $request->validate([
            'voucher_name' => 'sometimes|required|string|max:255',
            'discount_type' => 'sometimes|required|string',
            'discount_value' => 'nullable|numeric|min:0',
            'required_points' => 'sometimes|required|integer|min:0',
            'available_quantity' => 'sometimes|required|integer|min:0',
            'valid_from' => 'sometimes|required|date',
            'expires_at' => 'sometimes|required|date',
            'status' => 'sometimes|required|in:active,inactive,archived',
            'municipality_id' => 'nullable|exists:municipalities,id',
            'partner_establishment' => 'nullable|string|max:255',
            'maximum_redemption_per_user' => 'nullable|integer|min:1',
            'description' => 'nullable|string',
            'terms_and_conditions' => 'nullable|string',
            'image' => 'nullable|string',
        ]);

        $oldData = $voucher->toArray();

        // Calculate remaining quantity delta if available_quantity is changed
        if ($request->has('available_quantity')) {
            $newAvailable = (int) $request->available_quantity;
            $diff = $newAvailable - $voucher->available_quantity;
            $voucher->available_quantity = $newAvailable;
            $voucher->remaining_quantity = max(0, $voucher->remaining_quantity + $diff);
        }

        $voucher->fill($request->except(['available_quantity', 'redeemed_quantity', 'voucher_code', 'created_by']));
        $voucher->save();

        // Activity Log
        ActivityLog::create([
            'user_id' => $authUser->id,
            'user_name' => $authUser->name,
            'user_role' => $authUser->role,
            'action' => 'Voucher Updated',
            'module' => 'Voucher & Rewards',
            'description' => "Updated voucher '{$voucher->voucher_name}' ({$voucher->voucher_code}).",
            'old_value' => $oldData,
            'new_value' => $voucher->toArray(),
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Voucher updated successfully.',
            'data' => $voucher
        ]);
    }

    /**
     * Archive or toggle status of a voucher (LUPTO only).
     */
    public function toggleStatus(Request $request, $id)
    {
        $authUser = $this->getAuthUser($request);
        if (!$authUser || strtolower($authUser->role) !== 'lupto') {
            return response()->json(['success' => false, 'message' => 'Unauthorized. Only LUPTO can modify voucher status.'], 403);
        }

        $voucher = Voucher::findOrFail($id);
        $newStatus = $request->get('status');

        if (!in_array($newStatus, ['active', 'inactive', 'archived'])) {
            return response()->json(['success' => false, 'message' => 'Invalid status provided.'], 422);
        }

        $voucher->status = $newStatus;
        $voucher->save();

        ActivityLog::create([
            'user_id' => $authUser->id,
            'user_name' => $authUser->name,
            'user_role' => $authUser->role,
            'action' => 'Voucher Status Changed',
            'module' => 'Voucher & Rewards',
            'description' => "Set status of voucher '{$voucher->voucher_name}' to {$newStatus}.",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Voucher status changed to {$newStatus}.",
            'data' => $voucher
        ]);
    }

    /**
     * Archive voucher (soft delete / status archived).
     */
    public function archive(Request $request, $id)
    {
        return $this->toggleStatus($request->merge(['status' => 'archived']), $id);
    }

    /**
     * Redemption History with search and filtering.
     */
    public function redemptions(Request $request)
    {
        $query = VoucherRedemption::with(['voucher:id,voucher_name,voucher_code,discount_type,discount_value', 'user:id,name,email,municipality_id', 'user.municipality:id,name']);

        // Search by Redemption Code, Tourist Name, or Voucher Name
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('redemption_code', 'like', $search)
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', $search)->orWhere('email', 'like', $search);
                    })
                    ->orWhereHas('voucher', function ($vq) use ($search) {
                        $vq->where('voucher_name', 'like', $search)->orWhere('voucher_code', 'like', $search);
                    });
            });
        }

        // Filter by Status (pending, claimed, completed, cancelled, expired)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by Voucher
        if ($request->filled('voucher_id')) {
            $query->where('voucher_id', $request->voucher_id);
        }

        // Filter by Municipality (via user's municipality or voucher's municipality)
        if ($request->filled('municipality_id')) {
            $munId = $request->municipality_id;
            $query->where(function ($q) use ($munId) {
                $q->whereHas('user', function ($uq) use ($munId) {
                    $uq->where('municipality_id', $munId);
                })->orWhereHas('voucher', function ($vq) use ($munId) {
                    $vq->where('municipality_id', $munId);
                });
            });
        }

        $query->orderBy('created_at', 'desc');

        $perPage = (int) $request->get('per_page', 15);
        $redemptions = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $redemptions->items(),
            'meta' => [
                'current_page' => $redemptions->currentPage(),
                'last_page' => $redemptions->lastPage(),
                'per_page' => $redemptions->perPage(),
                'total' => $redemptions->total(),
            ]
        ]);
    }

    /**
     * Update redemption status (e.g. mark as claimed/completed/cancelled).
     */
    public function updateRedemptionStatus(Request $request, $id)
    {
        $authUser = $this->getAuthUser($request);
        if (!$authUser || !in_array(strtolower($authUser->role), ['lupto', 'picto', 'municipal'])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $redemption = VoucherRedemption::findOrFail($id);

        $request->validate([
            'status' => 'required|in:pending,claimed,completed,cancelled,expired',
        ]);

        $redemption->status = $request->status;
        if ($request->status === 'claimed' && !$redemption->claimed_at) {
            $redemption->claimed_at = now();
        }
        $redemption->save();

        ActivityLog::create([
            'user_id' => $authUser->id,
            'user_name' => $authUser->name,
            'user_role' => $authUser->role,
            'action' => 'Voucher Redemption Status Updated',
            'module' => 'Voucher & Rewards',
            'description' => "Updated redemption status of code {$redemption->redemption_code} to {$redemption->status}.",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Redemption status updated.',
            'data' => $redemption
        ]);
    }

    /**
     * Comprehensive Statistics & KPI Dashboard Data.
     */
    public function stats(Request $request)
    {
        // Cache basic KPIs for 30 seconds for lightning-fast loads
        $kpis = \Illuminate\Support\Facades\Cache::remember('voucher_kpis_summary', 30, function () {
            return [
                'total_vouchers' => Voucher::count(),
                'active_vouchers' => Voucher::active()->count(),
                'total_redeemed' => VoucherRedemption::count(),
                'remaining_rewards' => (int) Voucher::sum('remaining_quantity'),
                'total_points_redeemed' => (int) VoucherRedemption::sum('points_used'),
                'expired_vouchers' => Voucher::where('expires_at', '<', now())->count(),
            ];
        });

        // Only compute heavy chart queries when explicitly requested (e.g. Analytics tab)
        $includeCharts = $request->boolean('charts', false);
        $chartsData = [];

        if ($includeCharts) {
            $chartsData = \Illuminate\Support\Facades\Cache::remember('voucher_charts_summary', 30, function () {
                $mostRedeemed = Voucher::orderBy('redeemed_quantity', 'desc')
                    ->take(5)
                    ->get(['id', 'voucher_name', 'voucher_code', 'redeemed_quantity', 'available_quantity', 'remaining_quantity']);

                $monthlyTrend = VoucherRedemption::select(
                    DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month_key"),
                    DB::raw("DATE_FORMAT(created_at, '%b %Y') as month_label"),
                    DB::raw('COUNT(*) as total_redemptions'),
                    DB::raw('SUM(points_used) as total_points')
                )
                    ->groupBy('month_key', 'month_label')
                    ->orderBy('month_key', 'asc')
                    ->take(6)
                    ->get();

                $byMunicipality = VoucherRedemption::join('vouchers', 'voucher_redemptions.voucher_id', '=', 'vouchers.id')
                    ->leftJoin('municipalities', 'vouchers.municipality_id', '=', 'municipalities.id')
                    ->select(
                        DB::raw("COALESCE(municipalities.name, 'Provincial / All') as municipality_name"),
                        DB::raw('COUNT(voucher_redemptions.id) as count')
                    )
                    ->groupBy('municipality_name')
                    ->orderBy('count', 'desc')
                    ->get();

                $availabilityBreakdown = [
                    'active' => Voucher::where('status', 'active')->where('expires_at', '>=', now())->where('remaining_quantity', '>', 0)->count(),
                    'inactive' => Voucher::where('status', 'inactive')->count(),
                    'expired' => Voucher::where('expires_at', '<', now())->count(),
                    'out_of_stock' => Voucher::where('remaining_quantity', 0)->count(),
                ];

                return [
                    'most_redeemed' => $mostRedeemed,
                    'monthly_trend' => $monthlyTrend,
                    'by_municipality' => $byMunicipality,
                    'availability_breakdown' => $availabilityBreakdown,
                ];
            });
        }

        return response()->json([
            'success' => true,
            'data' => array_merge(['kpis' => $kpis], $chartsData)
        ]);
    }

    /**
     * Redeem voucher endpoint (used by Mobile API).
     */
    public function redeem(Request $request, $id)
    {
        $authUser = $this->getAuthUser($request);
        if (!$authUser) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $voucher = Voucher::findOrFail($id);

        // Validations
        if ($voucher->status !== 'active') {
            return response()->json(['success' => false, 'message' => 'Voucher is not active.'], 400);
        }

        if (now()->lessThan($voucher->valid_from) || now()->greaterThan($voucher->expires_at)) {
            return response()->json(['success' => false, 'message' => 'Voucher is expired or not yet valid.'], 400);
        }

        if ($voucher->remaining_quantity <= 0) {
            return response()->json(['success' => false, 'message' => 'Voucher is out of stock.'], 400);
        }

        // Check user redemptions count
        $userRedemptionsCount = VoucherRedemption::where('voucher_id', $voucher->id)
            ->where('user_id', $authUser->id)
            ->count();

        if ($userRedemptionsCount >= $voucher->maximum_redemption_per_user) {
            return response()->json([
                'success' => false,
                'message' => "Maximum redemption limit ({$voucher->maximum_redemption_per_user}) reached for this voucher."
            ], 400);
        }

        // Check user gamification points
        $userPoint = UserPoint::where('user_id', $authUser->id)->first();
        $currentPoints = $userPoint ? $userPoint->total_points : 0;

        if ($currentPoints < $voucher->required_points) {
            return response()->json([
                'success' => false,
                'message' => "Insufficient points. You need {$voucher->required_points} points, but have only {$currentPoints} points."
            ], 400);
        }

        // Perform redemption transaction
        DB::beginTransaction();
        try {
            // 1. Deduct user points
            $userPoint->total_points -= $voucher->required_points;
            $userPoint->save();

            // 2. Adjust voucher stock
            $voucher->redeemed_quantity += 1;
            $voucher->remaining_quantity = max(0, $voucher->remaining_quantity - 1);
            $voucher->save();

            // 3. Generate unique redemption code
            $redemptionCode = 'RED-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));

            // 4. Create VoucherRedemption
            $redemption = VoucherRedemption::create([
                'voucher_id' => $voucher->id,
                'user_id' => $authUser->id,
                'redemption_code' => $redemptionCode,
                'points_used' => $voucher->required_points,
                'status' => 'pending',
                'redeemed_at' => now(),
            ]);

            // 5. Activity Log
            ActivityLog::create([
                'user_id' => $authUser->id,
                'user_name' => $authUser->name,
                'user_role' => $authUser->role,
                'action' => 'Voucher Redeemed',
                'module' => 'Voucher & Rewards',
                'description' => "Redeemed voucher '{$voucher->voucher_name}' for {$voucher->required_points} points. Code: {$redemptionCode}",
                'ip_address' => $request->ip(),
            ]);

            // 6. Notifications
            Notification::create([
                'user_id' => $authUser->id,
                'type' => 'voucher_redeemed',
                'title' => 'Voucher Redeemed Successfully',
                'message' => "You redeemed '{$voucher->voucher_name}'. Code: {$redemptionCode}",
                'module' => 'Voucher & Rewards',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Voucher redeemed successfully!',
                'data' => [
                    'redemption_code' => $redemptionCode,
                    'voucher_name' => $voucher->voucher_name,
                    'points_deducted' => $voucher->required_points,
                    'remaining_points' => $userPoint->total_points,
                    'redeemed_at' => $redemption->redeemed_at->toIso8601String(),
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to process redemption: ' . $e->getMessage()
            ], 500);
        }
    }
}

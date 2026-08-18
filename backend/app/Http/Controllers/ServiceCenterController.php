<?php

namespace App\Http\Controllers;

use App\Models\ServiceCenter;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Enums\ActivityAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ServiceCenterController extends Controller
{
    // ──────────────────────────────────────────────────────────────────────────
    //  READ
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/service-centers
     * - Municipal: returns only service centers for their municipality
     * - LUPTO: returns all service centers across the province
     * - PICTO: returns all service centers (read-only)
     */
    public function index(Request $request): JsonResponse
    {
        $role           = strtolower((string) ($request->session()->get('user_role') ?? 'lupto'));
        $municipalityId = (int) $request->session()->get('user_municipality_id', 0);

        $query = ServiceCenter::with(['municipality:id,name'])
            ->orderBy('name');

        // Municipal users can only see their own municipality's service centers
        if (in_array($role, User::$MUNICIPAL_ROLES) && $municipalityId) {
            $query->where('municipality_id', $municipalityId);
        }

        // Optional municipality filter (for LUPTO/PICTO)
        if ($request->has('municipality_id') && !in_array($role, User::$MUNICIPAL_ROLES)) {
            $query->where('municipality_id', (int) $request->input('municipality_id'));
        }

        $centers = $query->get()->map(function ($sc) {
            $arr = $sc->toArray();
            $arr['display_type'] = $sc->display_type;
            return $arr;
        });

        return response()->json([
            'success' => true,
            'data'    => $centers,
        ]);
    }

    /**
     * GET /api/service-centers/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $role           = strtolower((string) ($request->session()->get('user_role') ?? 'lupto'));
        $municipalityId = (int) $request->session()->get('user_municipality_id', 0);

        $query = ServiceCenter::with(['municipality:id,name'])->where('id', $id);

        if (in_array($role, User::$MUNICIPAL_ROLES) && $municipalityId) {
            $query->where('municipality_id', $municipalityId);
        }

        $center = $query->first();
        if (!$center) {
            return response()->json(['error' => 'Service center not found.'], 404);
        }

        $arr = $center->toArray();
        $arr['display_type'] = $center->display_type;

        return response()->json(['success' => true, 'data' => $arr]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  WRITE
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * POST /api/service-centers
     * Municipal users automatically get their municipality assigned.
     */
    public function store(Request $request): JsonResponse
    {
        $role           = strtolower((string) ($request->session()->get('user_role') ?? 'lupto'));
        $municipalityId = (int) $request->session()->get('user_municipality_id', 0);

        // PICTO is read-only
        if (in_array($role, ['picto', 'pitco'])) {
            return response()->json(['error' => 'PICTO users cannot create service centers.'], 403);
        }

        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'type'           => 'required|string|max:100',
            'custom_type'    => 'nullable|string|max:100',
            'contact_number' => 'nullable|string|max:50',
            'address'        => 'nullable|string',
            'description'    => 'nullable|string',
            'status'         => 'nullable|in:active,inactive',
            'municipality_id'=> 'sometimes|integer',
        ]);

        // Municipal users always use their own municipality
        if (in_array($role, User::$MUNICIPAL_ROLES)) {
            if (!$municipalityId) {
                return response()->json(['error' => 'No municipality assigned to your account.'], 422);
            }
            $data['municipality_id'] = $municipalityId;
        } else {
            // LUPTO must supply a municipality_id
            if (empty($data['municipality_id'])) {
                return response()->json(['error' => 'municipality_id is required.'], 422);
            }
        }

        // Only store custom_type when type is "Other"
        if ($data['type'] !== 'Other') {
            $data['custom_type'] = null;
        }

        $data['status']     = $data['status'] ?? 'active';
        $data['created_by'] = (int) $request->session()->get('user_id', 0) ?: null;

        $center = ServiceCenter::create($data);
        $center->load('municipality:id,name');

        $arr = $center->toArray();
        $arr['display_type'] = $center->display_type;

        ActivityLogService::log(
            ActivityAction::SPOT_ADDED, // reuse existing enum; no SC-specific action yet
            'Service Centers',
            'New service center "' . $center->name . '" added',
            null,
            ['name' => $center->name, 'type' => $center->type, 'municipality_id' => $center->municipality_id],
            $request
        );

        return response()->json([
            'success' => true,
            'message' => 'Service center created successfully.',
            'data'    => $arr,
        ], 201);
    }

    /**
     * PUT /api/service-centers/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $role           = strtolower((string) ($request->session()->get('user_role') ?? 'lupto'));
        $municipalityId = (int) $request->session()->get('user_municipality_id', 0);

        if (in_array($role, ['picto', 'pitco'])) {
            return response()->json(['error' => 'PICTO users cannot edit service centers.'], 403);
        }

        $query = ServiceCenter::where('id', $id);
        if (in_array($role, User::$MUNICIPAL_ROLES) && $municipalityId) {
            $query->where('municipality_id', $municipalityId);
        }
        $center = $query->first();
        if (!$center) {
            return response()->json(['error' => 'Service center not found or access denied.'], 404);
        }

        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'type'           => 'required|string|max:100',
            'custom_type'    => 'nullable|string|max:100',
            'contact_number' => 'nullable|string|max:50',
            'address'        => 'nullable|string',
            'description'    => 'nullable|string',
            'status'         => 'nullable|in:active,inactive',
        ]);

        if ($data['type'] !== 'Other') {
            $data['custom_type'] = null;
        }

        $center->update($data);
        $center->load('municipality:id,name');

        $arr = $center->fresh()->toArray();
        $arr['display_type'] = $center->display_type;

        return response()->json([
            'success' => true,
            'message' => 'Service center updated successfully.',
            'data'    => $arr,
        ]);
    }
}

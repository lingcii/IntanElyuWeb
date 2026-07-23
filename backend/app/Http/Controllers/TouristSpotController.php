<?php

namespace App\Http\Controllers;

use App\Models\Municipality;
use App\Models\TouristSpot;
use App\Models\TouristSpotAudit;
use App\Models\TouristSpotImage;
use App\Models\User;
use App\Enums\ActivityAction;
use App\Services\ActivityLogService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\CacheInvalidationService;

class TouristSpotController extends Controller
{
    private const UPLOAD_DIR = 'tourist_spots';
    // UPLOAD_URL derived from APP_URL env — never hardcode a host
    private static function uploadUrl(): string
    {
        return rtrim(env('APP_URL', 'http://127.0.0.1:8000'), '/') . '/storage/tourist_spots/';
    }
    
    // Cache column check results to avoid hitting the database every time
    private static ?bool $hasBarangayColumn = null;
    private static ?bool $hasUpdatedAtColumn = null;


    
    // Check if the tourist_spots table has a specific column (cached)
    private function hasColumn(string $column): bool
    {
        $cacheProperty = match ($column) {
            'barangay' => 'hasBarangayColumn',
            'updated_at' => 'hasUpdatedAtColumn',
            default => null,
        };
        
        if ($cacheProperty && self::$$cacheProperty !== null) {
            return self::$$cacheProperty;
        }
        
        $result = false;
        try {
            $result = Schema::hasColumn('tourist_spots', $column);
        } catch (\Exception $e) {
            $result = false;
        }
        
        if ($cacheProperty) {
            self::$$cacheProperty = $result;
        }
        
        return $result;
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  READ
    // ──────────────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $role           = $request->session()->get('user_role');
        $municipalityId = (int) $request->session()->get('user_municipality_id', 0);

        $cacheKey = "tourist-spots:list:{$role}:{$municipalityId}";

        $spots = Cache::remember($cacheKey, 300, function () use ($role, $municipalityId) {
            $query = TouristSpot::select([
                'id', 'name', 'municipality_id', 'barangay', 'category', 'description', 'entrance_fee', 'environmental_fee', 'fee_types',
                'status', 'photo_url', 'latitude', 'longitude', 'opening_time',
                'closing_time', 'is_maintenance', 'classification_status', 'rejection_reason', 'visits', 'rating', 'points', 'approved_by', 'approved_at', 'created_by', 'creator_role', 'created_at'
            ])->with(['municipality:id,name', 'images', 'approver:id,name', 'creator:id,name']);

            if (in_array($role, User::$MUNICIPAL_ROLES) && $municipalityId) {
                $query->where('municipality_id', $municipalityId);
            }

            // Exclude drafts from standard spot list queries
            $query->where('status', '!=', 'draft');

            $list = $query->latest()->get();
            return $this->attachPrimaryPhoto($list)->toArray();
        });

        return $this->etagResponse($request, $spots);
    }

    /** GET /api/tourist-spots/{id} (all roles: access allowed) */
    public function show(Request $request, int $id): JsonResponse
    {
        $role           = $request->session()->get('user_role');
        $municipalityId = (int) $request->session()->get('user_municipality_id', 0);

        $query = TouristSpot::with(['municipality:id,name', 'images', 'approver:id,name', 'creator:id,name'])->where('id', $id);

        if (in_array($role, User::$MUNICIPAL_ROLES) && $municipalityId) {
            $query->where('municipality_id', $municipalityId);
        }

        $spot = $query->first();
        if (!$spot) return response()->json(['error' => 'Spot not found.'], 404);

        $spot = $this->setPhotoUrl($spot);
        return response()->json($spot);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  WRITE
    // ──────────────────────────────────────────────────────────────────────────

    /** POST /api/tourist-spots/upload-image */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate(['image' => 'required|image|mimes:jpeg,jpg,png|max:10240']); // 10MB

        $file     = $request->file('image');
        $filename = 'spot_' . uniqid() . '.' . $file->extension();
        
        // Ensure directory exists and is writable
        $directory = storage_path('app/public/' . self::UPLOAD_DIR);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        if (!is_writable($directory)) {
            @chmod($directory, 0777);
            if (str_starts_with(PHP_OS, 'WIN')) {
                @exec('attrib -r "' . $directory . '" /d');
            }
        }
        
        $file->move($directory, $filename);

        // Return the proxy URL instead of the full storage URL
        $url = '/api/serve-image.php?file=' . urlencode($filename);

        return response()->json([
            'success'   => true,
            'photo_url' => $url,
            'filename'  => $filename,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  DRAFT MANAGEMENT
    // ──────────────────────────────────────────────────────────────────────────

    /** GET /api/tourist-spots/draft */
    public function getDraft(Request $request): JsonResponse
    {
        $userId = (int) $request->session()->get('user_id', 0);
        $role   = $request->session()->get('user_role');
        $muniId = (int) $request->session()->get('user_municipality_id', 0);

        $query = TouristSpot::with(['municipality:id,name', 'images'])
            ->where('status', 'draft');

        if ($userId) {
            $query->where('created_by', $userId);
        } elseif (in_array($role, User::$MUNICIPAL_ROLES) && $muniId) {
            $query->where('municipality_id', $muniId);
        } else {
            return response()->json(['draft' => null]);
        }

        $draft = $query->latest('id')->first();
        if (!$draft) {
            return response()->json(['draft' => null]);
        }

        return response()->json([
            'draft' => $draft->toArray()
        ]);
    }

    /** POST /api/tourist-spots/draft */
    public function saveDraft(Request $request): JsonResponse
    {
        $userId = (int) $request->session()->get('user_id', 0);
        $role   = $request->session()->get('user_role', 'lupto');
        $muniId = (int) $request->session()->get('user_municipality_id', 0);

        $draftId = $request->input('id');
        $draft   = null;

        if ($draftId) {
            $draft = TouristSpot::where('id', $draftId)->where('status', 'draft')->first();
        }

        if (!$draft && $userId) {
            $draft = TouristSpot::where('created_by', $userId)->where('status', 'draft')->latest('id')->first();
        }

        if (!$draft && in_array($role, User::$MUNICIPAL_ROLES) && $muniId) {
            $draft = TouristSpot::where('municipality_id', $muniId)->where('status', 'draft')->latest('id')->first();
        }

        $data = [
            'name'                  => $request->input('name') ?: 'Untitled Draft',
            'municipality_id'       => $request->input('municipality_id') ? (int) $request->input('municipality_id') : ($muniId ?: 1),
            'barangay'              => $request->input('barangay') ?: null,
            'category'              => $request->input('category') ?: 'Other',
            'entrance_fee'          => (float) ($request->input('entrance_fee') ?? 0),
            'environmental_fee'     => (float) ($request->input('environmental_fee') ?? 0),
            'fee_types'             => $request->input('fee_types') ?: [],
            'description'           => $request->input('description') ?: '',
            'latitude'              => $request->input('latitude') ? (float) $request->input('latitude') : null,
            'longitude'             => $request->input('longitude') ? (float) $request->input('longitude') : null,
            'opening_time'          => $request->input('opening_time') ?: null,
            'closing_time'          => $request->input('closing_time') ?: null,
            'is_maintenance'        => (bool) $request->input('is_maintenance', false),
            'classification_status' => $request->input('classification_status') ?: 'EXISTING',
            'status'                => 'draft',
            'points'                => (int) ($request->input('points') ?? 50),
            'created_by'            => $userId ?: null,
            'creator_role'          => $role,
        ];

        if ($draft) {
            $draft->update($data);
        } else {
            $draft = TouristSpot::create($data);
        }

        if ($request->has('images') && is_array($request->input('images'))) {
            $draft->images()->delete();
            foreach ($request->input('images') as $i => $img) {
                $photoUrl = is_array($img) ? ($img['photo_url'] ?? '') : $img;
                if ($photoUrl) {
                    TouristSpotImage::create([
                        'spot_id'    => $draft->id,
                        'photo_url'  => $photoUrl,
                        'is_primary'  => ($i === 0),
                        'sort_order' => $i,
                    ]);
                }
            }
            if (count($request->input('images')) > 0) {
                $firstUrl = is_array($request->input('images')[0]) ? ($request->input('images')[0]['photo_url'] ?? '') : $request->input('images')[0];
                if ($firstUrl) {
                    $draft->update(['photo_url' => $firstUrl]);
                }
            }
        }

        CacheInvalidationService::forgetTouristSpots();

        return response()->json([
            'success' => true,
            'message' => 'Draft saved successfully.',
            'draft'   => $draft->fresh(['images', 'municipality'])->toArray()
        ]);
    }

    /** DELETE /api/tourist-spots/draft/{id} */
    public function deleteDraft(Request $request, int $id): JsonResponse
    {
        $userId = (int) $request->session()->get('user_id', 0);
        $muniId = (int) $request->session()->get('user_municipality_id', 0);

        $drafts = TouristSpot::where('status', 'draft')
            ->where(function ($q) use ($id, $userId, $muniId) {
                $q->where('id', $id);
                if ($userId) $q->orWhere('created_by', $userId);
                if ($muniId) $q->orWhere('municipality_id', $muniId);
            })->get();

        foreach ($drafts as $draft) {
            $draft->images()->delete();
            $draft->delete();
        }

        CacheInvalidationService::forgetTouristSpots();
        return response()->json(['success' => true, 'message' => 'Draft deleted successfully.']);
    }

    public function store(Request $request): JsonResponse
    {
        $role = $request->session()->get('user_role');
        if (in_array($role, User::$MUNICIPAL_ROLES)) {
            $request->merge(['points' => 0]);
        }

        $rules = [
            'name'                  => 'required|string|max:255',
            'barangay'              => 'nullable|string|max:255',
            'category'              => 'required|string',
            'description'           => 'required|string',
            'classification_status' => 'required|string',
            'municipality_id'       => 'sometimes|integer',
            'entrance_fee'          => 'nullable|numeric',
            'environmental_fee'     => 'nullable|numeric',
            'fee_types'             => 'nullable|array',
            'fee_types.*'           => 'in:entrance,environmental',
            'latitude'              => 'nullable|numeric',
            'longitude'             => 'nullable|numeric',
            'opening_time'          => 'nullable|string',
            'closing_time'          => 'nullable|string',
            'is_maintenance'        => 'nullable|boolean',
            'images'                => 'nullable|array',
            'points'                => 'required|integer|min:0',
        ];

        $feeTypes = $request->input('fee_types', []);
        if (in_array('entrance', $feeTypes)) {
            $rules['entrance_fee'] = 'required|numeric|min:0';
        }
        if (in_array('environmental', $feeTypes)) {
            $rules['environmental_fee'] = 'required|numeric|min:0';
        }

        $data = $request->validate($rules);

        $role           = $request->session()->get('user_role');
        $sessionMuniId  = (int) $request->session()->get('user_municipality_id', 0);

        // Municipal users always use their own municipality
        if (in_array($role, User::$MUNICIPAL_ROLES)) {
            $data['municipality_id'] = $sessionMuniId;
        }

        if (empty($data['municipality_id'])) {
            return response()->json(['error' => 'municipality_id is required.'], 422);
        }

        // Normalize category: accept comma-separated multi-category values
        $data['category'] = self::normalizeCategories($data['category']);

        $data['fee_types'] = $data['fee_types'] ?? [];

        // LUPTO can only create spots with EXISTING classification
        // Accept both the raw label ('EXISTING') and the mapped stored value ('EXIST')
        $classUpper = strtoupper($data['classification_status']);
        if ($role === 'lupto' && !in_array($classUpper, ['EXISTING', 'EXIST'])) {
            return response()->json(['error' => 'LUPTO can only create spots with EXISTING classification.'], 422);
        }

        $mapped = TouristSpot::$STATUS_MAP[strtoupper($data['classification_status'])] ?? null;
        if (!in_array($mapped, TouristSpot::$VALID_STATUSES)) {
            return response()->json(['error' => 'Invalid classification status.'], 422);
        }
        $data['classification_status'] = $mapped;

        // Determine approval status based on role
        $initialStatus = 'approved';
        if (in_array($role, User::$MUNICIPAL_ROLES)) {
            $initialStatus = 'pending';
        }

        $photoUrl = $this->normalizePhotoUrl($data['images'][0]['photo_url'] ?? null);

        $spot = DB::transaction(function () use ($data, $photoUrl, $request, $initialStatus, $role) {
            // Create the spot data array without barangay first, then add it only if it exists
            $spotData = [
                'name'                  => $data['name'],
                'municipality_id'       => $data['municipality_id'],
                'category'              => $data['category'],
                'entrance_fee'          => $data['entrance_fee'] ?? 0,
                'environmental_fee'     => $data['environmental_fee'] ?? 0,
                'fee_types'             => $data['fee_types'] ?? [],
                'description'           => $data['description'],
                'photo_url'             => $photoUrl,
                'latitude'              => $data['latitude']  ?? 0,
                'longitude'             => $data['longitude'] ?? 0,
                'opening_time'          => $data['opening_time']  ?? null,
                'closing_time'          => $data['closing_time']  ?? null,
                'is_maintenance'        => $data['is_maintenance'] ?? false,
                'status'                => $initialStatus,
                'classification_status' => $data['classification_status'],
                'points'                => (int) $data['points'],
                'created_by'            => (int) $request->session()->get('user_id'),
                'creator_role'          => $role,
            ];

            // If updating an existing draft, reuse that record instead of creating a duplicate
            $draftId = $request->input('draft_id') ?: $request->input('id');
            $existingDraft = null;
            if ($draftId) {
                $existingDraft = TouristSpot::where('id', $draftId)->where('status', 'draft')->first();
            }

            if ($existingDraft) {
                $existingDraft->update($spotData);
                $spot = $existingDraft;
            } else {
                $spot = new TouristSpot($spotData);
                $spot->save();
            }

            $this->syncImages($spot->id, $data['images'] ?? []);

            // Clean up any remaining draft entries for this user/municipality so drafts are strictly separate
            $userId = (int) $request->session()->get('user_id', 0);
            $sessionMuniId = (int) $request->session()->get('user_municipality_id', 0);
            $draftQuery = TouristSpot::where('status', 'draft')
                ->where(function($q) use ($userId, $sessionMuniId) {
                    if ($userId) $q->orWhere('created_by', $userId);
                    if ($sessionMuniId) $q->orWhere('municipality_id', $sessionMuniId);
                });
            $leftoverDrafts = $draftQuery->get();
            foreach ($leftoverDrafts as $d) {
                $d->images()->delete();
                $d->delete();
            }

            // Only increment attraction_count for approved spots (pending spots aren't counted yet)
            if ($initialStatus === 'approved') {
                Municipality::where('id', $spot->municipality_id)->increment('attraction_count');
            }

            $this->auditLog($spot->id, (int) $request->session()->get('user_id'), 'created', ['name' => $spot->name, 'category' => $spot->category, 'status' => $spot->status], $request);

            ActivityLogService::log(
                ActivityAction::SPOT_ADDED,
                'Tourist Spots',
                'New tourist spot "' . $spot->name . '" added',
                null,
                ['name' => $spot->name, 'category' => $spot->category, 'entrance_fee' => $spot->entrance_fee, 'status' => $spot->status],
                $request
            );

            return $spot;
        });

        try {
            if ($role === 'lupto' || $initialStatus === 'approved') {
                NotificationService::notifyProvincial(
                    'spot_added',
                    'New Tourist Spot Added',
                    "A new tourist spot \"" . $spot->name . "\" has been added.",
                    [
                        'module'            => 'TouristSpots',
                        'action_url'        => 'tourist-spots.php',
                        'spot_name'         => $spot->name,
                        'municipality_name' => $spot->municipality?->name,
                        'actor_name'        => $request->session()->get('user_name'),
                    ]
                );
            } else {
                NotificationService::notifyProvincial(
                    'spot_pending',
                    'New Tourist Spot Pending',
                    "A new tourist spot \"" . $spot->name . "\" has been submitted for approval.",
                    [
                        'module'            => 'TouristSpots',
                        'action_url'        => 'tourist-spots.php',
                        'spot_name'         => $spot->name,
                        'municipality_name' => $spot->municipality?->name,
                        'actor_name'        => $request->session()->get('user_name'),
                    ]
                );
            }
        } catch (\Exception $e) {
            // Notification failure must not block spot creation
            \Log::warning('Notification failed on spot creation: ' . $e->getMessage());
        }

        CacheInvalidationService::invalidateAll($spot->municipality_id);
        return response()->json([
            'success' => true,
            'message' => 'Tourist spot created successfully.',
            'id' => $spot->id,
            'spot' => $spot->fresh(['municipality', 'images'])?->toArray()
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $role = $request->session()->get('user_role');
        if (in_array($role, User::$MUNICIPAL_ROLES)) {
            $municipalityId = (int) $request->session()->get('user_municipality_id', 0);
            $query = TouristSpot::where('id', $id);
            if ($municipalityId) {
                $query->where('municipality_id', $municipalityId);
            }
            $spot = $query->first();
            $existingPoints = $spot ? (int)$spot->points : 0;
            $request->merge(['points' => $existingPoints]);
        }

        $rules = [
            'name'                  => 'required|string|max:255',
            'barangay'              => 'nullable|string|max:255',
            'category'              => 'required|string',
            'description'           => 'required|string',
            'classification_status' => 'required|string',
            'entrance_fee'          => 'nullable|numeric',
            'environmental_fee'     => 'nullable|numeric',
            'fee_types'             => 'nullable|array',
            'fee_types.*'           => 'in:entrance,environmental',
            'latitude'              => 'nullable|numeric',
            'longitude'             => 'nullable|numeric',
            'opening_time'          => 'nullable|string',
            'closing_time'          => 'nullable|string',
            'is_maintenance'        => 'nullable|boolean',
            'images'                => 'nullable|array',
            'points'                => 'required|integer|min:0',
        ];

        $feeTypes = $request->input('fee_types', []);
        if (in_array('entrance', $feeTypes)) {
            $rules['entrance_fee'] = 'required|numeric|min:0';
        }
        if (in_array('environmental', $feeTypes)) {
            $rules['environmental_fee'] = 'required|numeric|min:0';
        }

        $data = $request->validate($rules);

        $role           = $request->session()->get('user_role');
        $municipalityId = (int) $request->session()->get('user_municipality_id', 0);

        $query = TouristSpot::where('id', $id);
        if (in_array($role, User::$MUNICIPAL_ROLES) && $municipalityId) {
            $query->where('municipality_id', $municipalityId);
        }
        $spot = $query->firstOrFail();
        $old  = $spot->only(['name', 'category', 'entrance_fee', 'classification_status', 'status']);

        // Normalize category: accept comma-separated multi-category values
        $data['category'] = self::normalizeCategories($data['category']);

        $data['fee_types'] = $data['fee_types'] ?? [];

        $mapped = TouristSpot::$STATUS_MAP[strtoupper($data['classification_status'])] ?? null;
        if (!in_array($mapped, TouristSpot::$VALID_STATUSES)) {
            return response()->json(['error' => 'Invalid classification status.'], 422);
        }
        $data['classification_status'] = $mapped;

        $photoUrl = $this->normalizePhotoUrl($data['images'][0]['photo_url'] ?? null);

        DB::transaction(function () use ($spot, $data, $photoUrl, $old, $request, $role) {
            $updateData = [
                'name'                  => $data['name'],
                'category'              => $data['category'],
                'entrance_fee'          => $data['entrance_fee'] ?? 0,
                'environmental_fee'     => $data['environmental_fee'] ?? 0,
                'fee_types'             => $data['fee_types'] ?? [],
                'description'           => $data['description'],
                'photo_url'             => $photoUrl,
                'latitude'              => $data['latitude']  ?? 0,
                'longitude'             => $data['longitude'] ?? 0,
                'opening_time'          => $data['opening_time']  ?? null,
                'closing_time'          => $data['closing_time']  ?? null,
                'is_maintenance'        => $data['is_maintenance'] ?? false,
                'classification_status' => $data['classification_status'],
                'points'                => (int) $data['points'],
            ];

            // When MTO edits a rejected spot, reset status to pending for re-approval
            if (in_array($role, User::$MUNICIPAL_ROLES) && $spot->status === 'rejected') {
                $updateData['status']        = 'pending';
                $updateData['rejection_reason'] = null;
                $updateData['approved_by']   = null;
                $updateData['approved_at']   = null;
            }

            // Use the cached column check
            if ($this->hasColumn('barangay')) {
                $updateData['barangay'] = $data['barangay'] ?? null;
            }

            $spot->fill($updateData);
            $spot->updated_at = now();
            $spot->save();

            $this->syncImages($spot->id, $data['images'] ?? []);
            $this->auditLog($spot->id, (int) $request->session()->get('user_id'), 'updated', ['old' => $old, 'new' => $data], $request);

            ActivityLogService::log(
                ActivityAction::SPOT_UPDATED,
                'Tourist Spots',
                'Updated details for "' . $spot->name . '"',
                $old,
                ['name' => $data['name'], 'category' => $data['category'], 'entrance_fee' => $data['entrance_fee'] ?? 0, 'classification_status' => $data['classification_status'], 'status' => $updateData['status'] ?? $spot->status],
                $request
            );
        });

        CacheInvalidationService::invalidateAll($spot->municipality_id);
        return response()->json(['success' => true, 'message' => 'Tourist spot updated successfully.']);
    }

    /** DELETE /api/tourist-spots/{id} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $role           = $request->session()->get('user_role');
        $municipalityId = (int) $request->session()->get('user_municipality_id', 0);

        $query = TouristSpot::where('id', $id);
        if (in_array($role, User::$MUNICIPAL_ROLES) && $municipalityId) {
            $query->where('municipality_id', $municipalityId);
        }
        $spot = $query->firstOrFail();

        DB::transaction(function () use ($spot, $request) {
            Municipality::where('id', $spot->municipality_id)
                ->decrement('attraction_count');

            $this->auditLog($spot->id, (int) $request->session()->get('user_id'), 'deleted', ['name' => $spot->name], $request);
            $spot->delete();
        });

        ActivityLogService::log(
            ActivityAction::SPOT_DELETED,
            'Tourist Spots',
            'Tourist spot "' . $spot->name . '" deleted',
            ['name' => $spot->name, 'municipality_id' => $spot->municipality_id],
            null,
            $request
        );

        CacheInvalidationService::invalidateAll($spot->municipality_id);
        return response()->json(['success' => true, 'message' => 'Tourist spot deleted successfully.']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Accept a comma-separated category string, validate each part,
     * and return a cleaned comma-separated string.
     * e.g. "Beach,Mountain,Foo" → "Beach,Mountain"
     * Falls back to "Other" only if nothing valid is found.
     */
    private static function normalizeCategories(string $raw): string
    {
        $parts = array_map('trim', explode(',', $raw));
        $valid = array_filter($parts, fn($p) => in_array($p, TouristSpot::$VALID_CATEGORIES));
        return implode(',', $valid) ?: 'Other';
    }

    private function syncImages(int $spotId, array $images): void
    {
        if (empty($images)) {
            // No images supplied — leave existing images intact
            return;
        }

        // Build a set of normalized URLs from the incoming payload
        $incomingUrls = array_map(fn($img) => $this->normalizePhotoUrl($img['photo_url']), $images);

        // Delete only images that are no longer in the incoming list
        TouristSpotImage::where('spot_id', $spotId)
            ->whereNotIn('photo_url', $incomingUrls)
            ->delete();

        // Upsert remaining images (preserves existing rows, inserts new ones)
        foreach ($images as $i => $image) {
            $url = $this->normalizePhotoUrl($image['photo_url']);
            TouristSpotImage::updateOrCreate(
                ['spot_id' => $spotId, 'photo_url' => $url],
                ['is_primary' => $i === 0 ? 1 : 0, 'sort_order' => $i]
            );
        }
    }

    private function setPhotoUrl(TouristSpot $spot): TouristSpot
    {
        $images = $spot->images;
        if ($images && $images->isNotEmpty()) {
            $primary = $images->firstWhere('is_primary', 1) ?? $images->first();
            $spot->photo_url = $this->normalizePhotoUrl($primary->photo_url);
            foreach ($images as $img) {
                if (isset($img->photo_url)) {
                    $img->photo_url = $this->normalizePhotoUrl($img->photo_url);
                }
            }
        } elseif ($spot->photo_url) {
            $spot->photo_url = $this->normalizePhotoUrl($spot->photo_url);
        } else {
            $spot->photo_url = null;
        }
        $spot->municipality_name = $spot->municipality?->name ?? null;
        return $spot;
    }

    /**
     * Ensure a stored photo_url uses the frontend proxy format whenever possible.
     * Handles:
     * - Proxy URLs (e.g. "/api/serve-image.php?file=xxx.jpg") → kept as-is
     * - Full Laravel serveImage URLs → converted back to proxy format
     * - Bare filenames (e.g. "urbiztondo.jpg") → wrapped in proxy format
     * - Full storage URLs (any protocol/host) → converted to proxy format
     * - Legacy paths (e.g. "/Gaw-at-GO-System/...") → converted to proxy format
     *
     * The proxy URL format (/api/serve-image.php?file=...) is relative and works
     * regardless of the domain/port the frontend is accessed from.
     */
    private function normalizePhotoUrl(?string $url): ?string
    {
        if (!$url) return null;

        // If it's a full web URL (e.g. Unsplash), do NOT convert to serve-image.php unless it points to serve-image
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            if (str_contains($url, 'serve-image.php')) {
                $parsed = parse_url($url);
                parse_str($parsed['query'] ?? '', $params);
                $filename = $params['file'] ?? null;
                if ($filename) {
                    return '/api/serve-image.php?file=' . urlencode($filename);
                }
            }
            return $url;
        }

        $filename = null;

        if (str_contains($url, 'serve-image.php')) {
            $parsed = parse_url($url);
            parse_str($parsed['query'] ?? '', $params);
            $filename = $params['file'] ?? null;
        } elseif (str_contains($url, '/api/images/tourist-spots/')) {
            $filename = basename(parse_url($url, PHP_URL_PATH) ?? '');
        } elseif (str_starts_with($url, '/') || str_starts_with($url, '..')) {
            $filename = basename($url);
        } else {
            $filename = $url;
        }

        if ($filename) {
            return '/api/serve-image.php?file=' . urlencode($filename);
        }

        return null;
    }

    /**
     * GET /api/serve-image.php?file=...
     * Proxy-compatible image serving — mirrors the frontend serve-image.php.
     * Used by the mobile app and any client that can't reach the frontend PHP server.
     */
    public function serveImageProxy(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $filename = $request->query('file', '');
        return $this->serveImage($filename);
    }

    /**
     * GET /api/images/tourist-spots/{filename}
     * Serves an image file from the filesystem, searching frontend + backend
     * storage directories. No auth required — intended for <img> tags.
     */
    public function serveImage(string $filename): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename)) {
            abort(400, 'Invalid filename');
        }

        $directories = [
            base_path('../Frontend/Website/Frontend/images/tourist_spots/'),
            storage_path('app/public/tourist_spots/'),
            storage_path('app/public/'),
            public_path('storage/tourist_spots/'),
            base_path('../Frontend/Website/Frontend/images/'),
        ];

        $imagePath = null;
        foreach ($directories as $dir) {
            $testPath = $dir . $filename;
            if (file_exists($testPath)) {
                $imagePath = $testPath;
                break;
            }
        }

        if (!$imagePath) {
            abort(404, 'File not found');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $imagePath);
        finfo_close($finfo);

        return response()->file($imagePath, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }

    private function attachPrimaryPhoto($spots)
    {
        return $spots->map(function($s) {
            return $this->setPhotoUrl($s);
        });
    }

    private function auditLog(int $spotId, int $userId, string $action, array $changes, Request $request): void
    {
        try {
            TouristSpotAudit::create([
                'spot_id'    => $spotId,
                'user_id'    => $userId,
                'action'     => $action,
                'changes'    => json_encode($changes),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Exception) {}
    }
}


<?php

namespace App\Http\Controllers;

use App\Models\FareGuide;
use App\Models\FareMatrix;
use App\Models\FareUpload;
use App\Models\ImportLog;
use App\Models\User;
use App\Models\ValidationError;
use App\Enums\ActivityAction;
use App\Services\ActivityLogService;
use App\Services\FareDataProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FareDataController extends Controller
{
    // ──────────────────────────────────────────────────────────────────────────
    //  READ endpoints (all roles)
    // ──────────────────────────────────────────────────────────────────────────

    /** GET /api/{role}/fare-data/stats  (PITCO only) */
    public function stats(): JsonResponse
    {
        $payload = \Illuminate\Support\Facades\Cache::remember('fare-data:stats', 300, function () {
            // Combine guide status counts into a single query
            $guideStats = FareGuide::selectRaw("
                COUNT(*) as total,
                SUM(status = 'active') as active_cnt,
                SUM(status = 'archived') as archived_cnt
            ")->first();

            $totalGuides    = (int) ($guideStats->total ?? 0);
            $activeGuides   = (int) ($guideStats->active_cnt ?? 0);
            $archivedGuides = (int) ($guideStats->archived_cnt ?? 0);

            // Use a single JOIN to fetch min, max, avg fare matrices calculations
            $entries = FareMatrix::join('fare_guides', 'fare_matrices.fare_guide_id', '=', 'fare_guides.id')
                ->where('fare_guides.status', '!=', 'archived')
                ->selectRaw('COUNT(*) as total, MIN(regular_fare) as min_fare, MAX(regular_fare) as max_fare, AVG(regular_fare) as avg_fare')
                ->first();

            $municipalitiesCount = DB::table('fare_guides')
                ->join('users', 'fare_guides.created_by', '=', 'users.id')
                ->whereNotNull('users.municipality_id')
                ->distinct('users.municipality_id')
                ->count('users.municipality_id');

            $totalRoutes = FareMatrix::count();
            $transportationTypes = FareGuide::distinct('vehicle_type')->count('vehicle_type');
            $lastUpdatedCount = FareGuide::where('updated_at', '>=', now()->subDays(30))->count();

            return [
                'total_guides'         => $totalGuides,
                'active_guides'        => $activeGuides,
                'archived_guides'      => $archivedGuides,
                'municipalities_count' => $municipalitiesCount,
                'total_routes'         => $totalRoutes,
                'transportation_types' => $transportationTypes,
                'last_updated_count'   => $lastUpdatedCount,
                'total_entries'        => $totalRoutes,
                'lowest_fare'          => $entries->min_fare  !== null ? round((float) $entries->min_fare,  2) : null,
                'highest_fare'         => $entries->max_fare  !== null ? round((float) $entries->max_fare,  2) : null,
                'avg_fare'             => $entries->avg_fare  !== null ? round((float) $entries->avg_fare,  2) : null,
            ];
        });

        return response()->json(array_merge(['success' => true], $payload));
    }

    /** GET /api/{role}/fare-data/guides */
    public function guides(Request $request): JsonResponse
    {
        $role  = $request->session()->get('user_role');
        $municipalityId = in_array($role, User::$MUNICIPAL_ROLES)
            ? (int) $request->session()->get('user_municipality_id', 0)
            : 0;
        $cacheKey = "fare-data:guides:{$role}:{$municipalityId}";

        $guides = \Illuminate\Support\Facades\Cache::remember($cacheKey, 3600, function () use ($role, $municipalityId) {
            $query = FareGuide::with('creator:id,name')
                ->select('id', 'title', 'vehicle_type', 'region', 'status', 'effective_date', 'created_by', 'created_at', 'updated_at');

            // LUPTO sees only active 
            if ($role === 'lupto') {
                $query->where('status', 'active');
            }

            // Municipal users only see Tricycle fare guides for their own municipality
            if (in_array($role, \App\Models\User::$MUNICIPAL_ROLES)) {
                $query->where('vehicle_type', 'Tricycle');
                if ($municipalityId) {
                    $query->whereHas('creator', fn($q) => $q->where('municipality_id', $municipalityId));
                }
            }

            return $query->latest()->get()->map(function ($guide) {
                $guideArray = $guide->toArray();
                $guideArray['created_by_name'] = $guide->creator?->name ?? '—';
                return $guideArray;
            })->toArray();
        });

        return response()->json(['success' => true, 'fare_guides' => $guides]);
    }

    /** GET /api/{role}/fare-data/matrices?guide_id= */
    public function matrices(Request $request): JsonResponse
    {
        $request->validate(['guide_id' => 'required|integer']);
        $guideId = $request->guide_id;

        $matrices = \Illuminate\Support\Facades\Cache::remember("fare-data:matrices:{$guideId}", 3600, function () use ($guideId) {
            return FareMatrix::where('fare_guide_id', $guideId)->orderBy('distance_km')->get()->toArray();
        });

        return response()->json(['success' => true, 'fare_matrices' => $matrices]);
    }

    /** GET /api/{role}/fare-data/uploads */
    public function uploads(Request $request): JsonResponse
    {
        $role       = $request->session()->get('user_role');
        $municipalityId = (int) $request->session()->get('user_municipality_id', 0);

        $query = FareUpload::with('uploader:id,name');

        // Municipal users see only their own municipality's uploads
        if (in_array($role, User::$MUNICIPAL_ROLES) && $municipalityId) {
            $query->whereHas('uploader', fn($q) => $q->where('municipality_id', $municipalityId));
        }

        $uploads = $query->latest()->get()->map(function ($upload) {
            $uploadArray = $upload->toArray();
            $uploadArray['uploaded_by_name'] = $upload->uploader?->name ?? '—';
            return $uploadArray;
        });

        return response()->json(['success' => true, 'uploads' => $uploads]);
    }

    /** GET /api/{role}/fare-data/import-logs?upload_id= */
    public function importLogs(Request $request): JsonResponse
    {
        $request->validate(['upload_id' => 'required|integer']);
        return response()->json(['success' => true, 'import_logs' => ImportLog::where('fare_upload_id', $request->upload_id)->orderBy('created_at')->get()]);
    }

    /** GET /api/{role}/fare-data/validation-errors?upload_id= */
    public function validationErrors(Request $request): JsonResponse
    {
        $request->validate(['upload_id' => 'required|integer']);
        return response()->json(['success' => true, 'validation_errors' => ValidationError::where('fare_upload_id', $request->upload_id)->orderBy('row_number')->get()]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  WRITE endpoints (PITCO + Municipal)
    // ──────────────────────────────────────────────────────────────────────────

    /** POST /api/{role}/fare-data/upload */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'csv_file'       => 'required|file|max:20480',
            'title'          => 'nullable|string|max:255',
            'vehicle_type'   => 'nullable|string|in:PUB_Aircon,PUB_Ordinary,PUJ_Aircon,PUJ_Ordinary,Tricycle,Van',
            'region'         => 'nullable|string|max:255',
            'effective_date' => 'nullable|date',
        ]);

        $role   = $request->session()->get('user_role');
        $prefix = ($role === 'picto') ? 'fare_pitco_' : 'fare_mto_';

        $file              = $request->file('csv_file');
        $extension         = strtolower($file->getClientOriginalExtension());

        // Security check: Accept only .csv files
        if ($extension !== 'csv') {
            return response()->json([
                'success' => false,
                'error'   => 'Only CSV files (.csv) are allowed.',
            ], 400);
        }

        $originalName      = $file->getClientOriginalName();
        $fileSize          = $file->getSize();
        $mimeType          = $file->getMimeType();
        $uniquePrefix      = uniqid($prefix, true) . '_';

        try {
            $storedPath = Storage::disk('local')->putFileAs(
                'uploads', $file, $uniquePrefix . $originalName
            );
            $filePath = Storage::disk('local')->path($storedPath);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }

        $allowedVehicleType = in_array($role, User::$MUNICIPAL_ROLES) ? 'Tricycle' : null;
        $formMetadata = $request->only(['title', 'vehicle_type', 'region', 'effective_date']);

        if (in_array($role, User::$MUNICIPAL_ROLES)) {
            $formMetadata['vehicle_type'] = 'Tricycle';
            $user = User::find($request->session()->get('user_id'));
            if ($user && $user->municipality) {
                $formMetadata['region'] = $user->municipality->name;
            }
        }

        try {
            $processor = new FareDataProcessor((int) $request->session()->get('user_id'));
            $result    = $processor->processUpload($filePath, $originalName, $fileSize, $mimeType, $allowedVehicleType, $formMetadata);

            if ($result['success'] ?? false) {
                $recordCount = $result['valid_records'] ?? 0;
                ActivityLogService::log(
                    ActivityAction::FARE_UPLOADED,
                    'Fare Data',
                    "Fare data uploaded: \"{$originalName}\" ({$recordCount} records)",
                    null,
                    ['filename' => $originalName, 'records' => $recordCount],
                    $request
                );
            }

            $this->clearFareDataCaches();

            return response()->json($result);
        } catch (\Exception $e) {
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            $this->clearFareDataCaches();
            return response()->json([
                'success'       => false,
                'error'         => $e->getMessage(),
                'total_records' => 0,
                'valid_records' => 0,
            ]);
        }
    }

    /** POST /api/{role}/fare-data */
    public function store(Request $request): JsonResponse
    {
        $role = $request->session()->get('user_role');
        $userId = (int) $request->session()->get('user_id');

        $request->validate([
            'title'          => 'required|string|max:255',
            'vehicle_type'   => 'required|string|in:PUB_Aircon,PUB_Ordinary,PUJ_Aircon,PUJ_Ordinary,Tricycle,Van',
            'region'         => 'required|string|max:255',
            'effective_date' => 'required|date',
            'status'         => 'nullable|string|in:active,draft,archived',
            'fares'          => 'required|array',
            'fares.*.distance_km'    => 'required|numeric|gt:0',
            'fares.*.regular_fare'   => 'required|numeric|min:0',
            'fares.*.discounted_fare'=> 'nullable|numeric|min:0',
        ]);

        $vehicleType = $request->vehicle_type;
        $region      = $request->region;
        $status      = $request->status ?? 'active';

        // Enforce Municipal role constraints:
        if (in_array($role, User::$MUNICIPAL_ROLES)) {
            if ($vehicleType !== 'Tricycle') {
                return response()->json(['success' => false, 'error' => 'Municipal users can only create Tricycle fare guides.'], 403);
            }
            $user = User::find($userId);
            if ($user && $user->municipality) {
                $region = $user->municipality->name;
            }
        }

        // Check for duplicate distances in the request
        $distances = [];
        foreach ($request->fares as $fare) {
            $dist = strval($fare['distance_km']);
            if (in_array($dist, $distances)) {
                return response()->json(['success' => false, 'error' => "Duplicate distance row: {$dist} km."], 400);
            }
            $distances[] = $dist;
        }

        DB::beginTransaction();
        try {
            if ($status === 'active') {
                FareGuide::where('vehicle_type', $vehicleType)
                    ->where('region', $region)
                    ->where('status', 'active')
                    ->update(['status' => 'archived', 'updated_at' => now()]);
            }

            $guide = FareGuide::create([
                'title'          => $request->title,
                'vehicle_type'   => $vehicleType,
                'region'         => $region,
                'effective_date' => $request->effective_date,
                'status'         => $status,
                'created_by'     => $userId,
            ]);

            foreach ($request->fares as $fare) {
                $regular = (float) $fare['regular_fare'];
                $discounted = isset($fare['discounted_fare']) ? (float) $fare['discounted_fare'] : round($regular * 0.8, 2);
                FareMatrix::create([
                    'fare_guide_id'   => $guide->id,
                    'distance_km'     => (float) $fare['distance_km'],
                    'regular_fare'    => $regular,
                    'discounted_fare' => $discounted,
                ]);
            }

            DB::commit();

            ActivityLogService::log(
                ActivityAction::FARE_UPLOADED,
                'Fare Data',
                "Fare guide manually created: \"{$guide->title}\"",
                null,
                ['guide_id' => $guide->id, 'title' => $guide->title],
                $request
            );

            $this->clearFareDataCaches($guide->id);

            return response()->json(['success' => true, 'fare_guide_id' => $guide->id]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** PUT /api/pitco/fare-data/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        $role = $request->session()->get('user_role');
        if ($role !== 'picto' && $role !== 'pitco') {
            return response()->json(['error' => 'Forbidden: Only PICTO users can edit fare matrices.'], 403);
        }

        $request->validate([
            'title'          => 'required|string|max:255',
            'vehicle_type'   => 'required|string|in:PUB_Aircon,PUB_Ordinary,PUJ_Aircon,PUJ_Ordinary,Tricycle,Van',
            'region'         => 'required|string|max:255',
            'effective_date' => 'required|date',
            'status'         => 'nullable|string|in:active,draft,archived',
            'fares'          => 'required|array',
            'fares.*.distance_km'    => 'required|numeric|gt:0',
            'fares.*.regular_fare'   => 'required|numeric|min:0',
            'fares.*.discounted_fare'=> 'nullable|numeric|min:0',
        ]);

        $guide = FareGuide::find($id);
        if (!$guide) {
            return response()->json(['success' => false, 'error' => 'Fare guide not found.'], 404);
        }

        // Check for duplicate distances in the request
        $distances = [];
        foreach ($request->fares as $fare) {
            $dist = strval($fare['distance_km']);
            if (in_array($dist, $distances)) {
                return response()->json(['success' => false, 'error' => "Duplicate distance row: {$dist} km."], 400);
            }
            $distances[] = $dist;
        }

        DB::beginTransaction();
        try {
            $status = $request->status ?? $guide->status;

            if ($status === 'active' && $guide->status !== 'active') {
                FareGuide::where('vehicle_type', $request->vehicle_type)
                    ->where('region', $request->region)
                    ->where('status', 'active')
                    ->where('id', '!=', $id)
                    ->update(['status' => 'archived', 'updated_at' => now()]);
            }

            $guide->update([
                'title'          => $request->title,
                'vehicle_type'   => $request->vehicle_type,
                'region'         => $request->region,
                'effective_date' => $request->effective_date,
                'status'         => $status,
            ]);

            FareMatrix::where('fare_guide_id', $id)->delete();

            foreach ($request->fares as $fare) {
                $regular = (float) $fare['regular_fare'];
                $discounted = isset($fare['discounted_fare']) ? (float) $fare['discounted_fare'] : round($regular * 0.8, 2);
                FareMatrix::create([
                    'fare_guide_id'   => $id,
                    'distance_km'     => (float) $fare['distance_km'],
                    'regular_fare'    => $regular,
                    'discounted_fare' => $discounted,
                ]);
            }

            DB::commit();

            ActivityLogService::log(
                ActivityAction::FARE_UPDATED,
                'Fare Data',
                "Fare guide manually updated: \"{$guide->title}\"",
                null,
                ['guide_id' => $guide->id, 'title' => $guide->title],
                $request
            );

            $this->clearFareDataCaches($id);

            return response()->json(['success' => true, 'fare_guide_id' => $id]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** POST /api/{role}/fare-data/sync  – activate / archive / draft */
    public function sync(Request $request): JsonResponse
    {
        $request->validate([
            'guide_id' => 'required|integer',
            'status'   => 'required|in:active,archived,draft',
        ]);

        $guideId = $request->guide_id;
        $status  = $request->status;
        $userId  = (int) $request->session()->get('user_id');
        $role    = $request->session()->get('user_role');
        $municipalityId = (int) $request->session()->get('user_municipality_id', 0);

        // Municipal can only manage Tricycle guides from their own municipality
        if (in_array($role, User::$MUNICIPAL_ROLES) && $municipalityId) {
            $exists = FareGuide::where('id', $guideId)
                ->where('vehicle_type', 'Tricycle')
                ->whereHas('creator', fn($q) => $q->where('municipality_id', $municipalityId))
                ->exists();
            if (!$exists) {
                return response()->json(['error' => 'Forbidden: You can only manage Tricycle fare guides from your municipality.'], 403);
            }
        }

        if ($status === 'archived') {
            FareGuide::where('id', $guideId)->update([
                'status'     => 'archived',
                'updated_at' => now(),
            ]);
        } else {
            FareGuide::where('id', $guideId)->update([
                'status'     => $status,
                'updated_at' => now(),
            ]);

            // Auto-archive conflicting active guides
            if ($status === 'active') {
                $guide = FareGuide::find($guideId);
                FareGuide::where('id', '!=', $guideId)
                    ->where('vehicle_type', $guide->vehicle_type)
                    ->where('region', $guide->region)
                    ->where('status', 'active')
                    ->update([
                        'status'     => 'archived',
                        'updated_at' => now(),
                    ]);
            }
        }

        ActivityLogService::log(
            ActivityAction::FARE_UPDATED,
            'Fare Data',
            "Fare guide #{$guideId} status changed to {$status}",
            null,
            ['guide_id' => $guideId, 'status' => $status],
            $request
        );

        $this->clearFareDataCaches($guideId);

        return response()->json(['success' => true, 'fare_guide_id' => $guideId, 'status' => $status]);
    }

    /** DELETE /api/pitco/fare-data/{id}  – PITCO only */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $guide = FareGuide::find($id);
        $guideTitle = $guide ? $guide->title : "Fare guide #{$id}";

        DB::transaction(function () use ($id) {
            FareMatrix::where('fare_guide_id', $id)->delete();
            FareGuide::where('id', $id)->delete();
        });

        ActivityLogService::log(
            ActivityAction::FARE_DELETED,
            'Fare Data',
            "Fare guide \"{$guideTitle}\" deleted",
            ['guide_id' => $id, 'title' => $guideTitle],
            null,
            $request
        );

        $this->clearFareDataCaches($id);

        return response()->json(['success' => true]);
    }

    private function clearFareDataCaches(?int $guideId = null): void
    {
        \App\Services\CacheInvalidationService::invalidateFareData($guideId);
    }
}

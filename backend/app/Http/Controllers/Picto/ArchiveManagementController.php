<?php

namespace App\Http\Controllers\Picto;

use App\Http\Controllers\Controller;
use App\Models\FareGuide;
use App\Models\FareMatrix;
use Illuminate\Http\JsonResponse;
use App\Services\CacheInvalidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ArchiveManagementController extends Controller
{
    /** GET /api/Picto/archive/stats */
    public function stats(): JsonResponse
    {
        $row = FareGuide::where('status', 'archived')
            ->selectRaw("COUNT(*) as total_archived, SUM(updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as archived_this_month")
            ->first();

        return response()->json([
            'success'             => true,
            'total_archived'      => (int) $row->total_archived,
            'archived_this_month' => (int) $row->archived_this_month,
            'modules'             => [
                ['module' => 'Transportation Fare', 'count' => (int) $row->total_archived, 'icon' => 'fa-bus'],
            ],
        ]);
    }

    /** GET /api/Picto/archive/fares */
    public function archivedFares(Request $request): JsonResponse
    {
        $search  = $request->get('search', '');
        $vehicle = $request->get('vehicle_type', '');
        $sort    = $request->get('sort', 'newest');

        $orderBy = match ($sort) {
            'oldest' => 'fg.updated_at ASC',
            'title'  => 'fg.title ASC',
            default  => 'fg.updated_at DESC',
        };

        $query = FareGuide::from('fare_guides as fg')
            ->where('fg.status', 'archived')
            ->leftJoin('users as creator',  'creator.id',  '=', 'fg.created_by')
            ->selectRaw("
                fg.id, fg.title, fg.vehicle_type, fg.region, fg.effective_date,
                fg.plate_number, fg.status, fg.created_at, fg.updated_at as archived_at,
                COALESCE(creator.name, 'Deleted User')  as created_by_name,
                '' as archived_by_name,
                (SELECT COUNT(*) FROM fare_matrices fm WHERE fm.fare_guide_id = fg.id) as matrix_count,
                (SELECT MIN(regular_fare) FROM fare_matrices fm WHERE fm.fare_guide_id = fg.id) as min_fare,
                (SELECT MAX(regular_fare) FROM fare_matrices fm WHERE fm.fare_guide_id = fg.id) as max_fare
            ");

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(fn($q) => $q->where('fg.title', 'like', "%{$search}%")->orWhere('fg.region', 'like', "%{$search}%"));
        }

        if ($request->filled('vehicle_type')) {
            $query->where('fg.vehicle_type', $request->get('vehicle_type'));
        }

        $guides = $query->orderByRaw($orderBy)->get();

        return response()->json(['success' => true, 'fare_guides' => $guides]);
    }

    /** GET /api/Picto/archive/fares/{id} */
    public function archivedFareDetail(int $id): JsonResponse
    {
        $guide = FareGuide::from('fare_guides as fg')
            ->where('fg.id', $id)
            ->where('fg.status', 'archived')
            ->leftJoin('users as creator',  'creator.id',  '=', 'fg.created_by')
            ->selectRaw("fg.*, COALESCE(creator.name,'Deleted User') as created_by_name, '' as archived_by_name")
            ->first();

        if (!$guide) return response()->json(['error' => 'Archived guide not found.'], 404);

        $matrices = FareMatrix::where('fare_guide_id', $id)->orderBy('distance_km')->get();

        return response()->json(['success' => true, 'guide' => $guide, 'fare_matrices' => $matrices]);
    }

    /** POST /api/Picto/archive/fares/{id}/restore */
    public function restore(int $id): JsonResponse
    {
        $guide = FareGuide::where('id', $id)->where('status', 'archived')->first();
        if (!$guide) return response()->json(['error' => 'Archived guide not found.'], 404);

        $guide->update([
            'status'          => 'draft',
        ]);

        CacheInvalidationService::invalidateAll();

        return response()->json([
            'success'  => true,
            'guide_id' => $id,
            'message'  => 'Fare guide restored as Draft. Activate it from the Transportation Fare page.',
        ]);
    }

    /** DELETE /api/Picto/archive/fares/{id} */
    public function permanentDelete(int $id): JsonResponse
    {
        $guide = FareGuide::where('id', $id)->where('status', 'archived')->first();
        if (!$guide) return response()->json(['error' => 'Only archived guides can be permanently deleted.'], 403);

        DB::transaction(function () use ($id) {
            FareMatrix::where('fare_guide_id', $id)->delete();
            FareGuide::where('id', $id)->delete();
        });

        CacheInvalidationService::invalidateAll();

        return response()->json(['success' => true, 'guide_id' => $id]);
    }
}

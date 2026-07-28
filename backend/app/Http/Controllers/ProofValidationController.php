<?php

namespace App\Http\Controllers;

use App\Models\ItineraryItem;
use App\Models\Municipality;
use App\Models\User;
use App\Models\UserPoint;
use App\Services\ActivityLogService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProofValidationController extends Controller
{
    /**
     * Helper to get user role and municipality ID from session.
     */
    private function getSessionContext(Request $request): array
    {
        $role = strtolower((string) ($request->session()->get('user_role') ?? ''));
        $municipalityId = (int) ($request->session()->get('user_municipality_id') ?? 0);
        $isMunicipal = ($role === 'municipal' || str_ends_with($role, '_mto'));

        return [$role, $municipalityId, $isMunicipal];
    }

    /**
     * Build base query for itinerary items that have proof images.
     */
    private function baseQuery(Request $request)
    {
        [$role, $municipalityId, $isMunicipal] = $this->getSessionContext($request);

        $query = ItineraryItem::with([
            'itinerary.user:id,name,email,avatar',
            'touristSpot:id,name,municipality_id',
            'touristSpot.municipality:id,name',
            'reviewer:id,name',
        ])
        ->where(function ($q) {
            $q->where('is_visited', true)
              ->orWhereNotNull('proof_image');
        })
        ->whereNotNull('proof_image');

        if ($isMunicipal && $municipalityId > 0) {
            $query->whereHas('touristSpot', function ($q) use ($municipalityId) {
                $q->where('municipality_id', $municipalityId);
            });
        }

        return $query;
    }

    /**
     * GET /api/(lupto|pitco|municipal)/proof-validation/stats
     */
    public function stats(Request $request): JsonResponse
    {
        [$role, $municipalityId, $isMunicipal] = $this->getSessionContext($request);

        $base = DB::table('itinerary_items')
            ->join('tourist_spots', 'itinerary_items.tourist_spot_id', '=', 'tourist_spots.id')
            ->where(function ($q) {
                $q->where('itinerary_items.is_visited', true)
                  ->orWhereNotNull('itinerary_items.proof_image');
            })
            ->whereNotNull('itinerary_items.proof_image');

        if ($isMunicipal && $municipalityId > 0) {
            $base->where('tourist_spots.municipality_id', $municipalityId);
        }

        $total    = (clone $base)->count();
        $pending  = (clone $base)->where('itinerary_items.proof_status', 'pending')->count();
        $approved = (clone $base)->where('itinerary_items.proof_status', 'approved')->count();
        $rejected = (clone $base)->where('itinerary_items.proof_status', 'rejected')->count();

        return response()->json([
            'total'    => $total,
            'pending'  => $pending,
            'approved' => $approved,
            'rejected' => $rejected,
        ]);
    }

    /**
     * GET /api/(lupto|pitco|municipal)/proof-validation
     */
    public function index(Request $request): JsonResponse
    {
        [$role, $municipalityId, $isMunicipal] = $this->getSessionContext($request);

        $query = $this->baseQuery($request);

        // Filter by municipality if passed and user is LUPTO/PICTO
        if (!$isMunicipal && $request->filled('municipality_id')) {
            $mId = (int) $request->get('municipality_id');
            $query->whereHas('touristSpot', function ($q) use ($mId) {
                $q->where('municipality_id', $mId);
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $status = strtolower($request->get('status'));
            if (in_array($status, ItineraryItem::$VALID_STATUSES)) {
                $query->where('proof_status', $status);
            }
        }

        // Filter by date range (visited_at or created_at)
        if ($request->filled('date_from')) {
            $query->whereDate('visited_at', '>=', $request->get('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('visited_at', '<=', $request->get('date_to'));
        }

        // Filter by search query (tourist name, spot name, or municipality name)
        if ($request->filled('search')) {
            $search = '%' . trim($request->get('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->whereHas('itinerary.user', function ($uq) use ($search) {
                    $uq->where('name', 'like', $search);
                })
                ->orWhereHas('touristSpot', function ($sq) use ($search) {
                    $sq->where('name', 'like', $search)
                       ->orWhereHas('municipality', function ($mq) use ($search) {
                           $mq->where('name', 'like', $search);
                       });
                });
            });
        }

        $perPage = min((int) $request->get('per_page', 15), 100);
        $paginated = $query->orderByDesc('created_at')->paginate($perPage);

        $baseUrl = rtrim(env('APP_URL', 'http://127.0.0.1:8000'), '/');

        $items = collect($paginated->items())->map(function ($item) use ($request) {
            $proofUrl  = $this->formatImageUrl($item->proof_image, 'proofs', $request);
            $avatarUrl = $this->formatImageUrl($item->itinerary?->user?->avatar, 'avatars', $request);

            return [
                'id'               => $item->id,
                'itinerary_id'     => $item->itinerary_id,
                'tourist_name'     => $item->itinerary?->user?->name ?? 'Anonymous Tourist',
                'tourist_avatar'   => $avatarUrl,
                'tourist_email'    => $item->itinerary?->user?->email,
                'tourist_spot_id'  => $item->tourist_spot_id,
                'tourist_spot'     => $item->touristSpot?->name ?? 'Unknown Spot',
                'municipality'     => $item->touristSpot?->municipality?->name ?? 'Unknown Municipality',
                'proof_image'      => $proofUrl,
                'date_submitted'   => $item->visited_at?->format('M d, Y g:i A') ?? $item->created_at?->format('M d, Y g:i A'),
                'raw_visited_at'   => $item->visited_at?->toIso8601String() ?? $item->created_at?->toIso8601String(),
                'status'           => $item->proof_status ?? 'pending',
                'reviewed_by'      => $item->reviewer?->name ?? '—',
                'reviewed_at'      => $item->reviewed_at?->format('M d, Y g:i A') ?? '—',
                'rejection_reason' => $item->rejection_reason,
            ];
        });

        // Municipalities dropdown options
        $municipalities = Municipality::select(['id', 'name'])->orderBy('name')->get();

        return response()->json([
            'data'           => $items,
            'current_page'   => $paginated->currentPage(),
            'last_page'      => $paginated->lastPage(),
            'total'          => $paginated->total(),
            'per_page'       => $paginated->perPage(),
            'municipalities' => $municipalities,
        ]);
    }

    /**
     * GET /api/(lupto|pitco|municipal)/proof-validation/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $item = $this->baseQuery($request)->where('itinerary_items.id', $id)->first();

        if (!$item) {
            return response()->json(['error' => 'Proof submission not found.'], 404);
        }

        $proofUrl  = $this->formatImageUrl($item->proof_image, 'proofs', $request);
        $avatarUrl = $this->formatImageUrl($item->itinerary?->user?->avatar, 'avatars', $request);

        return response()->json([
            'id'               => $item->id,
            'itinerary_id'     => $item->itinerary_id,
            'tourist_name'     => $item->itinerary?->user?->name ?? 'Anonymous Tourist',
            'tourist_avatar'   => $avatarUrl,
            'tourist_email'    => $item->itinerary?->user?->email,
            'tourist_spot_id'  => $item->tourist_spot_id,
            'tourist_spot'     => $item->touristSpot?->name ?? 'Unknown Spot',
            'municipality'     => $item->touristSpot?->municipality?->name ?? 'Unknown Municipality',
            'proof_image'      => $proofUrl,
            'date_submitted'   => $item->visited_at?->format('M d, Y g:i A') ?? $item->created_at?->format('M d, Y g:i A'),
            'status'           => $item->proof_status ?? 'pending',
            'reviewed_by'      => $item->reviewer?->name ?? '—',
            'reviewed_at'      => $item->reviewed_at?->format('M d, Y g:i A') ?? '—',
            'rejection_reason' => $item->rejection_reason,
        ]);
    }

    /**
     * POST /api/municipal/proof-validation/{id}/approve
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        [$role, $municipalityId, $isMunicipal] = $this->getSessionContext($request);
        $userId = (int) $request->session()->get('user_id');

        if (!$isMunicipal) {
            return response()->json(['error' => 'Only Municipal Officers can approve proof submissions.'], 403);
        }

        $item = $this->baseQuery($request)->where('itinerary_items.id', $id)->first();
        if (!$item) {
            return response()->json(['error' => 'Proof submission not found or not in your municipality.'], 404);
        }

        $item->update([
            'proof_status'     => 'approved',
            'reviewed_by'      => $userId,
            'reviewed_at'      => now(),
            'rejection_reason' => null,
        ]);

        // Award points to tourist if not already rewarded for this item
        $touristId = $item->itinerary?->user_id;
        if ($touristId) {
            UserPoint::firstOrCreate([
                'user_id'     => $touristId,
                'source'      => 'proof_validation_' . $item->id,
            ], [
                'points'      => 50,
                'description' => 'Proof validated for ' . ($item->touristSpot?->name ?? 'tourist spot'),
            ]);
        }

        // Notification & Activity Log
        try {
            ActivityLogService::log(
                $userId,
                'APPROVE_PROOF',
                "Approved proof image submission #{$item->id} for spot '{$item->touristSpot?->name}'"
            );
        } catch (\Exception $e) {}

        try {
            if ($touristId) {
                NotificationService::send(
                    $touristId,
                    'Proof Image Approved',
                    "Your proof submission for {$item->touristSpot?->name} has been approved! You earned 50 points."
                );
            }
        } catch (\Exception $e) {}

        return response()->json([
            'message' => 'Submission approved successfully.',
            'item'    => $item->fresh(['reviewer']),
        ]);
    }

    /**
     * POST /api/municipal/proof-validation/{id}/reject
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        [$role, $municipalityId, $isMunicipal] = $this->getSessionContext($request);
        $userId = (int) $request->session()->get('user_id');

        if (!$isMunicipal) {
            return response()->json(['error' => 'Only Municipal Officers can reject proof submissions.'], 403);
        }

        $request->validate([
            'rejection_reason' => 'required|string|min:5|max:1000',
        ]);

        $item = $this->baseQuery($request)->where('itinerary_items.id', $id)->first();
        if (!$item) {
            return response()->json(['error' => 'Proof submission not found or not in your municipality.'], 404);
        }

        $reason = trim($request->get('rejection_reason'));

        $item->update([
            'proof_status'     => 'rejected',
            'reviewed_by'      => $userId,
            'reviewed_at'      => now(),
            'rejection_reason' => $reason,
        ]);

        $touristId = $item->itinerary?->user_id;

        // Notification & Activity Log
        try {
            ActivityLogService::log(
                $userId,
                'REJECT_PROOF',
                "Rejected proof image submission #{$item->id} for spot '{$item->touristSpot?->name}'. Reason: {$reason}"
            );
        } catch (\Exception $e) {}

        try {
            if ($touristId) {
                NotificationService::send(
                    $touristId,
                    'Proof Image Rejected',
                    "Your proof submission for {$item->touristSpot?->name} was rejected. Reason: {$reason}"
                );
            }
        } catch (\Exception $e) {}

        return response()->json([
            'message' => 'Submission rejected successfully.',
            'item'    => $item->fresh(['reviewer']),
        ]);
    }

    /**
     * GET /api/images/proofs/{filename}
     */
    public function serveImage(string $filename): BinaryFileResponse
    {
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename)) {
            abort(400, 'Invalid filename');
        }

        $directories = [
            storage_path('app/public/proofs/'),
            storage_path('app/proofs/'),
            storage_path('app/public/'),
            public_path('storage/proofs/'),
            public_path('uploads/proofs/'),
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
            // Return placeholder or 404
            abort(404, 'File not found');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $imagePath);
        finfo_close($finfo);

        return response()->file($imagePath, [
            'Content-Type'  => $mime,
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }

    /**
     * Format image/avatar URLs (handles local storage files, absolute HTTP/HTTPS, Railway domains, relative storage paths).
     */
    private function formatImageUrl(?string $path, string $defaultSubdir = 'proofs', ?Request $request = null): ?string
    {
        if (empty($path)) {
            return null;
        }

        $trimmed = trim($path);
        $filename = basename($trimmed);

        $cleanPath = ltrim($trimmed, '/');
        if (str_starts_with($cleanPath, 'storage/')) {
            $relativePath = substr($cleanPath, 8);
        } else {
            $relativePath = $cleanPath;
        }

        if (!str_contains($relativePath, '/')) {
            $relativePath = $defaultSubdir . '/' . $filename;
        }

        // Check if file exists in local storage
        $localExists = file_exists(storage_path('app/public/' . $relativePath))
                    || file_exists(storage_path('app/public/' . $defaultSubdir . '/' . $filename))
                    || file_exists(public_path('storage/' . $relativePath));

        // Determine current host from active request or APP_URL env
        $currentHost = null;
        if ($request) {
            $currentHost = $request->schemeAndHttpHost();
        }
        if (!$currentHost || $currentHost === 'http://' || $currentHost === 'https://') {
            $currentHost = rtrim(env('APP_URL', 'http://127.0.0.1:8000'), '/');
        }
        if (!str_starts_with($currentHost, 'http://') && !str_starts_with($currentHost, 'https://')) {
            $currentHost = 'http://' . $currentHost;
        }

        if ($localExists) {
            return $currentHost . '/api/images/proofs/' . $filename;
        }

        // 1. If already full http:// or https:// URL
        if (preg_match('/^https?:\/\//i', $trimmed)) {
            return $trimmed;
        }

        // 2. If starts with a railway domain name without scheme (e.g. intanelyuweb... or intanelyumobile...)
        if (preg_match('/^[a-z0-9\.\-]+\.up\.railway\.app/i', $trimmed) || preg_match('/^[a-z0-9\.\-]+\.railway\.app/i', $trimmed)) {
            return 'https://' . ltrim($trimmed, '/');
        }

        // 3. Mobile Railway backend fallback
        $mobileBackendHost = 'https://intanelyumobile-production.up.railway.app';
        return $mobileBackendHost . '/storage/' . ltrim($relativePath, '/');
    }
}

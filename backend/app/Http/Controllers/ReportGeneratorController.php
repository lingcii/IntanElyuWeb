<?php

namespace App\Http\Controllers;

use App\Models\Municipality;
use App\Models\SiteFeedback;
use App\Models\TouristSpot;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportGeneratorController extends Controller
{
    /**
     * Helper: Resolve current user's role
     */
    private function getUserRole(): string
    {
        return session('user_role') ?: (auth()->check() ? auth()->user()->role : 'guest');
    }

    /**
     * Helper: Check if current role is Municipal Tourist Office
     */
    private function isMunicipalRole(): bool
    {
        $role = $this->getUserRole();
        return $role === 'municipal' || str_ends_with($role, '_mto');
    }

    /**
     * Helper: Resolve assigned municipality ID for municipal role
     */
    private function getAssignedMunicipalityId(): ?int
    {
        $muniId = session('user_municipality_id');
        if ($muniId) {
            return (int) $muniId;
        }

        if (auth()->check() && auth()->user()->municipality_id) {
            return (int) auth()->user()->municipality_id;
        }

        $muniName = session('user_municipality_name');
        if ($muniName) {
            $muni = Municipality::where('name', 'LIKE', "%{$muniName}%")->first();
            if ($muni) {
                return $muni->id;
            }
        }

        return null;
    }

    /**
     * Helper: Get assigned municipality name for current user
     */
    private function getAssignedMunicipalityName(): string
    {
        $muniName = session('user_municipality_name');
        if ($muniName) {
            return $muniName;
        }

        $muniId = $this->getAssignedMunicipalityId();
        if ($muniId) {
            $muni = Municipality::find($muniId);
            if ($muni) {
                return $muni->name;
            }
        }

        return 'Assigned Municipality';
    }

    /**
     * GET /api/{role}/reports
     * Returns recent report generation logs or metadata overview
     */
    public function index(Request $request): JsonResponse
    {
        $isMuni = $this->isMunicipalRole();
        $muniName = $isMuni ? $this->getAssignedMunicipalityName() : 'All Municipalities';

        $reports = [
            [
                'id' => 1,
                'report_name' => 'Tourist Spots Summary - ' . $muniName,
                'type' => 'Tourist Spots Summary',
                'generated_at' => now()->subHours(2)->format('Y-m-d H:i:s'),
                'generated_by' => strtoupper($this->getUserRole()) . ' User',
                'format' => 'PDF',
                'municipality' => $muniName,
            ],
            [
                'id' => 2,
                'report_name' => 'Visitor Feedback & Spot Ratings',
                'type' => 'Visitor Feedback Summary',
                'generated_at' => now()->subDay()->format('Y-m-d H:i:s'),
                'generated_by' => strtoupper($this->getUserRole()) . ' User',
                'format' => 'Excel',
                'municipality' => $muniName,
            ],
            [
                'id' => 3,
                'report_name' => 'Tourism Statistics Report',
                'type' => 'Tourism Statistics',
                'generated_at' => now()->subDays(3)->format('Y-m-d H:i:s'),
                'generated_by' => strtoupper($this->getUserRole()) . ' User',
                'format' => 'CSV',
                'municipality' => $muniName,
            ],
        ];

        return response()->json([
            'success' => true,
            'role' => $this->getUserRole(),
            'is_municipal' => $isMuni,
            'assigned_municipality' => $isMuni ? $muniName : null,
            'assigned_municipality_id' => $isMuni ? $this->getAssignedMunicipalityId() : null,
            'reports' => $reports,
        ]);
    }

    /**
     * GET /api/{role}/reports/generate
     * Generates report data dynamically with filters and summary metrics for preview
     */
    public function generate(Request $request): JsonResponse
    {
        $reportData = $this->buildReportData($request);
        return response()->json(['success' => true] + $reportData);
    }

    /**
     * GET /api/{role}/reports/export
     * Handles file exports (PDF, Excel, CSV)
     */
    public function export(Request $request)
    {
        $format = strtolower($request->get('format', 'pdf'));
        $reportData = $this->buildReportData($request);

        $fileName = 'Tourist_Spots_Report_' . date('Y-m-d');

        if ($format === 'csv') {
            return $this->exportCsv($reportData, $fileName);
        } elseif ($format === 'excel' || $format === 'xlsx') {
            return $this->exportExcel($reportData, $fileName);
        } else {
            return $this->exportPdf($reportData, $fileName);
        }
    }

    /**
     * Core Data Builder: Executes filtered queries & computes summary statistics with RBAC
     */
    private function buildReportData(Request $request): array
    {
        $isMuni = $this->isMunicipalRole();
        $userRole = $this->getUserRole();
        $assignedMuniId = $this->getAssignedMunicipalityId();
        $assignedMuniName = $this->getAssignedMunicipalityName();

        $requestedMuni = $request->get('municipality', 'all');
        $reportType = $request->get('report_type', 'tourist_spots_summary');
        $startDate = $request->get('start_date', '');
        $endDate = $request->get('end_date', '');

        $cacheKey = "report_data:v3:{$userRole}:" . md5(json_encode([
            'is_muni' => $isMuni,
            'muni_id' => $assignedMuniId,
            'req_muni' => $requestedMuni,
            'type' => $reportType,
            'start' => $startDate,
            'end' => $endDate,
        ]));

        if ($request->has('refresh') || $request->has('nocache')) {
            \Illuminate\Support\Facades\Cache::forget($cacheKey);
        }

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 300, function () use (
            $request, $isMuni, $userRole, $assignedMuniId, $assignedMuniName, $requestedMuni, $reportType, $startDate, $endDate
        ) {
            // 1. Resolve Municipality Filter
            $muniId = null;
            $muniLabel = 'All Municipalities';

            if ($isMuni) {
                // STRICT RBAC: Lock Municipal users to their assigned municipality
                $muniId = $assignedMuniId;
                $muniLabel = $assignedMuniName;
            } elseif ($requestedMuni && $requestedMuni !== 'all' && $requestedMuni !== '0') {
                if (is_numeric($requestedMuni)) {
                    $muniObj = Municipality::find((int)$requestedMuni);
                    if ($muniObj) {
                        $muniId = $muniObj->id;
                        $muniLabel = $muniObj->name;
                    }
                } else {
                    $muniObj = Municipality::where('name', 'LIKE', "%{$requestedMuni}%")->first();
                    if ($muniObj) {
                        $muniId = $muniObj->id;
                        $muniLabel = $muniObj->name;
                    } else {
                        $muniLabel = ucfirst($requestedMuni);
                    }
                }
            }

            // 2. Resolve Report Type & Title
            $typeTitles = [
                'all_summary'                  => 'Comprehensive All-in-One Tourism Master Summary Report',
                'tourist_spots_summary'        => 'Tourist Spots Summary Report',
                'tourist_spots_by_municipality' => 'Tourist Spots by Municipality Report',
                'visitor_feedback_summary'      => 'Visitor Feedback & Ratings Summary',
                'tourist_spot_ratings'          => 'Tourist Spot Performance & Ratings',
                'tourism_statistics'           => 'Tourism Statistics & Analytics Overview',
                'user_accounts_summary'         => 'User Accounts Summary Report',
            ];
            $reportTitle = $typeTitles[$reportType] ?? 'Custom Tourism Management Report';

            $category = $request->get('category', 'all');
            $classification = $request->get('classification', 'all');
            $status = $request->get('status', 'all');

            // Branch Logic based on Report Category
            if ($reportType === 'all_summary') {
                return $this->buildAllSummaryReport($userRole, $isMuni, $muniId, $muniLabel, $startDate, $endDate);
            }

            if ($reportType === 'user_accounts_summary') {
                return $this->buildUserAccountsReport($userRole, $isMuni, $muniId, $muniLabel, $startDate, $endDate);
            }

            if ($reportType === 'visitor_feedback_summary') {
                return $this->buildFeedbackReport($userRole, $isMuni, $muniId, $muniLabel, $startDate, $endDate, $category);
            }

            return $this->buildTouristSpotsReport(
                $reportTitle,
                $reportType,
                $userRole,
                $isMuni,
                $muniId,
                $muniLabel,
                $category,
                $classification,
                $status,
                $startDate,
                $endDate
            );
        });
    }

    /**
     * Build Tourist Spot based report data
     */
    private function buildTouristSpotsReport(
        string $reportTitle,
        string $reportType,
        string $userRole,
        bool $isMuni,
        ?int $muniId,
        string $muniLabel,
        string $category,
        string $classification,
        string $status,
        ?string $startDate,
        ?string $endDate
    ): array {
        $query = DB::table('tourist_spots')
            ->leftJoin('municipalities', 'tourist_spots.municipality_id', '=', 'municipalities.id')
            ->leftJoin('site_feedbacks', 'tourist_spots.id', '=', 'site_feedbacks.tourist_spot_id')
            ->selectRaw("
                tourist_spots.id,
                tourist_spots.name,
                tourist_spots.municipality_id,
                COALESCE(municipalities.name, 'Unassigned') as municipality_name,
                COALESCE(tourist_spots.barangay, 'N/A') as barangay,
                tourist_spots.category,
                tourist_spots.classification_status,
                tourist_spots.status,
                tourist_spots.rating,
                tourist_spots.visits,
                tourist_spots.created_at,
                tourist_spots.approved_at,
                COUNT(site_feedbacks.id) as total_reviews
            ")
            ->groupBy(
                'tourist_spots.id',
                'tourist_spots.name',
                'tourist_spots.municipality_id',
                'municipalities.name',
                'tourist_spots.barangay',
                'tourist_spots.category',
                'tourist_spots.classification_status',
                'tourist_spots.status',
                'tourist_spots.rating',
                'tourist_spots.visits',
                'tourist_spots.created_at',
                'tourist_spots.approved_at'
            );

        // Apply Municipality Filter (Strict for Municipal users)
        if ($isMuni && $muniId) {
            $query->where('tourist_spots.municipality_id', $muniId);
        } elseif ($muniId) {
            $query->where('tourist_spots.municipality_id', $muniId);
        }

        // Apply Category Filter
        if ($category && $category !== 'all') {
            $query->where('tourist_spots.category', $category);
        }

        // Apply Classification Filter
        if ($classification && $classification !== 'all') {
            $classMap = [
                'EXISTING' => ['EXISTING', 'EXIST'],
                'EMERGING' => ['EMERGING', 'EMERGE'],
                'POTENTIAL' => ['POTENTIAL'],
            ];
            $searchVal = strtoupper($classification);
            if (isset($classMap[$searchVal])) {
                $query->whereIn('tourist_spots.classification_status', $classMap[$searchVal]);
            } else {
                $query->where('tourist_spots.classification_status', $classification);
            }
        }

        // Apply Status Filter
        if ($status && $status !== 'all') {
            $query->where('tourist_spots.status', strtolower($status));
        }

        // Apply Date Range Filter
        if ($startDate) {
            $query->where('tourist_spots.created_at', '>=', $startDate . ' 00:00:00');
        }
        if ($endDate) {
            $query->where('tourist_spots.created_at', '<=', $endDate . ' 23:59:59');
        }

        $items = $query->orderBy('tourist_spots.name', 'asc')->get();

        // Standardize Classifications and Status Display
        $processedData = [];
        $totalApproved = 0;
        $totalPending  = 0;
        $totalRejected = 0;
        $totalDraft    = 0;
        $sumRatings    = 0;
        $totalReviewsSum = 0;

        $muniCounts  = [];
        $catCounts   = [];
        $classCounts = [];

        foreach ($items as $item) {
            $st = strtolower($item->status);
            if ($st === 'approved') $totalApproved++;
            elseif ($st === 'pending') $totalPending++;
            elseif ($st === 'rejected') $totalRejected++;
            elseif ($st === 'draft') $totalDraft++;

            $cStatus = strtoupper($item->classification_status ?? 'EXISTING');
            if (in_array($cStatus, ['EXIST', 'EXISTING'])) $cStatus = 'Existing';
            elseif (in_array($cStatus, ['EMERGE', 'EMERGING'])) $cStatus = 'Emerging';
            elseif ($cStatus === 'POTENTIAL') $cStatus = 'Potential';

            $rating = floatval($item->rating ?? 0);
            $sumRatings += $rating;
            $reviews = intval($item->total_reviews ?? 0);
            $totalReviewsSum += $reviews;

            $mName = $item->municipality_name ?: 'Unassigned';
            $catName = $item->category ?: 'Others';

            $muniCounts[$mName] = ($muniCounts[$mName] ?? 0) + 1;
            $catCounts[$catName] = ($catCounts[$catName] ?? 0) + 1;
            $classCounts[$cStatus] = ($classCounts[$cStatus] ?? 0) + 1;

            $created = $item->created_at ? date('Y-m-d', strtotime($item->created_at)) : 'N/A';

            $processedData[] = [
                'id'             => $item->id,
                'name'           => $item->name,
                'municipality'   => $mName,
                'barangay'       => $item->barangay,
                'category'       => $catName,
                'classification' => $cStatus,
                'status'         => ucfirst($st),
                'rating'         => number_format($rating, 1),
                'total_reviews'  => $reviews,
                'visits'         => intval($item->visits ?? 0),
                'date_created'   => $created,
                'last_updated'   => $item->approved_at ? date('Y-m-d', strtotime($item->approved_at)) : $created,
            ];
        }

        $totalSpots = count($processedData);
        $avgRating = $totalSpots > 0 ? round($sumRatings / $totalSpots, 1) : 0.0;

        return [
            'report_title'     => $reportTitle,
            'report_type'      => $reportType,
            'date_generated'   => date('F j, Y, g:i A'),
            'generated_by'     => strtoupper($userRole) . ' Officer',
            'municipality'     => $muniLabel,
            'filters'          => [
                'municipality'   => $muniLabel,
                'category'       => $category !== 'all' ? $category : 'All Categories',
                'classification' => $classification !== 'all' ? $classification : 'All Classifications',
                'status'         => $status !== 'all' ? ucfirst($status) : 'All Statuses',
                'start_date'     => $startDate ?: 'N/A',
                'end_date'       => $endDate ?: 'N/A',
            ],
            'summary_stats'    => [
                'total_spots'              => $totalSpots,
                'total_approved'           => $totalApproved,
                'total_pending'            => $totalPending,
                'total_rejected'           => $totalRejected,
                'total_draft'              => $totalDraft,
                'avg_rating'               => $avgRating,
                'total_feedback_received' => $totalReviewsSum,
                'municipality_breakdown'   => $muniCounts,
                'category_breakdown'       => $catCounts,
                'classification_breakdown' => $classCounts,
            ],
            'columns' => [
                ['key' => 'name',           'label' => 'Tourist Spot Name'],
                ['key' => 'municipality',   'label' => 'Municipality'],
                ['key' => 'barangay',       'label' => 'Barangay'],
                ['key' => 'category',       'label' => 'Category'],
                ['key' => 'classification', 'label' => 'Classification'],
                ['key' => 'status',         'label' => 'Status'],
                ['key' => 'rating',         'label' => 'Avg Rating'],
                ['key' => 'total_reviews',  'label' => 'Total Reviews'],
                ['key' => 'date_created',   'label' => 'Date Created'],
            ],
            'data' => $processedData,
        ];
    }

    /**
     * Build Visitor Feedback Report
     */
    private function buildFeedbackReport(
        string $userRole,
        bool $isMuni,
        ?int $muniId,
        string $muniLabel,
        ?string $startDate,
        ?string $endDate,
        string $category
    ): array {
        $query = DB::table('site_feedbacks')
            ->join('tourist_spots', 'site_feedbacks.tourist_spot_id', '=', 'tourist_spots.id')
            ->leftJoin('municipalities', 'tourist_spots.municipality_id', '=', 'municipalities.id')
            ->leftJoin('users', 'site_feedbacks.user_id', '=', 'users.id')
            ->selectRaw("
                site_feedbacks.id,
                tourist_spots.name as spot_name,
                COALESCE(municipalities.name, 'Unassigned') as municipality_name,
                tourist_spots.category,
                COALESCE(users.name, 'Tourist User') as user_name,
                site_feedbacks.rating,
                COALESCE(site_feedbacks.testimony, 'No written feedback') as comment,
                site_feedbacks.cleanliness_level,
                site_feedbacks.safety_level,
                site_feedbacks.created_at
            ");

        if ($isMuni && $muniId) {
            $query->where('tourist_spots.municipality_id', $muniId);
        } elseif ($muniId) {
            $query->where('tourist_spots.municipality_id', $muniId);
        }

        if ($category && $category !== 'all') {
            $query->where('tourist_spots.category', $category);
        }

        if ($startDate) {
            $query->where('site_feedbacks.created_at', '>=', $startDate . ' 00:00:00');
        }
        if ($endDate) {
            $query->where('site_feedbacks.created_at', '<=', $endDate . ' 23:59:59');
        }

        $items = $query->orderBy('site_feedbacks.created_at', 'desc')->get();

        $processedData = [];
        $ratingCounts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $sumRatings = 0;

        foreach ($items as $item) {
            $r = (int) round($item->rating);
            if (isset($ratingCounts[$r])) {
                $ratingCounts[$r]++;
            }
            $sumRatings += $item->rating;

            $processedData[] = [
                'id'            => $item->id,
                'name'          => $item->spot_name,
                'municipality'  => $item->municipality_name,
                'category'      => $item->category,
                'user_name'     => $item->user_name,
                'rating'        => number_format($item->rating, 1) . ' ⭐',
                'comment'       => $item->comment,
                'safety_level'  => $item->safety_level ?: 'N/A',
                'cleanliness'   => $item->cleanliness_level ?: 'N/A',
                'date_created'  => $item->created_at ? date('Y-m-d H:i', strtotime($item->created_at)) : 'N/A',
            ];
        }

        $totalFeedback = count($processedData);
        $avgRating = $totalFeedback > 0 ? round($sumRatings / $totalFeedback, 1) : 0.0;

        return [
            'report_title'   => 'Visitor Feedback & Spot Ratings Summary',
            'report_type'    => 'visitor_feedback_summary',
            'date_generated' => date('F j, Y, g:i A'),
            'generated_by'   => strtoupper($userRole) . ' Officer',
            'municipality'   => $muniLabel,
            'filters'        => [
                'municipality' => $muniLabel,
                'category'     => $category !== 'all' ? $category : 'All Categories',
                'start_date'   => $startDate ?: 'N/A',
                'end_date'     => $endDate ?: 'N/A',
            ],
            'summary_stats'  => [
                'total_spots'              => count(array_unique(array_column($processedData, 'name'))),
                'total_approved'           => $totalFeedback,
                'total_pending'            => 0,
                'total_rejected'           => 0,
                'total_draft'              => 0,
                'avg_rating'               => $avgRating,
                'total_feedback_received' => $totalFeedback,
                'rating_breakdown'         => $ratingCounts,
            ],
            'columns' => [
                ['key' => 'name',         'label' => 'Tourist Spot'],
                ['key' => 'municipality', 'label' => 'Municipality'],
                ['key' => 'category',     'label' => 'Category'],
                ['key' => 'user_name',    'label' => 'Tourist / User'],
                ['key' => 'rating',       'label' => 'Rating'],
                ['key' => 'comment',      'label' => 'Comment / Review'],
                ['key' => 'date_created', 'label' => 'Date Submitted'],
            ],
            'data' => $processedData,
        ];
    }

    /**
     * Build User Accounts Summary Report
     */
    private function buildUserAccountsReport(
        string $userRole,
        bool $isMuni,
        ?int $muniId,
        string $muniLabel,
        ?string $startDate,
        ?string $endDate
    ): array {
        $query = DB::table('users')
            ->leftJoin('municipalities', 'users.municipality_id', '=', 'municipalities.id')
            ->selectRaw("
                users.id,
                users.name,
                users.email,
                users.role,
                users.status,
                COALESCE(municipalities.name, 'Province-Wide') as municipality_name,
                users.last_activity,
                users.created_at
            ");

        if ($isMuni && $muniId) {
            $query->where('users.municipality_id', $muniId);
        } elseif ($muniId) {
            $query->where('users.municipality_id', $muniId);
        }

        if ($startDate) {
            $query->where('users.created_at', '>=', $startDate . ' 00:00:00');
        }
        if ($endDate) {
            $query->where('users.created_at', '<=', $endDate . ' 23:59:59');
        }

        $items = $query->orderBy('users.name', 'asc')->get();

        $processedData = [];
        $activeUsers = 0;

        foreach ($items as $item) {
            $st = strtolower($item->status ?? 'active');
            if ($st === 'active') $activeUsers++;

            $processedData[] = [
                'id'            => $item->id,
                'name'          => $item->name,
                'email'         => $item->email,
                'role'          => strtoupper(str_replace('_', ' ', $item->role)),
                'municipality'  => $item->municipality_name,
                'status'        => ucfirst($st),
                'last_activity' => $item->last_activity ? date('Y-m-d H:i', strtotime($item->last_activity)) : 'Never',
                'date_created'  => $item->created_at ? date('Y-m-d', strtotime($item->created_at)) : 'N/A',
            ];
        }

        return [
            'report_title'   => 'User Accounts Summary Report',
            'report_type'    => 'user_accounts_summary',
            'date_generated' => date('F j, Y, g:i A'),
            'generated_by'   => strtoupper($userRole) . ' Officer',
            'municipality'   => $muniLabel,
            'filters'        => [
                'municipality' => $muniLabel,
                'start_date'   => $startDate ?: 'N/A',
                'end_date'     => $endDate ?: 'N/A',
            ],
            'summary_stats'  => [
                'total_spots'              => count($processedData),
                'total_approved'           => $activeUsers,
                'total_pending'            => count($processedData) - $activeUsers,
                'total_rejected'           => 0,
                'total_draft'              => 0,
                'avg_rating'               => 0,
                'total_feedback_received' => 0,
            ],
            'columns' => [
                ['key' => 'name',          'label' => 'Full Name'],
                ['key' => 'email',         'label' => 'Email Address'],
                ['key' => 'role',          'label' => 'System Role'],
                ['key' => 'municipality',  'label' => 'Assigned Jurisdiction'],
                ['key' => 'status',        'label' => 'Account Status'],
                ['key' => 'last_activity', 'label' => 'Last Login / Activity'],
            ],
            'data' => $processedData,
        ];
    }    /**
     * Stream Download CSV File
     */
    private function exportCsv(array $reportData, string $fileName)
    {
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}.csv\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($reportData) {
            $file = fopen('php://output', 'w');
            // Write UTF-8 BOM for Excel compatibility
            fputs($file, "\xEF\xBB\xBF");

            $dateGenerated = $reportData['date_generated'] ?? $reportData['generated_at'] ?? date('Y-m-d H:i:s');
            $generatedBy = $reportData['generated_by'] ?? 'System User';
            $municipality = $reportData['municipality'] ?? $reportData['filters']['municipality'] ?? 'All Municipalities';

            // Metadata Headers
            fputcsv($file, ['REPORT TITLE', $reportData['report_title'] ?? 'Tourism Management Report']);
            fputcsv($file, ['DATE GENERATED', $dateGenerated]);
            fputcsv($file, ['GENERATED BY', $generatedBy]);
            fputcsv($file, ['MUNICIPALITY SCOPE', $municipality]);
            fputcsv($file, ['FILTERS APPLIED', json_encode($reportData['filters'] ?? [])]);
            fputcsv($file, []);

            // Summary Statistics
            if (isset($reportData['summary_stats'])) {
                fputcsv($file, ['--- SUMMARY STATISTICS ---']);
                foreach ($reportData['summary_stats'] as $k => $v) {
                    if (is_scalar($v)) {
                        fputcsv($file, [strtoupper(str_replace('_', ' ', $k)), $v]);
                    }
                }
                fputcsv($file, []);
            }

            // Table Data Headers
            $colKeys = [];
            $colLabels = [];
            foreach (($reportData['columns'] ?? []) as $col) {
                $colKeys[] = $col['key'];
                $colLabels[] = $col['label'];
            }
            fputcsv($file, $colLabels);

            // Table Rows
            foreach (($reportData['data'] ?? []) as $row) {
                $line = [];
                foreach ($colKeys as $k) {
                    $line[] = $row[$k] ?? '';
                }
                fputcsv($file, $line);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Download Excel compatible spreadsheet
     */
    private function exportExcel(array $reportData, string $fileName)
    {
        return $this->exportCsv($reportData, $fileName);
    }

    /**
     * Printable PDF Report Window
     */
    private function exportPdf(array $reportData, string $fileName)
    {
        $dateGenerated = $reportData['date_generated'] ?? $reportData['generated_at'] ?? date('Y-m-d H:i:s');
        $generatedBy = $reportData['generated_by'] ?? 'System User';
        $municipality = $reportData['municipality'] ?? $reportData['filters']['municipality'] ?? 'All Municipalities';
        $categoryFilter = $reportData['filters']['category'] ?? 'All Categories';
        $statusFilter = $reportData['filters']['status'] ?? 'All Statuses';

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . htmlspecialchars($reportData['report_title'] ?? 'Tourism Management Report') . '</title><style>
            body { font-family: "Segoe UI", Arial, sans-serif; color: #0F172A; margin: 30px; font-size: 12px; }
            .header { border-bottom: 3px solid #2563EB; padding-bottom: 12px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
            .header h1 { margin: 0 0 4px; font-size: 22px; color: #1E3A8A; }
            .meta-grid { display: flex; flex-wrap: wrap; gap: 16px; background: #F8FAFC; padding: 14px; border-radius: 8px; border: 1px solid #E2E8F0; margin-bottom: 24px; }
            .meta-item { font-size: 11px; }
            .meta-item strong { color: #475569; }
            .kpi-row { display: flex; gap: 12px; margin-bottom: 24px; }
            .kpi-card { flex: 1; background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: 8px; padding: 12px; text-align: center; }
            .kpi-card h4 { margin: 0 0 4px; font-size: 10px; text-transform: uppercase; color: #1E40AF; }
            .kpi-card .val { font-size: 20px; font-weight: bold; color: #1D4ED8; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th { background: #2563EB; color: #FFFFFF; font-size: 11px; padding: 10px 12px; text-align: left; text-transform: uppercase; }
            td { padding: 9px 12px; border-bottom: 1px solid #E2E8F0; font-size: 11px; }
            tr:nth-child(even) td { background: #F8FAFC; }
            .footer { margin-top: 40px; text-align: center; font-size: 10px; color: #94A3B8; border-top: 1px solid #E2E8F0; padding-top: 12px; }
            @media print { body { margin: 15px; } .no-print { display: none !important; } }
        </style></head><body>';

        $html .= '<div class="header">';
        $html .= '<div><h1>' . htmlspecialchars($reportData['report_title'] ?? 'Tourism Management Report') . '</h1>';
        $html .= '<div>Province of La Union — Tourism Management System</div></div>';
        $html .= '<button type="button" class="no-print" onclick="window.print()" style="padding:8px 16px;background:#2563EB;color:#FFF;border:none;border-radius:6px;cursor:pointer;font-weight:600;"><i class="fas fa-print"></i> Print / Save as PDF</button>';
        $html .= '</div>';

        $html .= '<div class="meta-grid">';
        $html .= '<div class="meta-item"><strong>Date Generated:</strong> ' . htmlspecialchars($dateGenerated) . '</div>';
        $html .= '<div class="meta-item"><strong>Generated By:</strong> ' . htmlspecialchars($generatedBy) . '</div>';
        $html .= '<div class="meta-item"><strong>Municipality:</strong> ' . htmlspecialchars($municipality) . '</div>';
        $html .= '<div class="meta-item"><strong>Category Filter:</strong> ' . htmlspecialchars($categoryFilter) . '</div>';
        $html .= '<div class="meta-item"><strong>Status Filter:</strong> ' . htmlspecialchars($statusFilter) . '</div>';
        $html .= '</div>';

        if (isset($reportData['summary_stats'])) {
            $s = $reportData['summary_stats'];
            $html .= '<div class="kpi-row">';
            $html .= '<div class="kpi-card"><h4>Total Records</h4><div class="val">' . ($s['total_spots'] ?? 0) . '</div></div>';
            $html .= '<div class="kpi-card"><h4>Approved</h4><div class="val">' . ($s['total_approved'] ?? 0) . '</div></div>';
            $html .= '<div class="kpi-card"><h4>Pending</h4><div class="val">' . ($s['total_pending'] ?? 0) . '</div></div>';
            $html .= '<div class="kpi-card"><h4>Average Rating</h4><div class="val">' . ($s['avg_rating'] ?? 0) . '</div></div>';
            $html .= '</div>';
        }

        $html .= '<table><thead><tr>';
        foreach (($reportData['columns'] ?? []) as $col) {
            $html .= '<th>' . htmlspecialchars($col['label']) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach (($reportData['data'] ?? []) as $row) {
            $html .= '<tr>';
            foreach (($reportData['columns'] ?? []) as $col) {
                $k = $col['key'];
                $val = $row[$k] ?? '';
                $html .= '<td>' . htmlspecialchars((string)$val) . '</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        $html .= '<div class="footer">Generated by Intan Elyu Tourism Management System | Official Report Document</div>';
        $html .= '<script>window.onload = function() { setTimeout(function(){ window.print(); }, 400); };</script>';
        $html .= '</body></html>';

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    /**
     * Build Comprehensive All-in-One Tourism Master Summary Report
     */
    private function buildAllSummaryReport(
        string $userRole,
        bool $isMuni,
        ?int $muniId,
        string $muniLabel,
        ?string $startDate,
        ?string $endDate
    ): array {
        $spotsQuery = DB::table('tourist_spots')
            ->leftJoin('municipalities', 'tourist_spots.municipality_id', '=', 'municipalities.id')
            ->leftJoin('site_feedbacks', 'tourist_spots.id', '=', 'site_feedbacks.tourist_spot_id')
            ->selectRaw("
                tourist_spots.id,
                tourist_spots.name,
                COALESCE(municipalities.name, 'Unassigned') as municipality_name,
                tourist_spots.category,
                tourist_spots.classification_status as classification,
                tourist_spots.status,
                COALESCE(ROUND(AVG(site_feedbacks.rating), 1), 0.0) as avg_rating,
                COUNT(site_feedbacks.id) as total_feedback,
                tourist_spots.created_at
            ")
            ->groupBy(
                'tourist_spots.id',
                'tourist_spots.name',
                'municipalities.name',
                'tourist_spots.category',
                'tourist_spots.classification_status',
                'tourist_spots.status',
                'tourist_spots.created_at'
            );

        if ($isMuni && $muniId) {
            $spotsQuery->where('tourist_spots.municipality_id', $muniId);
        }

        if ($startDate && $endDate) {
            $spotsQuery->whereBetween('tourist_spots.created_at', ["{$startDate} 00:00:00", "{$endDate} 23:59:59"]);
        }

        $records = $spotsQuery->get();

        $totalSpots = $records->count();
        $totalApproved = $records->where('status', 'approved')->count();
        $totalPending = $records->where('status', 'pending')->count();
        $totalRejected = $records->where('status', 'rejected')->count();
        $totalDraft = $records->where('status', 'draft')->count();
        $avgRating = $totalSpots > 0 ? round($records->avg('avg_rating'), 1) : 0.0;
        $totalFeedback = $records->sum('total_feedback');

        $dataRows = $records->map(function ($spot) {
            return [
                'id' => 'SPOT-' . str_pad($spot->id, 4, '0', STR_PAD_LEFT),
                'name' => $spot->name,
                'municipality' => $spot->municipality_name,
                'category' => $spot->category ?? 'N/A',
                'classification' => $spot->classification ?? 'N/A',
                'status' => strtoupper($spot->status ?? 'DRAFT'),
                'avg_rating' => number_format((float)$spot->avg_rating, 1) . ' ⭐',
                'total_feedback' => (int)$spot->total_feedback,
                'created_at' => $spot->created_at ? date('Y-m-d', strtotime($spot->created_at)) : 'N/A',
            ];
        })->toArray();

        return [
            'report_title' => 'Comprehensive All-in-One Tourism Master Summary Report',
            'report_type' => 'all_summary',
            'generated_at' => date('Y-m-d H:i:s'),
            'filters' => [
                'municipality' => $muniLabel,
                'start_date' => $startDate ?: 'All Time',
                'end_date' => $endDate ?: 'All Time',
                'category' => 'All Categories',
                'status' => 'All Statuses',
            ],
            'summary_stats' => [
                'total_spots' => $totalSpots,
                'total_approved' => $totalApproved,
                'total_pending' => $totalPending,
                'total_rejected' => $totalRejected,
                'total_draft' => $totalDraft,
                'avg_rating' => $avgRating,
                'total_feedback' => $totalFeedback,
            ],
            'columns' => [
                ['key' => 'id', 'label' => 'Spot ID'],
                ['key' => 'name', 'label' => 'Tourist Spot Name'],
                ['key' => 'municipality', 'label' => 'Municipality'],
                ['key' => 'category', 'label' => 'Category'],
                ['key' => 'classification', 'label' => 'Classification'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'avg_rating', 'label' => 'Rating'],
                ['key' => 'total_feedback', 'label' => 'Feedback Count'],
                ['key' => 'created_at', 'label' => 'Date Added'],
            ],
            'data' => $dataRows,
        ];
    }
}

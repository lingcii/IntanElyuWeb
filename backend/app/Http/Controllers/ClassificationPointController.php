<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Models\Classification;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ClassificationPointController extends Controller
{
    /**
     * GET /api/classification-points
     * Get configured points for classifications.
     */
    public function index(): JsonResponse
    {
        $pointsMap = Cache::remember('classification_points', 3600, function () {
            $records = Classification::all();
            $map = [
                'EXISTING'  => 50,
                'EMERGING'  => 100,
                'POTENTIAL' => 75,
            ];
            foreach ($records as $record) {
                $key = strtoupper($record->name);
                $map[$key] = (int) $record->points;
            }
            return $map;
        });

        return response()->json([
            'success' => true,
            'points'  => $pointsMap,
        ]);
    }

    /**
     * PUT /api/lupto/classification-points
     * Update configured points for classifications (LUPTO only).
     */
    public function update(Request $request): JsonResponse
    {
        // Support both UPPERCASE and lowercase payloads
        $existing  = $request->input('EXISTING', $request->input('existing'));
        $emerging  = $request->input('EMERGING', $request->input('emerging'));
        $potential = $request->input('POTENTIAL', $request->input('potential'));

        $request->merge([
            'EXISTING'  => $existing,
            'EMERGING'  => $emerging,
            'POTENTIAL' => $potential,
        ]);

        $request->validate([
            'EXISTING'  => 'required|integer|min:0',
            'EMERGING'  => 'required|integer|min:0',
            'POTENTIAL' => 'required|integer|min:0',
        ], [
            'EXISTING.required'  => 'Existing point value is required.',
            'EXISTING.integer'   => 'Existing point value must be a whole number.',
            'EXISTING.min'       => 'Existing point value cannot be negative.',
            'EMERGING.required'  => 'Emerging point value is required.',
            'EMERGING.integer'   => 'Emerging point value must be a whole number.',
            'EMERGING.min'       => 'Emerging point value cannot be negative.',
            'POTENTIAL.required' => 'Potential point value is required.',
            'POTENTIAL.integer'  => 'Potential point value must be a whole number.',
            'POTENTIAL.min'      => 'Potential point value cannot be negative.',
        ]);

        $updates = [
            'EXISTING'  => (int) $request->input('EXISTING'),
            'EMERGING'  => (int) $request->input('EMERGING'),
            'POTENTIAL' => (int) $request->input('POTENTIAL'),
        ];

        foreach ($updates as $name => $points) {
            Classification::updateOrCreate(
                ['name' => $name],
                ['points' => $points]
            );
        }

        Cache::forget('classification_points');

        ActivityLogService::log(
            ActivityAction::SETTINGS_UPDATED,
            'Settings',
            'Updated classification default points: Existing=' . $updates['EXISTING'] . ', Emerging=' . $updates['EMERGING'] . ', Potential=' . $updates['POTENTIAL'],
            null,
            $updates,
            $request
        );

        return response()->json([
            'success' => true,
            'message' => 'Classification points updated successfully.',
            'points'  => $updates,
        ]);
    }
}

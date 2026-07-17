<?php

namespace App\Http\Controllers;

use App\Models\Municipality;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MunicipalityController extends Controller
{
    /** GET /api/{role}/municipalities */
    public function index(Request $request): JsonResponse
    {
        $municipalities = Municipality::orderBy('name')->get();
        return $this->etagResponse($request, ['municipalities' => $municipalities]);
    }

    /** GET /api/{role}/municipalities/{id} */
    public function show(int $id): JsonResponse
    {
        return response()->json(['municipality' => Municipality::findOrFail($id)]);
    }
}

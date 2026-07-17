<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * Return a JsonResponse with ETag validation.
     */
    protected function etagResponse(\Illuminate\Http\Request $request, array|object $payload): \Illuminate\Http\JsonResponse
    {
        $etag = '"' . md5(json_encode($payload)) . '"';

        if ($request->hasHeader('If-None-Match') && $request->header('If-None-Match') === $etag) {
            return response()->json(null, 304)
                ->header('Cache-Control', 'no-cache, must-revalidate')
                ->header('ETag', $etag);
        }

        return response()->json($payload)
            ->header('Cache-Control', 'no-cache, must-revalidate')
            ->header('ETag', $etag);
    }
}

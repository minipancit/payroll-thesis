<?php

namespace App\Http\Controllers;

use App\Models\UserLocation;
use Illuminate\Http\Request;

class UserLocationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        $location = UserLocation::create([
            'user_id' => $request->user()->id,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'accuracy' => $validated['accuracy'] ?? null,
            'recorded_at' => $validated['recorded_at'] ?? now(),
        ]);

        return response()->json([
            'message' => 'Location saved successfully.',
            'data' => $location,
        ], 201);
    }
}

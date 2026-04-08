<?php

use App\Http\Controllers\Api\FaceRecognitionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FaceVerifyController;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('face-auth')->group(function () {
    // Public endpoints
    Route::post('/authenticate', [FaceRecognitionController::class, 'authenticate']);
    
    // Protected endpoints (requires authentication)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/register', [FaceRecognitionController::class, 'registerFace']);
        Route::put('/update', [FaceRecognitionController::class, 'updateFace']);
        Route::get('/status', [FaceRecognitionController::class, 'checkFaceRegistration']);
    });
});

Route::get('/mobile/events', [EventController::class, 'api']);

Route::post('/verify-face', FaceVerifyController::class);

Route::post('/auth/login', [AuthController::class, 'loginWithFaceCode']);

Route::get('/debug-auth', function (Request $request) {
    return response()->json([
        'accept' => $request->header('Accept'),
        'authorization' => $request->header('Authorization'),
        'bearer' => $request->bearerToken(),
        'user' => $request->user(),
    ]);
});
Route::middleware('auth:sanctum')->group(function(){
    Route::get('/auth/session', [AuthController::class, 'session']);
    Route::get('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/user', function(Request $request){
        
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'employee_id' => $user->employee_id,
            'last_login_at' => $user->last_login_at,
            'last_login_ip' => $user->last_login_ip,
            'last_login_location' => $user->last_login_location,
            'logs' => $user->loginAttempts()
                ->where('status', 'success')
                ->latest('authenticated_at')
                ->take(30)
                ->get()
                ->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'status' => $log->status,
                        'confidence_score' => $log->confidence_score,
                        'ip_address' => $log->ip_address,
                        'latitude' => $log->latitude,
                        'longitude' => $log->longitude,
                        'device_info' => $log->device_info,
                        'user_agent' => $log->user_agent,
                        'attempted_at' => $log->attempted_at,
                        'authenticated_at' => $log->authenticated_at,
                        'formatted_date' => optional($log->authenticated_at ?? $log->attempted_at)?->format('M j, Y'),
                        'formatted_time' => optional($log->authenticated_at ?? $log->attempted_at)?->format('g:i A'),
                    ];
                }),
        ]);
    });
});

Route::get('/current-event', [EventController::class, 'api']);

Route::get('/mobile/users-with-embeddings', [FaceRecognitionController::class, 'getAllUsersWithEmbeddings']);

// Get compact embeddings format (for regular sync)
Route::get('/mobile/compact-embeddings', [FaceRecognitionController::class, 'getCompactEmbeddings']);

// Get face images for training (optional)
Route::get('/mobile/face-images', [FaceRecognitionController::class, 'getFaceImages']);

// Get sync status
Route::get('/mobile/sync-status', [FaceRecognitionController::class, 'getSyncStatus']);
<?php

use App\Http\Controllers\Api\FaceRecognitionController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FaceVerifyController;
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

Route::get('/mobile/users-with-embeddings', [FaceRecognitionController::class, 'getAllUsersWithEmbeddings']);

// Get compact embeddings format (for regular sync)
Route::get('/mobile/compact-embeddings', [FaceRecognitionController::class, 'getCompactEmbeddings']);

// Get face images for training (optional)
Route::get('/mobile/face-images', [FaceRecognitionController::class, 'getFaceImages']);

// Get sync status
Route::get('/mobile/sync-status', [FaceRecognitionController::class, 'getSyncStatus']);
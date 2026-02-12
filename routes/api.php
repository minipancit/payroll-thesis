<?php

use App\Http\Controllers\Api\FaceRecognitionController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::prefix('face-auth')->group(function () {
    Route::post('/authenticate', [FaceRecognitionController::class, 'authenticate']);
});

// Protected routes (require authentication)
Route::middleware(['auth:sanctum'])->prefix('face-auth')->group(function () {
    Route::post('/register', [FaceRecognitionController::class, 'registerFace']);
    Route::post('/update', [FaceRecognitionController::class, 'updateFace']);
    Route::post('/delete', [FaceRecognitionController::class, 'deleteFace']);
    Route::get('/check', [FaceRecognitionController::class, 'checkFaceRegistration']);
});
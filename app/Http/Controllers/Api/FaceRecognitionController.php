<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FaceRecognitionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\LoginAttempt;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class FaceRecognitionController extends Controller
{
    protected $faceRecognitionService;

    public function __construct(FaceRecognitionService $faceRecognitionService)
    {
        $this->faceRecognitionService = $faceRecognitionService;
    }

    /**
     * Authenticate using face recognition with image data
     */
    public function authenticate(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'image_data' => 'required|string',
                'location.latitude' => 'required|numeric|between:-90,90',
                'location.longitude' => 'required|numeric|between:-180,180',
                'device_info' => 'sometimes|array',
                'timestamp' => 'required|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();
            
            // Process the image and extract face embedding
            $imageData = $validated['image_data'];
            $imagePath = $this->storeTemporaryImage($imageData);
            
            if (!$imagePath) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid image data',
                ], 422);
            }

            // Generate face embedding from image
            $faceEmbedding = $this->faceRecognitionService->generateFaceEmbedding($imagePath);
            
            if (!$faceEmbedding) {
                return response()->json([
                    'success' => false,
                    'message' => 'No face detected in the image. Please ensure your face is clearly visible.',
                ], 400);
            }

            // Log the login attempt
            $loginAttempt = LoginAttempt::create([
                'face_data_hash' => hash('sha256', json_encode($faceEmbedding)),
                'latitude' => $validated['location']['latitude'],
                'longitude' => $validated['location']['longitude'],
                'device_info' => json_encode($validated['device_info'] ?? []),
                'attempted_at' => Carbon::parse($validated['timestamp']),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => 'pending',
            ]);

            // Find user by face embedding
            $user = $this->faceRecognitionService->findUserByFaceEmbedding($faceEmbedding);
            
            if (!$user) {
                $loginAttempt->update(['status' => 'failed', 'failure_reason' => 'Face not recognized']);
                
                // Clean up temporary image
                Storage::disk('local')->delete($imagePath);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Face not recognized. Please try again or use alternative login.',
                ], 401);
            }

            // Check if user is active
            if (!$user->is_active) {
                $loginAttempt->update(['status' => 'failed', 'failure_reason' => 'Account disabled']);
                
                // Clean up temporary image
                Storage::disk('local')->delete($imagePath);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Your account has been disabled. Please contact administrator.',
                ], 403);
            }

            // Verify location (optional)
            if (!$this->validateLocation($user, $validated['location'])) {
                $loginAttempt->update(['status' => 'failed', 'failure_reason' => 'Suspicious location']);
                
                // Send security alert
                $this->sendSecurityAlert($user, $validated['location'], $request->ip());
                
                // Clean up temporary image
                Storage::disk('local')->delete($imagePath);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Login attempt from unusual location. Security alert has been sent.',
                ], 403);
            }

            // Check for too many failed attempts
            if ($user->hasRecentFailedLoginAttempts()) {
                $loginAttempt->update(['status' => 'failed', 'failure_reason' => 'Too many attempts']);
                
                // Clean up temporary image
                Storage::disk('local')->delete($imagePath);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Too many failed attempts. Please try again in 15 minutes.',
                ], 429);
            }

            // Generate token
            $token = $user->createFaceRecognitionToken();
            
            // Update login attempt as successful
            $loginAttempt->update([
                'status' => 'success',
                'user_id' => $user->id,
                'authenticated_at' => now(),
            ]);

            // Record user login
            $user->recordLogin(
                $request->ip(),
                [
                    'latitude' => $validated['location']['latitude'],
                    'longitude' => $validated['location']['longitude'],
                ]
            );

            // Clear failed attempts
            $user->clearFailedLoginAttempts();

            // Store the authentication image for audit trail
            $this->storeAuthImage($user, $imageData, $faceEmbedding);

            // Clean up temporary image
            Storage::disk('local')->delete($imagePath);

            return response()->json([
                'success' => true,
                'message' => 'Authentication successful',
                'data' => [
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => 2592000, // 30 days in seconds
                    'user' => $user->shareToInertia(),
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Face recognition authentication failed: ' . $e->getMessage(), [
                'exception' => $e,
                'request_data' => $request->except(['image_data']), // Don't log full image data
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Authentication failed. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Store temporary image for processing
     */
    private function storeTemporaryImage(string $base64Image): ?string
    {
        try {
            // Extract base64 data
            if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $matches)) {
                $imageType = $matches[1];
                $imageData = substr($base64Image, strpos($base64Image, ',') + 1);
                $imageData = base64_decode($imageData);
            } else {
                // Assume it's raw base64
                $imageData = base64_decode($base64Image);
                $imageType = 'jpg';
            }

            Log::info('Temporary image stored for processing', [
                'image_type' => $imageType,
                'image_size_kb' => round(strlen($imageData) / 1024, 2),
            ]);
            if (!$imageData) {
                return null;
            }
            // Validate image size (max 5MB)
            if (strlen($imageData) > 5 * 1024 * 1024) {
                throw new \Exception('Image size exceeds 5MB limit');
            }

            $fileName = 'temp/face_auth_' . uniqid() . '.' . $imageType;
            
            Storage::disk('local')->put($fileName, $imageData);
            
            return $fileName;
            
        } catch (\Exception $e) {
            Log::error('Failed to store temporary image: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Store authentication image for audit trail
     */
    private function storeAuthImage(User $user, string $base64Image, array $embedding): void
    {
        try {
            $fileName = 'auth_logs/' . $user->id . '/' . now()->format('Y-m-d_H-i-s') . '_' . uniqid() . '.jpg';
            
            // Extract and decode base64
            $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64Image));
            
            Storage::disk('local')->put($fileName, $imageData);
            
            // You could also save this to a database table for audit trail
            Log::info('Authentication image stored', [
                'user_id' => $user->id,
                'image_path' => $fileName,
                'embedding_hash' => hash('sha256', json_encode($embedding)),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to store auth image: ' . $e->getMessage());
        }
    }

    /**
     * Register face for user with image data
     */
    public function registerFace(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'image_data' => 'required|string', // Base64 encoded image
                'confirm_image_data' => 'sometimes|string', // For double verification
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();
            $imageData = $validated['image_data'];
            
            // Store temporary image for processing
            $imagePath = $this->storeTemporaryImage($imageData);
            
            if (!$imagePath) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid image data',
                ], 422);
            }

            // Generate face embedding
            $faceEmbedding = $this->faceRecognitionService->generateFaceEmbedding($imagePath);
            
            if (!$faceEmbedding) {
                // Clean up temporary image
                Storage::disk('local')->delete($imagePath);
                
                return response()->json([
                    'success' => false,
                    'message' => 'No face detected in the image. Please ensure your face is clearly visible.',
                ], 400);
            }

            // For double verification, check second image if provided
            if (isset($validated['confirm_image_data'])) {
                $confirmImagePath = $this->storeTemporaryImage($validated['confirm_image_data']);
                
                if ($confirmImagePath) {
                    $confirmEmbedding = $this->faceRecognitionService->generateFaceEmbedding($confirmImagePath);
                    
                    if ($confirmEmbedding) {
                        $similarity = $this->faceRecognitionService->compareFaces($faceEmbedding, $confirmEmbedding);
                        
                        if ($similarity < 0.8) { // Adjust threshold as needed
                            // Clean up temporary images
                            Storage::disk('local')->delete($imagePath);
                            Storage::disk('local')->delete($confirmImagePath);
                            
                            return response()->json([
                                'success' => false,
                                'message' => 'Face images do not match. Please ensure you\'re taking photos of the same person.',
                            ], 400);
                        }
                    }
                    
                    Storage::disk('local')->delete($confirmImagePath);
                }
            }

            // Check if face already exists in database (prevent duplicate registration)
            $existingUser = $this->faceRecognitionService->findUserByFaceEmbedding($faceEmbedding);
            
            if ($existingUser && $existingUser->id !== $user->id) {
                // Clean up temporary image
                Storage::disk('local')->delete($imagePath);
                
                return response()->json([
                    'success' => false,
                    'message' => 'This face is already registered to another user.',
                ], 409);
            }

            // Store permanent face image
            $permanentImagePath = $this->storeFaceImage($user, $imageData);
            
            // Add face embedding to user
            $user->addFaceEmbedding($faceEmbedding, $permanentImagePath);
            
            // Update face registration timestamp
            $user->update([
                'face_registered_at' => now(),
                'face_data_hash' => hash('sha256', json_encode($faceEmbedding)),
            ]);

            // Clean up temporary image
            Storage::disk('local')->delete($imagePath);

            Log::info('Face data registered for user: ' . $user->id);

            return response()->json([
                'success' => true,
                'message' => 'Face data registered successfully',
                'data' => [
                    'registered_at' => $user->face_registered_at,
                    'has_face_registered' => true,
                    'image_stored' => !empty($permanentImagePath),
                ],
            ], 201);

        } catch (\Exception $e) {
            Log::error('Face registration failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Face registration failed',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Store permanent face image
     */
    private function storeFaceImage(User $user, string $base64Image): string
    {
        try {
            // Create directory if it doesn't exist
            $directory = 'faces/' . $user->id;
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }

            $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64Image));
            $fileName = $directory . '/' . uniqid() . '_' . time() . '.jpg';
            
            // Process image for better storage (resize, compress)
            $manager = new ImageManager(new Driver());
            $image = $manager->read($imageData);
            
            // Resize to max 800px width while maintaining aspect ratio
            $image->scaleDown(width: 800);
            
            // Encode as JPEG with 80% quality
            $processedImage = $image->toJpeg(80);
            
            Storage::disk('public')->put($fileName, $processedImage);
            
            return $fileName;
            
        } catch (\Exception $e) {
            Log::error('Failed to store face image: ' . $e->getMessage());
            
            // Fallback: store original image without processing
            $fileName = 'faces/' . $user->id . '/' . uniqid() . '.jpg';
            $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64Image));
            Storage::disk('public')->put($fileName, $imageData);
            
            return $fileName;
        }
    }

    /**
     * Update face data for user
     */
    public function updateFace(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            if (!$user->hasFaceRegistered()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No face data registered. Please register face first.',
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'old_image_data' => 'required|string', // Current face for verification
                'new_image_data' => 'required|string', // New face data
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verify old face data
            $oldImagePath = $this->storeTemporaryImage($request->old_image_data);
            $oldEmbedding = $this->faceRecognitionService->generateFaceEmbedding($oldImagePath);
            
            if (!$oldEmbedding || !$this->faceRecognitionService->verifyFace($user, $oldEmbedding)) {
                if ($oldImagePath) Storage::disk('local')->delete($oldImagePath);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Current face verification failed.',
                ], 401);
            }

            // Process new face data
            $newImagePath = $this->storeTemporaryImage($request->new_image_data);
            $newEmbedding = $this->faceRecognitionService->generateFaceEmbedding($newImagePath);
            
            if (!$newEmbedding) {
                Storage::disk('local')->delete($oldImagePath);
                Storage::disk('local')->delete($newImagePath);
                
                return response()->json([
                    'success' => false,
                    'message' => 'No face detected in new image.',
                ], 400);
            }

            // Store new permanent image
            $newPermanentPath = $this->storeFaceImage($user, $request->new_image_data);
            
            // Update user face data
            $user->addFaceEmbedding($newEmbedding, $newPermanentPath);
            $user->update([
                'face_updated_at' => now(),
                'face_data_hash' => hash('sha256', json_encode($newEmbedding)),
            ]);

            // Clean up temporary files
            Storage::disk('local')->delete($oldImagePath);
            Storage::disk('local')->delete($newImagePath);

            Log::info('Face data updated for user: ' . $user->id);

            return response()->json([
                'success' => true,
                'message' => 'Face data updated successfully',
            ], 200);

        } catch (\Exception $e) {
            Log::error('Face update failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Face update failed',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Check face registration status
     */
    public function checkFaceRegistration()
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            $hasFaceRegistered = $user->hasFaceRegistered();
            $primaryEmbedding = $user->primaryFaceEmbedding;
            $faceImages = $user->faceEmbeddings()->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'has_face_registered' => $hasFaceRegistered,
                    'registered_at' => $user->face_registered_at,
                    'updated_at' => $user->face_updated_at,
                    'total_face_images' => $faceImages,
                    'can_authenticate' => $hasFaceRegistered && $user->is_active,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Face registration check failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Check failed',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Validate location
     */
    private function validateLocation(User $user, array $location): bool
    {
        if (!$user->last_login_location) {
            return true; // First time login
        }

        $lastLocation = $user->last_login_location;
        
        $distance = $this->calculateDistance(
            $lastLocation['latitude'] ?? 0,
            $lastLocation['longitude'] ?? 0,
            $location['latitude'],
            $location['longitude']
        );

        // Allow if within 100km of last login location
        return $distance <= 100;
    }

    /**
     * Calculate distance between coordinates
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2): float
    {
        $earthRadius = 6371;

        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lon1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lon2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));

        return $angle * $earthRadius;
    }

    /**
     * Send security alert
     */
    private function sendSecurityAlert(User $user, array $location, string $ipAddress): void
    {
        Log::warning('Suspicious login attempt detected', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_name' => $user->name,
            'location' => $location,
            'ip_address' => $ipAddress,
            'time' => now()->toISOString(),
            'action' => 'Login blocked due to suspicious location',
        ]);

        // Here you could:
        // 1. Send email notification to admin
        // 2. Send SMS alert to user
        // 3. Log to security monitoring system
        // 4. Trigger other security protocols
    }
}
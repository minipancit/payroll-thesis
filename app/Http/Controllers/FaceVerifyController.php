<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FaceVerifyController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, User $user)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'face_image' => 'required|string',
        ]);
        
        try {
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }
            
            // Check if user has facial images registered
            if (!$user->facial_images || count($user->facial_images) === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No facial images registered for this user. Please contact administrator.'
                ], 400);
            }
            
            Log::info('Processing face verification for user: ' . $user->email . ' with ' . count($user->facial_images) . ' stored images');
            
            // Your Face++ API credentials
            $apiKey = env('FACE_PLUS_PLUS_API_KEY');
            $apiSecret = env('FACE_PLUS_PLUS_API_SECRET');
            
            // Extract and clean base64 image from request
            $submittedImageBase64 = $request->face_image;
            if (strpos($submittedImageBase64, 'base64,') !== false) {
                $submittedImageBase64 = substr($submittedImageBase64, strpos($submittedImageBase64, 'base64,') + 7);
            }
            $submittedImageBase64 = preg_replace('/\s+/', '', $submittedImageBase64);
            
            // Step 1: Detect face in the submitted image
            $detectResponse = Http::timeout(30)
                ->asMultipart()
                ->post('https://api-us.faceplusplus.com/facepp/v3/detect', [
                    [
                        'name' => 'api_key',
                        'contents' => $apiKey,
                    ],
                    [
                        'name' => 'api_secret',
                        'contents' => $apiSecret,
                    ],
                    [
                        'name' => 'image_base64',
                        'contents' => $submittedImageBase64,
                    ],
                    [
                        'name' => 'return_attributes',
                        'contents' => 'facequality,blur,headpose',
                    ]
                ]);
            
            if (!$detectResponse->successful()) {
                $errorData = $detectResponse->json();
                Log::error('Face++ detect failed:', $errorData);
                return response()->json([
                    'success' => false,
                    'message' => $errorData['error_message'] ?? 'Face detection failed'
                ], 500);
            }
            
            $detectData = $detectResponse->json();
            
            // Check if face was detected
            if (!isset($detectData['faces']) || count($detectData['faces']) === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No face detected in the image. Please ensure your face is clearly visible.'
                ], 400);
            }
            
            // Check for multiple faces
            if (count($detectData['faces']) > 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Multiple faces detected. Please ensure only your face is in the frame.'
                ], 400);
            }
            
            $faceInfo = $detectData['faces'][0];
            
            // Check face quality
            $faceQuality = $faceInfo['attributes']['facequality']['value'] ?? 0;
            if ($faceQuality < 50) {
                return response()->json([
                    'success' => false,
                    'message' => 'Face quality is too low (' . round($faceQuality) . '%). Please ensure good lighting.',
                    'face_quality' => $faceQuality
                ], 400);
            }
            
            // Compare with stored images
            $highestConfidence = 0;
            $successfulComparisons = 0;
            
            foreach ($user->facial_images as $index => $storedImage) {
                // Extract base64 from stored image
                $storedBase64 = $storedImage;
                if (strpos($storedBase64, 'base64,') !== false) {
                    $storedBase64 = substr($storedBase64, strpos($storedBase64, 'base64,') + 7);
                }
                $storedBase64 = preg_replace('/\s+/', '', $storedBase64);
                
                // FIXED: Properly format multipart data for compare API
                $compareResponse = Http::timeout(30)
                    ->asMultipart()
                    ->post('https://api-us.faceplusplus.com/facepp/v3/compare', [
                        [
                            'name' => 'api_key',
                            'contents' => $apiKey,
                        ],
                        [
                            'name' => 'api_secret',
                            'contents' => $apiSecret,
                        ],
                        [
                            'name' => 'image_base64_1',
                            'contents' => $storedBase64,
                        ],
                        [
                            'name' => 'image_base64_2',
                            'contents' => $submittedImageBase64,
                        ]
                    ]);
                
                if ($compareResponse->successful()) {
                    $successfulComparisons++;
                    $compareData = $compareResponse->json();
                    $confidence = $compareData['confidence'] ?? 0;
                    
                    Log::info("Comparison with image " . ($index + 1) . ": confidence = " . $confidence);
                    
                    if ($confidence > $highestConfidence) {
                        $highestConfidence = $confidence;
                    }
                } else {
                    $errorData = $compareResponse->json();
                    Log::warning('Face++ compare failed for image ' . ($index + 1) . ': ' . ($errorData['error_message'] ?? 'Unknown error'));
                    
                    // Log the full error for debugging
                    Log::debug('Compare error details:', [
                        'status' => $compareResponse->status(),
                        'body' => $errorData
                    ]);
                }
            }
            
            if ($successfulComparisons === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not compare with registered faces. Please ensure your stored images are valid.'
                ], 500);
            }
            
            $threshold = 75; // Confidence threshold
            
            if ($highestConfidence >= $threshold) {
                // Update user's last login info
                $user->last_login_at = now();
                $user->last_login_ip = $request->ip();
                
                if ($request->has('location')) {
                    $user->last_login_location = json_encode($request->location);
                }
                
                $user->save();
                
                return response()->json([
                    'success' => true,
                    'message' => 'Face verified successfully',
                    'confidence' => $highestConfidence,
                    'threshold' => $threshold,
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email,
                        'employee_id' => $user->employee_id
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Face does not match. Confidence: ' . round($highestConfidence) . '% (required: ' . $threshold . '%)',
                    'confidence' => $highestConfidence,
                    'threshold' => $threshold
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('Face verification exception: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }
}

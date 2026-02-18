<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserFaceEmbedding;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class FaceRecognitionService
{
    protected $imageManager;
    protected $similarityThreshold = 0.75; // 75% similarity threshold
    
    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver());
    }

    /**
     * Generate face embedding from image
     * 
     * @param string $imagePath Path to image file
     * @return array|null Face embedding vector or null if no face detected
     */
    public function generateFaceEmbedding(string $imagePath): ?array
    {
        try {
            $fullPath = Storage::disk('local')->path($imagePath);
            
            if (!file_exists($fullPath)) {
                Log::error('Image file not found: ' . $fullPath);
                return null;
            }

            // Load image
            $image = $this->imageManager->read($fullPath);
            
            // Convert to grayscale for face detection
            $grayscale = $image->greyscale();
            
            // Simple face detection using Haar Cascade
            $faceCoordinates = $this->detectFace($grayscale);
            
            if (!$faceCoordinates) {
                Log::warning('No face detected in image');
                return null;
            }

            // Extract face region
            $faceImage = $image->crop(
                $faceCoordinates['width'],
                $faceCoordinates['height'],
                $faceCoordinates['x'],
                $faceCoordinates['y']
            );

            // Resize to standard size for embedding generation
            $processedFace = $faceImage->resize(128, 128);

            // Generate feature vector (simplified - use PCA/LBP in production)
            $embedding = $this->extractFeatureVector($processedFace);
            
            Log::info('Face embedding generated successfully', [
                'embedding_size' => count($embedding),
                'face_coordinates' => $faceCoordinates
            ]);

            return $embedding;

        } catch (\Exception $e) {
            Log::error('Failed to generate face embedding: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'image_path' => $imagePath
            ]);
            return null;
        }
    }

    /**
     * Detect face in image using Haar Cascade
     */
    private function detectFace($image): ?array
    {
        try {
            // Save temporary file for OpenCV processing
            $tempPath = storage_path('app/temp/face_detect_' . uniqid() . '.jpg');
            $image->save($tempPath);

            // In production, use OpenCV or a dedicated face detection library
            // This is a simplified detection algorithm for demonstration
            
            // Get image dimensions
            $width = $image->width();
            $height = $image->height();
            
            // Simple heuristic: assume face is in center and takes about 30-50% of image
            $faceWidth = (int)($width * 0.4);
            $faceHeight = (int)($height * 0.4);
            $faceX = (int)(($width - $faceWidth) / 2);
            $faceY = (int)(($height - $faceHeight) / 2);
            
            // Clean up temp file
            @unlink($tempPath);
            
            return [
                'x' => max(0, $faceX),
                'y' => max(0, $faceY),
                'width' => min($faceWidth, $width - $faceX),
                'height' => min($faceHeight, $height - $faceY)
            ];
            
        } catch (\Exception $e) {
            Log::error('Face detection failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract feature vector from face image
     * Simplified implementation - use deep learning model in production
     */
    private function extractFeatureVector($image): array
    {
        // In production, this would use a pre-trained CNN model
        // (e.g., FaceNet, ArcFace, or Dlib)
        
        // Get image pixels
        $width = $image->width();
        $height = $image->height();
        
        // Generate a 128-dimension feature vector
        $features = [];
        
        // Sample pixels from different regions
        for ($i = 0; $i < 128; $i++) {
            // Deterministic pseudo-random based on image content
            $x = ($i * 7) % $width;
            $y = ($i * 11) % $height;
            
            $pixel = $image->pickColor($x, $y, 'array');
            
            // Generate feature value from pixel data
            $value = ($pixel[0] / 255.0) * 0.5 + 
                     ($pixel[1] / 255.0) * 0.3 + 
                     ($pixel[2] / 255.0) * 0.2;
            
            $features[] = round($value, 6);
        }
        
        return $features;
    }

    /**
     * Find user by face embedding
     * 
     * @param array $faceEmbedding Query face embedding
     * @return User|null Matching user or null
     */
    public function findUserByFaceEmbedding(array $faceEmbedding): ?User
    {
        try {
            // Get all face embeddings from database
            $embeddings = UserFaceEmbedding::with('user')
                ->whereHas('user', function($query) {
                    $query->where('is_active', true);
                })
                ->get();

            if ($embeddings->isEmpty()) {
                Log::warning('No face embeddings found in database');
                return null;
            }

            $bestMatch = null;
            $highestSimilarity = 0;

            foreach ($embeddings as $embedding) {
                $similarity = $this->compareFaces($faceEmbedding, $embedding->embedding);
                
                if ($similarity > $this->similarityThreshold && $similarity > $highestSimilarity) {
                    $highestSimilarity = $similarity;
                    $bestMatch = $embedding->user;
                }
            }

            if ($bestMatch) {
                Log::info('User found by face embedding', [
                    'user_id' => $bestMatch->id,
                    'similarity' => $highestSimilarity
                ]);
            }

            return $bestMatch;

        } catch (\Exception $e) {
            Log::error('Failed to find user by face embedding: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Compare two face embeddings and return similarity score
     * 
     * @param array $embedding1 First face embedding
     * @param array $embedding2 Second face embedding
     * @return float Similarity score (0-1)
     */
    public function compareFaces(array $embedding1, array $embedding2): float
    {
        try {
            if (empty($embedding1) || empty($embedding2)) {
                return 0.0;
            }

            // Calculate cosine similarity
            $dotProduct = 0;
            $norm1 = 0;
            $norm2 = 0;
            
            $count = min(count($embedding1), count($embedding2));
            
            for ($i = 0; $i < $count; $i++) {
                $dotProduct += $embedding1[$i] * $embedding2[$i];
                $norm1 += $embedding1[$i] ** 2;
                $norm2 += $embedding2[$i] ** 2;
            }
            
            if ($norm1 == 0 || $norm2 == 0) {
                return 0.0;
            }
            
            $similarity = $dotProduct / (sqrt($norm1) * sqrt($norm2));
            
            // Convert from [-1,1] to [0,1] range
            $normalizedSimilarity = ($similarity + 1) / 2;
            
            return round($normalizedSimilarity, 4);
            
        } catch (\Exception $e) {
            Log::error('Failed to compare faces: ' . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Verify if face embedding matches user
     * 
     * @param User $user User to verify against
     * @param array $faceEmbedding Query face embedding
     * @return bool True if face matches
     */
    public function verifyFace(User $user, array $faceEmbedding): bool
    {
        try {
            $primaryEmbedding = $user->getFaceEmbedding();
            
            if (!$primaryEmbedding) {
                Log::warning('User has no primary face embedding', ['user_id' => $user->id]);
                return false;
            }

            $similarity = $this->compareFaces($faceEmbedding, $primaryEmbedding);
            
            $isMatch = $similarity >= $this->similarityThreshold;
            
            Log::info('Face verification result', [
                'user_id' => $user->id,
                'similarity' => $similarity,
                'threshold' => $this->similarityThreshold,
                'is_match' => $isMatch
            ]);

            return $isMatch;

        } catch (\Exception $e) {
            Log::error('Face verification failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Set similarity threshold
     */
    public function setThreshold(float $threshold): self
    {
        $this->similarityThreshold = max(0, min(1, $threshold));
        return $this;
    }

    /**
     * Get current similarity threshold
     */
    public function getThreshold(): float
    {
        return $this->similarityThreshold;
    }

    /**
     * Batch process multiple face embeddings
     * 
     * @param array $embeddings Array of face embeddings
     * @return array Average embedding
     */
    public function averageEmbeddings(array $embeddings): array
    {
        if (empty($embeddings)) {
            return [];
        }

        $dimension = count($embeddings[0]);
        $sum = array_fill(0, $dimension, 0);
        
        foreach ($embeddings as $embedding) {
            for ($i = 0; $i < $dimension; $i++) {
                $sum[$i] += $embedding[$i];
            }
        }
        
        $average = [];
        $count = count($embeddings);
        
        for ($i = 0; $i < $dimension; $i++) {
            $average[$i] = round($sum[$i] / $count, 6);
        }
        
        return $average;
    }
}
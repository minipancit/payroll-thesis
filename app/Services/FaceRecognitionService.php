<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserFaceEmbedding;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FaceRecognitionService
{
    private float $similarityThreshold = 0.7; // Adjust based on your needs
    private int $embeddingDimensions = 128;

    /**
     * Generate face embedding from image
     */
    public function generateFaceEmbedding(string $imagePath): ?array
    {
        try {
            // Option 1: Use a local ML library (requires OpenCV/PHP)
            // $embedding = $this->generateEmbeddingWithOpenCV($imagePath);
            
            // Option 2: Call external API (Azure Face API, AWS Rekognition, etc.)
            $embedding = $this->generateEmbeddingWithExternalAPI($imagePath);
            
            // Option 3: For demo/testing, generate random embedding
            $embedding = $this->generateRandomEmbedding();
            
            return $embedding;
            
        } catch (\Exception $e) {
            Log::error('Failed to generate face embedding: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Compare two face embeddings
     */
    public function compareFaces(array $embedding1, array $embedding2): float
    {
        if (count($embedding1) !== count($embedding2)) {
            throw new \Exception('Embedding dimensions mismatch');
        }

        // Calculate cosine similarity
        $dotProduct = 0;
        $norm1 = 0;
        $norm2 = 0;

        for ($i = 0; $i < count($embedding1); $i++) {
            $dotProduct += $embedding1[$i] * $embedding2[$i];
            $norm1 += $embedding1[$i] ** 2;
            $norm2 += $embedding2[$i] ** 2;
        }

        $norm1 = sqrt($norm1);
        $norm2 = sqrt($norm2);

        if ($norm1 == 0 || $norm2 == 0) {
            return 0;
        }

        return $dotProduct / ($norm1 * $norm2);
    }

    /**
     * Find user by face embedding
     */
    public function findUserByFaceEmbedding(array $faceEmbedding, float|null $threshold = null): ?User
    {
        $threshold = $threshold ?? $this->similarityThreshold;
        
        $bestMatch = null;
        $highestSimilarity = 0;

        // Get all users with face embeddings
        $usersWithEmbeddings = User::whereHas('faceEmbeddings')
            ->with('faceEmbeddings')
            ->active()
            ->get();

        foreach ($usersWithEmbeddings as $user) {
            foreach ($user->faceEmbeddings as $embeddingRecord) {
                $similarity = $this->compareFaces($faceEmbedding, $embeddingRecord->embedding);
                
                if ($similarity > $highestSimilarity) {
                    $highestSimilarity = $similarity;
                    $bestMatch = $user;
                }
                
                // Early exit if we find a very close match
                if ($similarity >= 0.95) {
                    Log::info("Found exact match for user {$user->id} with similarity {$similarity}");
                    return $user;
                }
            }
        }

        if ($highestSimilarity >= $threshold) {
            Log::info("Found match for user {$bestMatch->id} with similarity {$highestSimilarity}");
            return $bestMatch;
        }

        Log::info("No match found. Highest similarity was {$highestSimilarity}");
        return null;
    }

    /**
     * Register new face for user
     */
    public function registerFace(User $user, string $imagePath, bool $isPrimary = true): bool
    {
        $embedding = $this->generateFaceEmbedding($imagePath);
        
        if (!$embedding) {
            return false;
        }

        $user->addFaceEmbedding($embedding, $imagePath);
        
        // Also update the face_data_hash for backward compatibility
        $user->update([
            'face_data_hash' => hash('sha256', json_encode($embedding)),
            'face_registered_at' => now(),
        ]);

        return true;
    }

    /**
     * Verify face against user's registered faces
     */
    public function verifyFace(User $user, array $faceEmbedding, float $threshold = null): bool
    {
        $threshold = $threshold ?? $this->similarityThreshold;
        
        foreach ($user->faceEmbeddings as $embeddingRecord) {
            $similarity = $this->compareFaces($faceEmbedding, $embeddingRecord->embedding);
            
            if ($similarity >= $threshold) {
                Log::info("Face verified for user {$user->id} with similarity {$similarity}");
                return true;
            }
        }

        Log::info("Face verification failed for user {$user->id}");
        return false;
    }

    /**
     * For demo/testing: Generate random embedding
     */
    private function generateRandomEmbedding(): array
    {
        $embedding = [];
        for ($i = 0; $i < $this->embeddingDimensions; $i++) {
            $embedding[] = (mt_rand() / mt_getrandmax()) * 2 - 1; // Random between -1 and 1
        }
        return $embedding;
    }

    /**
     * Example: Generate embedding using external API (Azure Face API)
     */
    private function generateEmbeddingWithExternalAPI(string $imagePath): array
    {
        // Example using Azure Face API
        $apiKey = config('services.azure.face_api_key');
        $endpoint = config('services.azure.face_api_endpoint');
        
        // This is a simplified example
        // You would need to implement the actual API call
        
        return $this->generateRandomEmbedding(); // Fallback for now
    }

    /**
     * Get similarity threshold
     */
    public function getSimilarityThreshold(): float
    {
        return $this->similarityThreshold;
    }

    /**
     * Set similarity threshold
     */
    public function setSimilarityThreshold(float $threshold): void
    {
        $this->similarityThreshold = $threshold;
    }
}
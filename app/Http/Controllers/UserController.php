<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserFaceImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $users = User::query()
            ->orderBy('created_at','desc')
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                    $q->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->paginate(10)
            ->withQueryString();
        

        return Inertia::render('Admin/Users/Index', [
            'modules' => $users,
            'filters' => $request->only('search'),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Admin/Users/Create');   
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = (object) $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
        ]);
            

        $mi = strlen($data->middle_name) > 0 ? strtoupper($data->middle_name[0]) . '. ' : '';

        $user = new User();
        $user->name = "$data->first_name $mi $data->last_name";
        $user->first_name = $data->first_name;
        $user->last_name = $data->last_name;
        $user->middle_name = $data->middle_name;
        $user->email = $data->email;
        $user->phone = $data->phone;
        $user->password = Hash::make('password'); // Default password, should be changed later
        $user->save();

        Inertia::flash([
            'header' => "Create success",
            'message' => "You have successfully created user $user->name"
        ]);

        return to_route('admin.user.edit', $user->id);
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        return Inertia::render('Admin/Users/Edit', [
            'module' => $user,
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        
        // Update basic user info
        $user->update($request->only([
            'first_name', 
            'last_name', 
            'middle_name', 
            'email', 
            'phone',
        ]));
        
        // Handle facial images
        if ($request->has('facial_images') && !empty($request->facial_images)) {
            $user->facial_images = $request->input('facial_images');
            $user->face_trained_at = now();
        }
        
        $user->save();
        
        return redirect()->back()->with('success', 'User updated successfully');
    }

    

    /**
     * Handle facial recognition data including sample images
     */
    private function handleFaceData(User $user, array $data): void
    {
        // Save primary face image
        $primaryImage = $this->saveFaceImage(
            $user, 
            $data['facial_data'], 
            true, // is primary
            $data['facial_embeddings']['detection_score'] ?? null
        );

        // Save sample images
        if (isset($data['facial_embeddings']['sample_images']) && is_array($data['facial_embeddings']['sample_images'])) {
            foreach ($data['facial_embeddings']['sample_images'] as $index => $sampleImage) {
                if (!empty($sampleImage)) {
                    $this->saveFaceImage(
                        $user,
                        $sampleImage,
                        false, // not primary
                        $data['facial_embeddings']['detection_score'] ?? null,
                        $index + 1
                    );
                }
            }
        }

        // Save face embeddings
        if (isset($data['facial_embeddings'])) {
            // Generate a mock embedding vector (in production, this would come from your face recognition model)
            $embeddingVector = $this->generateMockEmbedding();
            
            $user->addFaceEmbedding(
                $embeddingVector,
                $primaryImage ? $primaryImage->image_path : null
            );

            // Update user face registration status
            $user->face_data_hash = hash('sha256', $data['facial_data']);
            $user->face_registered_at = now();
            $user->face_updated_at = now();
        }
    }

    /**
     * Save face image to storage and database
     */
    private function saveFaceImage(User $user, string $base64Image, bool $isPrimary = false, ?float $confidenceScore = null, int $order = 0): ?UserFaceImage
    {
        try {
            // Decode base64 image
            $imageData = $this->decodeBase64Image($base64Image);
            if (!$imageData) {
                return null;
            }

            // Generate unique filename
            $timestamp = now()->format('Ymd_His');
            $filename = sprintf(
                'user_%d_%s_%s.jpg',
                $user->id,
                $isPrimary ? 'primary' : 'sample',
                $timestamp
            );

            // Store in public disk
            $path = "face-images/{$user->id}/{$filename}";
            Storage::disk('public')->put($path, $imageData);

            // Create database record
            $faceImage = new UserFaceImage([
                'user_id' => $user->id,
                'image_path' => $path,
                'is_primary' => $isPrimary,
                'order' => $order,
                'metadata' => [
                    'uploaded_at' => now()->toISOString(),
                    'confidence_score' => $confidenceScore,
                    'filename' => $filename,
                    'size_bytes' => strlen($imageData),
                ],
            ]);

            $faceImage->save();

            // If this is primary, mark all other images as non-primary
            if ($isPrimary) {
                UserFaceImage::where('user_id', $user->id)
                    ->where('id', '!=', $faceImage->id)
                    ->update(['is_primary' => false]);
            }

            return $faceImage;

        } catch (\Exception $e) {
            Log::error('Failed to save face image: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Decode base64 image data
     */
    private function decodeBase64Image(string $base64String): ?string
    {
        // Remove data URL prefix if present
        if (strpos($base64String, 'data:image') === 0) {
            $base64String = preg_replace('#^data:image/\w+;base64,#i', '', $base64String);
        }

        // Decode base64
        $imageData = base64_decode($base64String, true);
        
        if ($imageData === false) {
            return null;
        }

        return $imageData;
    }

    /**
     * Generate a mock embedding vector
     * In production, this should come from your actual face recognition model
     */
    private function generateMockEmbedding(): array
    {
        // Generate a 128-dimension mock embedding (common size for face recognition)
        $embedding = [];
        for ($i = 0; $i < 128; $i++) {
            $embedding[] = (float) (mt_rand(-100, 100) / 100);
        }
        return $embedding;
    }

    /**
     * Optional: Delete old face images
     */
    private function deleteOldFaceImages(User $user): void
    {
        // Get all face images
        $images = UserFaceImage::where('user_id', $user->id)->get();
        
        foreach ($images as $image) {
            // Delete file from storage
            if ($image->image_path && Storage::disk('public')->exists($image->image_path)) {
                Storage::disk('public')->delete($image->image_path);
            }
            
            // Delete database record
            $image->delete();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $user->delete();
        Inertia::flash([
            'header' => "Delete success",
            'message' => "You have successfully removed user $user->name"
        ]);

        return to_route('admin.user.index');
    }
}

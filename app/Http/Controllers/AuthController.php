<?php

namespace App\Http\Controllers;


use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class AuthController extends Controller
{
 public function loginWithFaceCode(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'code' => ['required', 'digits:5'],
        ]);

        $cached = Cache::get('face_login_code_' . $request->email);

        if (!$cached) {
            return response()->json([
                'success' => false,
                'message' => 'Code expired or not found.',
            ], 401);
        }

        if (($cached['code'] ?? null) !== $request->code) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid secret code.',
            ], 401);
        }

        $user = User::find($cached['user_id'] ?? null);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        // Remove used code
        Cache::forget('face_login_code_' . $request->email);

        // Optional: delete previous mobile tokens
        // $user->tokens()->where('name', 'mobile')->delete();

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'employee_id' => $user->employee_id,
            ],
        ]);
    }

    public function session(Request $request)
    {
        return response()->json([
            'success' => true,
            'user' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'employee_id' => $request->user()->employee_id,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'user',
        ]);

        // Create default profile for onboarding
        UserProfile::create([
            'user_id' => $user->id,
            'gender' => 'pria',
            'occupation' => 'mahasiswa',
            'budget_min' => 1000000,
            'budget_max' => 3000000,
            'preferred_facilities' => [],
            'preferred_rules' => [],
            'preferred_location' => 'Karawaci',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user->load('profile'),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Email atau password salah.'
            ], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user->load('profile'),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Berhasil logout.'
        ]);
    }

    public function getProfile(Request $request)
    {
        $user = $request->user()->load('profile', 'interactions.kos');
        return response()->json($user);
    }

    public function updateProfile(Request $request)
    {
        $request->validate([
            'gender' => 'required|string|in:pria,wanita',
            'occupation' => 'required|string|in:mahasiswa,pekerja',
            'budget_min' => 'required|integer|min:0',
            'budget_max' => 'required|integer|gte:budget_min',
            'preferred_facilities' => 'nullable|array',
            'preferred_rules' => 'nullable|array',
            'preferred_location' => 'required|string',
        ]);

        $user = $request->user();
        
        $profile = UserProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'gender' => $request->gender,
                'occupation' => $request->occupation,
                'budget_min' => $request->budget_min,
                'budget_max' => $request->budget_max,
                'preferred_facilities' => $request->preferred_facilities ?? [],
                'preferred_rules' => $request->preferred_rules ?? [],
                'preferred_location' => $request->preferred_location,
            ]
        );

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'profile' => $profile
        ]);
    }

    public function resetInteractions(Request $request)
    {
        $user = $request->user();
        $user->interactions()->delete();
        return response()->json([
            'message' => 'Seluruh riwayat interaksi berhasil di-reset ke Cold-Start.'
        ]);
    }
}

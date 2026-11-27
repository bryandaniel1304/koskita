<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Cari-atau-buat akun dari identitas Google -- dipakai bersama oleh login
 * Google di web (redirect OAuth lewat Socialite, lihat WebAuthController)
 * DAN di mobile (verifikasi ID token dari google_sign_in, lihat
 * Api\AuthController::loginWithGoogle) supaya logic pencocokan akun (coba
 * google_id dulu, fallback ke email biar tidak bikin akun dobel kalau
 * sebelumnya sudah pernah daftar manual) cuma ada di SATU tempat.
 */
class GoogleAccountService
{
    public static function findOrCreate(string $googleId, string $email, ?string $name): User
    {
        $user = User::where('google_id', $googleId)->first();

        if ($user) {
            return $user;
        }

        // Mungkin sudah pernah daftar manual pakai email yang sama --
        // sambungkan ke akun lama itu, jangan bikin akun dobel.
        $user = User::where('email', $email)->first();

        if ($user) {
            $user->forceFill(['google_id' => $googleId])->save();

            return $user;
        }

        $user = User::create([
            'name' => $name ?: 'Pengguna KosKita',
            'email' => $email,
            'google_id' => $googleId,
            // Password acak yang tidak pernah diketahui/dipakai siapa pun --
            // akun ini SELALU masuk lewat Google.
            'password' => Hash::make(Str::random(40)),
            'role' => 'user',
            // Google sudah verifikasi emailnya sendiri, jadi tidak perlu
            // lewat proses verifikasi email kami lagi.
            'email_verified_at' => now(),
        ]);

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

        return $user;
    }
}

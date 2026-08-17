<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\GoogleAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            // Nomor HP Indonesia: 08xxxxxxxxx / +628xxxxxxxxx / 628xxxxxxxxx,
            // 9-13 digit setelah kode awal. Verifikasi OTP belum diaktifkan
            // (butuh layanan SMS/WA berbayar pihak ketiga) -- untuk sekarang
            // cuma divalidasi formatnya & disimpan.
            'phone' => ['required', 'string', 'regex:/^(\+62|62|0)8[1-9][0-9]{6,10}$/'],
            'password' => 'required|string|min:6',
            'role' => 'nullable|string|in:user,owner',
        ]);

        $role = $request->role ?? 'user';

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role' => $role,
        ]);

        // Kirim email verifikasi (link sungguhan lewat SMTP yang dikonfigurasi
        // di .env). Tidak memblokir registrasi/login kalau gagal terkirim --
        // pengguna tetap bisa pakai app & kirim ulang lewat /email/resend.
        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            report($e);
        }

        // Penyewa (role "user") mengisi profil preferensi lewat layar
        // Onboarding setelah ini -- baris kosong disiapkan di sini supaya
        // RecommendationService tidak error sebelum Onboarding diisi, tapi
        // TIDAK ada nilai yang dianggap "sudah dipilih" pengguna (semua
        // chip di Onboarding tetap kosong sampai pengguna sendiri memilih).
        // Pemilik kos (role "owner") tidak butuh profil preferensi ini sama
        // sekali, jadi dilewati.
        if ($role === 'user') {
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
        }

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

        if ($user->two_factor_enabled) {
            // Auth::attempt() di atas cuma buat cek kredensial -- app
            // Flutter pakai token Sanctum, bukan sesi, jadi logout lagi
            // di sini supaya tidak nyangkut sesi guard "web" yang tidak
            // dipakai. Token BELUM diterbitkan sampai kode 2FA benar
            // (lihat verifyTwoFactor).
            Auth::logout();
            $user->generateAndSendTwoFactorCode();

            return response()->json([
                'requires_2fa' => true,
                'message' => 'Kode verifikasi sudah dikirim ke emailmu.',
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user->load('profile'),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /** true + client_id kalau GOOGLE_CLIENT_ID sudah diisi -- dipakai
     *  Flutter buat sembunyikan tombol "Masuk dengan Google" kalau memang
     *  belum dikonfigurasi (sama seperti WebAuthController::googleLoginConfigured()),
     *  DAN sebagai `serverClientId` yang wajib diisi ke plugin google_sign_in
     *  supaya idToken yang dihasilkan device bisa diverifikasi balik di sini. */
    public function googleConfig()
    {
        $clientId = config('services.google.client_id');

        return response()->json([
            'configured' => filled($clientId),
            'client_id' => $clientId ?: null,
        ]);
    }

    /**
     * Login Google dari mobile -- BEDA alurnya dari web (yang redirect
     * penuh lewat Socialite). Di sini Flutter sudah menyelesaikan sign-in
     * asli di perangkat (lewat Google Play Services/paket google_sign_in)
     * dan cuma kirim ID token hasilnya ke sini untuk diverifikasi &
     * ditukar jadi token Sanctum -- backend TIDAK pernah melihat password
     * Google pengguna.
     *
     * Verifikasi token dilakukan lewat endpoint tokeninfo resmi Google
     * (bukan decode manual JWT) supaya tanda tangannya ikut tervalidasi
     * tanpa perlu simpan/rotasi public key Google sendiri -- cukup untuk
     * skala aplikasi ini, dan konsisten dengan filosofi "jangan pasang
     * dependency berat untuk hal yang bisa dicek lewat satu panggilan
     * HTTP" yang sudah dipakai di validasi foto chat (lihat MessageController).
     */
    public function loginWithGoogle(Request $request)
    {
        $request->validate(['id_token' => 'required|string']);

        $clientId = config('services.google.client_id');
        if (blank($clientId)) {
            return response()->json(['message' => 'Login Google belum dikonfigurasi oleh admin.'], 503);
        }

        try {
            $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $request->id_token,
            ]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Gagal menghubungi server Google. Coba lagi.'], 502);
        }

        if (!$response->successful()) {
            return response()->json(['message' => 'Token Google tidak valid atau sudah kedaluwarsa.'], 401);
        }

        $payload = $response->json();

        // "aud" HARUS persis sama dengan client ID yang kita daftarkan --
        // kalau tidak dicek, siapa pun bisa kirim ID token dari APLIKASI
        // GOOGLE LAIN (bukan KosKita) dan tetap lolos diverifikasi di sini.
        if (($payload['aud'] ?? null) !== $clientId) {
            return response()->json(['message' => 'Token Google tidak valid untuk aplikasi ini.'], 401);
        }

        if (($payload['email_verified'] ?? 'false') !== 'true' || blank($payload['email'] ?? null)) {
            return response()->json(['message' => 'Email Google belum terverifikasi.'], 401);
        }

        $user = GoogleAccountService::findOrCreate($payload['sub'], $payload['email'], $payload['name'] ?? null);

        // Kebijakan 2FA tetap konsisten berlaku terlepas dari jalur
        // masuknya (password ATAU Google) -- bukan celah buat lewati 2FA.
        if ($user->two_factor_enabled) {
            $user->generateAndSendTwoFactorCode();

            return response()->json([
                'requires_2fa' => true,
                'message' => 'Kode verifikasi sudah dikirim ke emailmu.',
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user->load('profile'),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /** Tukar kode OTP dengan token Sanctum -- dipanggil setelah login()
     *  membalas requires_2fa: true. Pola email+kode dipilih (bukan
     *  user_id) supaya konsisten dengan alur reset password yang sudah
     *  ada, dan tidak membocorkan ID user lewat request body. */
    public function verifyTwoFactor(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'code' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !$user->verifyTwoFactorCode($request->code)) {
            return response()->json(['message' => 'Kode salah atau sudah kedaluwarsa.'], 422);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user->load('profile'),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function resendTwoFactorCode(Request $request)
    {
        $request->validate(['email' => 'required|string|email']);

        $user = User::where('email', $request->email)->first();
        if ($user && $user->two_factor_enabled) {
            $user->generateAndSendTwoFactorCode();
        }

        // Balas sukses generik regardless -- jangan bocorkan lewat respons
        // apakah email itu terdaftar/2FA aktif (sama prinsipnya dengan
        // Password::sendResetLink di alur lupa password web).
        return response()->json(['message' => 'Kalau akun itu ada dan 2FA aktif, kode baru sudah dikirim.']);
    }

    /** Toggle 2FA dari dalam app (Profil > Keamanan) -- guard Sanctum,
     *  jadi user yang minta sudah pasti pemilik akun ini sendiri. */
    public function startEnableTwoFactor(Request $request)
    {
        $request->user()->generateAndSendTwoFactorCode();

        return response()->json(['message' => 'Kode konfirmasi sudah dikirim ke emailmu.']);
    }

    public function confirmEnableTwoFactor(Request $request)
    {
        $request->validate(['code' => 'required|string']);
        $user = $request->user();

        if (!$user->verifyTwoFactorCode($request->code)) {
            return response()->json(['message' => 'Kode salah atau sudah kedaluwarsa.'], 422);
        }

        $user->forceFill(['two_factor_enabled' => true])->save();

        return response()->json(['message' => 'Verifikasi 2 langkah berhasil diaktifkan.', 'two_factor_enabled' => true]);
    }

    public function disableTwoFactor(Request $request)
    {
        $request->user()->forceFill([
            'two_factor_enabled' => false,
            'two_factor_code' => null,
            'two_factor_expires_at' => null,
        ])->save();

        return response()->json(['message' => 'Verifikasi 2 langkah dinonaktifkan.', 'two_factor_enabled' => false]);
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

    /** Satu switch = mematikan email DAN push (FCM) sekaligus untuk jenis
     *  itu -- lihat method via() di NewMessageReceived/BookingStatusChanged/
     *  WaitlistSpotAvailable. */
    public function updateNotificationPreferences(Request $request)
    {
        $validated = $request->validate([
            'notify_bookings' => 'required|boolean',
            'notify_messages' => 'required|boolean',
            'notify_waitlist' => 'required|boolean',
        ]);

        $request->user()->update($validated);

        return response()->json(['message' => 'Preferensi notifikasi tersimpan.']);
    }

    /**
     * Foto profil -- pakai `mimes:...` (bukan rule `image`) karena
     * lingkungan pengembangan ini tidak punya ekstensi GD PHP yang
     * dibutuhkan validasi `image` (lihat pola sama di MessageController
     * untuk foto chat), sekaligus lebih portable ke server produksi mana
     * pun terlepas dari GD terpasang atau tidak.
     */
    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|mimes:jpeg,jpg,png,webp|max:4096',
        ]);

        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar_path' => $path]);

        return response()->json([
            'message' => 'Foto profil berhasil disimpan.',
            'avatar_url' => Storage::disk('public')->url($path),
        ]);
    }

    public function deleteAvatar(Request $request)
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        return response()->json(['message' => 'Foto profil dihapus.']);
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

    /**
     * Kirim ulang email verifikasi -- dipakai tombol "Kirim Ulang" di banner
     * verifikasi pada layar Profil (app belum tahu SMTP-nya berhasil kirim
     * atau tidak, jadi pesan sukses di sini berarti "sudah diproses", bukan
     * jaminan email sampai).
     */
    public function resendVerification(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email sudah terverifikasi.']);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Email verifikasi telah dikirim ulang.']);
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

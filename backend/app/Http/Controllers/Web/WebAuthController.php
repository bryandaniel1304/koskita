<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\GoogleAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Laravel\Socialite\Facades\Socialite;

/**
 * Otentikasi untuk situs publik (penyewa) -- sesi browser biasa (guard
 * "web"), TERPISAH dari AdminAuthController (path & nama route beda:
 * /daftar & /masuk, bukan /login) supaya tidak pernah tabrakan dengan
 * panel admin, dan terpisah dari AuthController Api (yang pakai token
 * Sanctum untuk app Flutter). Ketiganya sengaja berdiri sendiri-sendiri
 * walau sama-sama baca/tulis ke tabel users yang sama.
 */
class WebAuthController extends Controller
{
    public function showRegister()
    {
        if (Auth::check()) {
            return redirect()->route('web.home');
        }

        return view('web.auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => ['required', 'string', 'regex:/^(\+62|62|0)8[1-9][0-9]{6,10}$/'],
            'password' => 'required|string|min:6|confirmed',
        ], [
            'phone.regex' => 'Format nomor HP tidak valid (contoh: 081234567890).',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
            'role' => 'user',
        ]);

        // Profil preferensi default -- sama seperti alur register di app
        // Flutter, supaya RecommendationService langsung bisa jalan
        // (cold-start) tanpa nunggu pengguna mengisi preferensi dulu.
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

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            report($e);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('web.home')
            ->with('status', 'Akun berhasil dibuat! Cek email kamu untuk verifikasi sebelum mengajukan booking.');
    }

    public function showLogin(Request $request)
    {
        if (Auth::check()) {
            return redirect()->route('web.home');
        }

        return view('web.auth.login', ['redirect' => $request->query('redirect')]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Email atau password salah.'])->onlyInput('email');
        }

        $user = Auth::user();

        // Password benar, TAPI kalau 2FA aktif jangan langsung dianggap
        // login penuh -- logout lagi, simpan identitas minimal di sesi
        // (bukan sesi terotentikasi), lempar ke layar kode verifikasi.
        // Baru Auth::login() beneran dipanggil setelah kodenya benar
        // (lihat verifyTwoFactor()).
        if ($user->two_factor_enabled) {
            Auth::logout();
            $request->session()->put('2fa_email', $user->email);
            $request->session()->put('2fa_remember', $request->boolean('remember'));
            $request->session()->put('2fa_redirect', $request->input('redirect'));
            $user->generateAndSendTwoFactorCode();

            return redirect()->route('web.2fa.challenge');
        }

        $request->session()->regenerate();

        return $this->redirectAfterLogin($request, $user);
    }

    /** Tujuan setelah login sukses (baik langsung maupun setelah lolos
     *  2FA) -- dipakai bersama oleh login() dan verifyTwoFactor(). */
    private function redirectAfterLogin(Request $request, User $user)
    {
        // "redirect" cuma dipakai kalau memang mengarah ke halaman situs ini
        // sendiri (mis. balik ke halaman detail kos setelah klik "Masuk
        // untuk Booking") -- dicegah dipakai buka redirect ke domain lain.
        $redirect = $request->input('redirect') ?? $request->session()->pull('2fa_redirect');
        if ($redirect && str_starts_with($redirect, url('/'))) {
            return redirect()->to($redirect);
        }

        // Akun "owner" tidak punya keperluan di beranda pencarian kos untuk
        // penyewa -- langsung antar ke Portal Pemilik.
        if ($user->role === 'owner') {
            return redirect()->intended(route('web.owner.dashboard'));
        }

        return redirect()->intended(route('web.home'));
    }

    public function showTwoFactorChallenge(Request $request)
    {
        if (!$request->session()->has('2fa_email')) {
            return redirect()->route('web.login');
        }

        return view('web.auth.two-factor');
    }

    public function verifyTwoFactor(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $email = $request->session()->get('2fa_email');
        $user = $email ? User::where('email', $email)->first() : null;

        if (!$user) {
            return redirect()->route('web.login');
        }

        if (!$user->verifyTwoFactorCode($request->code)) {
            return back()->withErrors(['code' => 'Kode salah atau sudah kedaluwarsa. Coba lagi atau minta kode baru.']);
        }

        Auth::login($user, (bool) $request->session()->get('2fa_remember', false));
        $request->session()->forget(['2fa_email', '2fa_remember']);
        $request->session()->regenerate();

        return $this->redirectAfterLogin($request, $user);
    }

    public function resendTwoFactorCode(Request $request)
    {
        $email = $request->session()->get('2fa_email');
        $user = $email ? User::where('email', $email)->first() : null;

        if (!$user) {
            return redirect()->route('web.login');
        }

        $user->generateAndSendTwoFactorCode();

        return back()->with('status', 'Kode baru sudah dikirim ke emailmu.');
    }

    /** Langkah 1 aktifkan 2FA -- kirim kode dulu buat buktikan email bisa
     *  diakses SEBELUM benar-benar disandarkan buat login berikutnya. */
    public function startEnableTwoFactor(Request $request)
    {
        $request->user()->generateAndSendTwoFactorCode();

        return back()->with('confirmingTwoFactor', true)->with('status', 'Kode konfirmasi sudah dikirim ke emailmu.');
    }

    public function confirmEnableTwoFactor(Request $request)
    {
        $request->validate(['code' => 'required|string']);
        $user = $request->user();

        if (!$user->verifyTwoFactorCode($request->code)) {
            return back()->withErrors(['code' => 'Kode salah atau sudah kedaluwarsa.'])->with('confirmingTwoFactor', true);
        }

        $user->forceFill(['two_factor_enabled' => true])->save();

        return back()->with('status', 'Verifikasi 2 langkah berhasil diaktifkan.');
    }

    public function disableTwoFactor(Request $request)
    {
        $request->user()->forceFill([
            'two_factor_enabled' => false,
            'two_factor_code' => null,
            'two_factor_expires_at' => null,
        ])->save();

        return back()->with('status', 'Verifikasi 2 langkah dinonaktifkan.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('web.home');
    }

    /** true kalau GOOGLE_CLIENT_ID sudah diisi di .env -- dipakai di sini
     *  DAN di Blade (buat sembunyikan tombol kalau memang belum
     *  dikonfigurasi, daripada tampil tapi selalu error kalau diklik). */
    public static function googleLoginConfigured(): bool
    {
        return filled(config('services.google.client_id'));
    }

    public function redirectToGoogle()
    {
        if (!self::googleLoginConfigured()) {
            return redirect()->route('web.login')->withErrors(['email' => 'Login Google belum dikonfigurasi oleh admin. Silakan masuk pakai email & password.']);
        }

        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(Request $request)
    {
        if (!self::googleLoginConfigured()) {
            return redirect()->route('web.login');
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('web.login')->withErrors(['email' => 'Gagal masuk dengan Google. Coba lagi.']);
        }

        $user = GoogleAccountService::findOrCreate(
            $googleUser->getId(),
            $googleUser->getEmail(),
            $googleUser->getName() ?: $googleUser->getNickname()
        );

        // Kebijakan 2FA tetap konsisten berlaku terlepas dari jalur
        // masuknya (password ATAU Google) -- bukan celah buat lewati 2FA.
        if ($user->two_factor_enabled) {
            $request->session()->put('2fa_email', $user->email);
            $user->generateAndSendTwoFactorCode();
            return redirect()->route('web.2fa.challenge');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return $this->redirectAfterLogin($request, $user);
    }

    public function resendVerification(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return back()->with('status', 'Email kamu sudah terverifikasi.');
        }

        $user->sendEmailVerificationNotification();

        return back()->with('status', 'Email verifikasi telah dikirim ulang. Cek inbox (atau folder Spam) kamu.');
    }

    public function showForgotPassword()
    {
        if (Auth::check()) {
            return redirect()->route('web.home');
        }

        return view('web.auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        // SENGAJA balas dengan pesan yang sama persis baik email-nya
        // terdaftar maupun tidak -- kalau beda, itu jadi celah buat orang
        // iseng cek satu-satu email mana saja yang punya akun KosKita
        // (email enumeration). Password::sendResetLink sendiri sudah diam-
        // diam no-op kalau email tidak ditemukan.
        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'Kalau email itu terdaftar di KosKita, tautan atur ulang password sudah kami kirim. Cek inbox (atau folder Spam) kamu.');
    }

    public function showResetPassword(Request $request, string $token)
    {
        if (Auth::check()) {
            return redirect()->route('web.home');
        }

        return view('web.auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => __($status)])->withInput($request->only('email'));
        }

        return redirect()->route('web.login')->with('status', 'Password berhasil diatur ulang. Silakan masuk dengan password barumu.');
    }
}

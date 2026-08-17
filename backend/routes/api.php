<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\KosController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\ChatbotController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\OwnerKosController;
use App\Http\Controllers\Api\OwnerBookingController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\WaitlistController;
use App\Http\Controllers\Api\OwnerVerificationController;
use App\Http\Controllers\Api\BroadcastingController;
use App\Http\Controllers\Api\FcmTokenController;

// Parameter koneksi publik Reverb -- tidak butuh login sama sekali (mirip
// app key publik Pusher), dipanggil sekali oleh Flutter sebelum konek WS.
Route::get('/broadcasting/config', [BroadcastingController::class, 'config']);

// Idem -- client ID publik Google (bukan rahasia, dipakai google_sign_in
// sebagai serverClientId) dan status "sudah dikonfigurasi admin atau belum".
Route::get('/auth/google/config', [AuthController::class, 'googleConfig']);

// Public Routes -- dibatasi 6 percobaan/menit per IP supaya tidak jadi
// sasaran brute-force/spam registrasi (standar keamanan API publik).
Route::middleware('throttle:6,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/auth/google', [AuthController::class, 'loginWithGoogle']);
});

// Tantangan verifikasi 2 langkah -- dibatasi lebih ketat (kode 6 digit
// cuma 1 juta kemungkinan) supaya tidak bisa ditebak brute-force dalam
// jendela 10 menit masa berlakunya.
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/verify-2fa', [AuthController::class, 'verifyTwoFactor']);
    Route::post('/2fa/resend', [AuthController::class, 'resendTwoFactorCode']);
});

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/2fa/enable/start', [AuthController::class, 'startEnableTwoFactor']);
    Route::post('/2fa/enable/confirm', [AuthController::class, 'confirmEnableTwoFactor'])->middleware('throttle:5,1');
    Route::post('/2fa/disable', [AuthController::class, 'disableTwoFactor']);

    // Otorisasi channel privat Reverb untuk client mobile -- lihat
    // BroadcastingController untuk kenapa ini terpisah dari
    // `/broadcasting/auth` bawaan Laravel (yang pakai sesi cookie, bukan
    // token Sanctum). Nama endpoint SENGAJA sama (`/broadcasting/auth`)
    // supaya Laravel Echo/pusher-js client mana pun (termasuk Flutter)
    // bisa pakai default `authEndpoint` tanpa konfigurasi tambahan.
    Route::post('/broadcasting/auth', [BroadcastingController::class, 'auth']);

    // Token perangkat untuk push notification (FCM) -- lihat FcmTokenController.
    Route::post('/fcm-token', [FcmTokenController::class, 'store']);
    Route::delete('/fcm-token', [FcmTokenController::class, 'destroy']);

    // Profile
    Route::get('/profile', [AuthController::class, 'getProfile']);
    Route::post('/profile', [AuthController::class, 'updateProfile']);
    Route::put('/profile/notification-preferences', [AuthController::class, 'updateNotificationPreferences']);
    Route::post('/profile/avatar', [AuthController::class, 'uploadAvatar']);
    Route::delete('/profile/avatar', [AuthController::class, 'deleteAvatar']);
    Route::post('/profile/reset-interactions', [AuthController::class, 'resetInteractions']);
    // Kirim email sungguhan (SMTP) -- dibatasi ketat (3x/10 menit) supaya
    // tombol "Kirim Ulang" tidak bisa dispam jadi banjir SMTP/kena rate
    // limit Gmail.
    Route::post('/email/resend', [AuthController::class, 'resendVerification'])->middleware('throttle:3,10');
    
    // Kos Listings & Interactions
    Route::get('/kos', [KosController::class, 'index']);
    // Harus terdaftar SEBELUM /kos/{id} -- kalau tidak, "compare" akan
    // ditangkap sebagai {id} dan berakhir 404 "kos tidak ditemukan".
    Route::get('/kos/compare', [KosController::class, 'compare']);
    Route::get('/kos/{id}', [KosController::class, 'show']);
    Route::post('/kos/{id}/rate', [KosController::class, 'rate']);
    Route::get('/favorites', [KosController::class, 'favorites']);

    // "Beri Tahu Saya" saat kos penuh
    Route::post('/kos/{id}/waitlist', [WaitlistController::class, 'store']);
    Route::delete('/kos/{id}/waitlist', [WaitlistController::class, 'destroy']);

    // Laporan/flag atas kos atau ulasan mencurigakan
    Route::post('/reports', [ReportController::class, 'store'])->middleware('throttle:10,1');

    // Review publik (beda dari rating privat di atas) -- wajib email
    // terverifikasi karena isinya konten publik ke pengguna lain.
    Route::post('/kos/{id}/reviews', [ReviewController::class, 'store'])->middleware('verified');
    Route::delete('/kos/{id}/reviews', [ReviewController::class, 'destroy']);

    // Recommendations
    Route::get('/recommendations', [RecommendationController::class, 'index']);

    // Booking (mengajukan booking = aksi sensitif -> wajib email terverifikasi)
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store'])->middleware(['verified', 'throttle:10,1']);
    Route::post('/bookings/{id}/cancel', [BookingController::class, 'cancel']);

    // Online Nanny (chatbot) -- dibatasi 20 pesan/menit, cukup longgar untuk
    // percakapan wajar tapi mencegah spam otomatis.
    Route::post('/chatbot', [ChatbotController::class, 'respond'])->middleware('throttle:20,1');

    // Master data (fasilitas & aturan) untuk form kos pemilik
    Route::get('/meta', [MetaController::class, 'index']);

    // Notifikasi in-app (derived dari data booking/ulasan, bukan tabel
    // tersendiri) -- satu endpoint dipakai penyewa maupun pemilik,
    // isinya beda otomatis mengikuti role di dalam controller.
    Route::get('/notifications', [NotificationController::class, 'index']);

    // Pesan langsung penyewa <-> pemilik (lihat MessageController) --
    // wajib email terverifikasi karena isinya kontak langsung antar
    // pengguna, dibatasi 30 pesan/menit supaya tidak jadi jalur spam.
    Route::middleware('verified')->group(function () {
        Route::get('/messages/conversations', [MessageController::class, 'conversations']);
        Route::get('/messages/unread-count', [MessageController::class, 'unreadCount']);
        Route::get('/messages/thread/{userId}', [MessageController::class, 'thread']);
        Route::post('/messages', [MessageController::class, 'store'])->middleware('throttle:30,1');
    });

    // Khusus akun Penyedia Kos (role "owner") -- juga wajib email
    // terverifikasi karena berurusan langsung dengan listing & booking.
    Route::middleware(['role:owner', 'verified'])->prefix('owner')->group(function () {
        Route::get('/koses', [OwnerKosController::class, 'index']);
        Route::post('/koses', [OwnerKosController::class, 'store']);
        Route::get('/koses/{id}', [OwnerKosController::class, 'show']);
        Route::put('/koses/{id}', [OwnerKosController::class, 'update']);
        Route::delete('/koses/{id}', [OwnerKosController::class, 'destroy']);
        Route::delete('/koses/{kosId}/images/{imageId}', [OwnerKosController::class, 'destroyImage']);

        // Breakdown tipe kamar opsional (mis. AC vs Standar, harga beda) --
        // murni tampilan, lihat catatan di KosRoomType.
        Route::post('/koses/{id}/room-types', [OwnerKosController::class, 'storeRoomType']);
        Route::put('/koses/{kosId}/room-types/{roomTypeId}', [OwnerKosController::class, 'updateRoomType']);
        Route::delete('/koses/{kosId}/room-types/{roomTypeId}', [OwnerKosController::class, 'destroyRoomType']);

        Route::get('/koses/{id}/matches', [OwnerKosController::class, 'matches']);
        Route::get('/koses/{id}/analytics', [OwnerKosController::class, 'analytics']);

        Route::get('/bookings', [OwnerBookingController::class, 'index']);
        Route::put('/bookings/{id}', [OwnerBookingController::class, 'update']);
        Route::put('/bookings/{id}/payment-status', [OwnerBookingController::class, 'updatePaymentStatus']);

        Route::post('/reviews/{id}/reply', [OwnerKosController::class, 'replyToReview']);

        // Badge "Pemilik Terverifikasi" & kode QRIS statis
        Route::post('/verification', [OwnerVerificationController::class, 'submit']);
        Route::post('/qris', [OwnerVerificationController::class, 'uploadQris']);
        Route::delete('/qris', [OwnerVerificationController::class, 'deleteQris']);
    });
});

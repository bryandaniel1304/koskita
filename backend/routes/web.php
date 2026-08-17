<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminKosController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminFacilityController;
use App\Http\Controllers\Admin\AdminRuleController;
use App\Http\Controllers\Admin\AdminBookingController;
use App\Http\Controllers\Admin\AdminBroadcastController;
use App\Http\Controllers\Admin\AdminReportController;
use App\Http\Controllers\Admin\AdminSearchLogController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\Web\WebAuthController;
use App\Http\Controllers\Web\WebBookingController;
use App\Http\Controllers\Web\WebChatbotController;
use App\Http\Controllers\Web\WebHomeController;
use App\Http\Controllers\Web\WebKosController;
use App\Http\Controllers\Web\WebMessageController;
use App\Http\Controllers\Web\WebAvatarController;
use App\Http\Controllers\Web\WebNotificationPreferencesController;
use App\Http\Controllers\Web\WebOwnerController;
use App\Http\Controllers\Web\WebProfileController;
use App\Http\Controllers\Web\WebRecommendationController;
use App\Http\Controllers\Web\WebTipsController;
use App\Http\Controllers\Web\SitemapController;
use App\Http\Controllers\Web\WidgetController;
use App\Http\Controllers\Admin\AdminArticleController;

// Link konfirmasi verifikasi email (dibuka lewat browser dari email pengguna
// app Flutter maupun situs, bukan dari dalam app/situs itu sendiri) -- nama
// route "verification.verify" wajib persis ini karena dipakai otomatis oleh
// notifikasi VerifyEmail bawaan Laravel.
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware('signed')
    ->name('verification.verify');

// Dokumen legal -- dibuka dari dalam app Flutter (Profil > Kebijakan Privasi
// / Syarat & Ketentuan) lewat WebView/browser eksternal, dan dipakai juga
// sebagai footer link di situs publik.
Route::view('/privacy', 'legal.privacy')->name('legal.privacy');
Route::view('/terms', 'legal.terms')->name('legal.terms');

// ============================================================
// App Links (Android) / Universal Links (iOS) -- file verifikasi yang
// dicek OS langsung dari domain ini (BUKAN dari dalam app) supaya tautan
// "https://<domain>/kos/{id}" dibuka LANGSUNG oleh app KosKita (kalau
// sudah terpasang) alih-alih cuma buka browser ke halaman web biasa.
//
// CATATAN PENTING -- butuh 2 hal yang belum bisa diisi dari sini karena
// bergantung pada proses rilis produksi, BUKAN kode:
//   1. `sha256_cert_fingerprints` di assetlinks.json harus diganti dengan
//      fingerprint SHA-256 dari keystore RILIS (bukan debug) yang dipakai
//      buat sign APK/AAB final. Cara ambil:
//      `keytool -list -v -keystore <path-keystore-rilis> -alias <alias>`
//      lalu salin baris "SHA256:".
//   2. `appID` di apple-app-site-association harus diganti prefix
//      "TEAMID" dengan Apple Developer Team ID asli (cuma ada kalau daftar
//      akun Apple Developer Program, $99/tahun) -- KosKita saat ini belum
//      submit ke App Store jadi bagian iOS ini murni scaffolding.
// Tanpa langkah manual di atas, OS akan GAGAL verifikasi App Link dan
// tautan tetap terbuka di browser biasa (bukan error, cuma fallback aman).
// ============================================================
Route::get('/.well-known/assetlinks.json', function () {
    return response()->json([
        [
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => 'com.koskita.frontend',
                'sha256_cert_fingerprints' => [
                    'GANTI_DENGAN_SHA256_KEYSTORE_RILIS',
                ],
            ],
        ],
    ]);
})->name('well-known.assetlinks');

Route::get('/.well-known/apple-app-site-association', function () {
    return response()->json([
        'applinks' => [
            'apps' => [],
            'details' => [
                [
                    'appID' => 'TEAMID.com.koskita.frontend',
                    'paths' => ['/kos/*'],
                ],
            ],
        ],
    ]);
})->name('well-known.apple-app-site-association');

// ============================================================
// Situs Publik KosKita -- untuk pengunjung & penyewa lewat browser.
// Route/nama sengaja beda total dari panel admin (/daftar & /masuk,
// bukan /register & /login) supaya tidak pernah tabrakan dengan
// AdminAuthController di bawah, walau sama-sama guard sesi "web".
// ============================================================
Route::get('/', [WebHomeController::class, 'index'])->name('web.home');

Route::get('/kos', [WebKosController::class, 'index'])->name('web.kos.index');
// "/kos/lokasi/{location}" WAJIB didaftar sebelum "/kos/{id}" -- kalau
// tidak, akan ketangkap wildcard {id} dan dianggap id="lokasi", sama
// seperti catatan "export sebelum {id}" di admin.koses.
Route::get('/kos/lokasi/{location}', [WebKosController::class, 'byLocation'])->name('web.kos.location');
Route::get('/bandingkan', [WebKosController::class, 'compare'])->name('web.kos.compare');
Route::get('/kos/{id}', [WebKosController::class, 'show'])->name('web.kos.show');

// Tips Ngekos -- konten SEO publik, sitemap.xml.
Route::get('/tips', [WebTipsController::class, 'index'])->name('web.tips.index');
Route::get('/tips/{slug}', [WebTipsController::class, 'show'])->name('web.tips.show');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

// Widget pencarian yang bisa ditanam di situs lain lewat <iframe> -- lihat
// WidgetController untuk kenapa halamannya sengaja mandiri, tanpa layout.
Route::view('/widget', 'web.widget.instructions')->name('web.widget.instructions');
Route::get('/widget/search', [WidgetController::class, 'search'])->name('web.widget.search');

Route::get('/daftar', [WebAuthController::class, 'showRegister'])->name('web.register');
Route::post('/daftar', [WebAuthController::class, 'register'])->name('web.register.submit');
Route::get('/masuk', [WebAuthController::class, 'showLogin'])->name('web.login');
Route::post('/masuk', [WebAuthController::class, 'login'])->name('web.login.submit');
Route::post('/keluar', [WebAuthController::class, 'logout'])->name('web.logout');

// "Masuk dengan Google" -- lihat catatan GOOGLE_CLIENT_ID di .env untuk
// cara aktifkan. Route tetap didaftarkan meski belum dikonfigurasi
// (controller sendiri yang jaga & kasih pesan error ramah).
Route::get('/auth/google', [WebAuthController::class, 'redirectToGoogle'])->name('web.google.redirect');
Route::get('/auth/google/callback', [WebAuthController::class, 'handleGoogleCallback'])->name('web.google.callback');

// Lupa/atur ulang password -- pakai PasswordBroker bawaan Laravel (tabel
// password_reset_tokens sudah ada dari migration default), cuma tampilan &
// notifikasi email-nya yang dikustom (lihat ResetPasswordNotification).
Route::get('/lupa-password', [WebAuthController::class, 'showForgotPassword'])->name('web.password.request');
Route::post('/lupa-password', [WebAuthController::class, 'sendResetLink'])->name('web.password.email');
Route::get('/reset-password/{token}', [WebAuthController::class, 'showResetPassword'])->name('web.password.reset');
Route::post('/reset-password', [WebAuthController::class, 'resetPassword'])->name('web.password.update');

// Tantangan verifikasi 2 langkah -- SENGAJA di luar grup auth.web (user
// belum benar-benar login sampai kodenya benar, lihat WebAuthController@
// login). Identitas siapa yang sedang menantang disimpan di sesi, bukan
// query string/parameter, supaya tidak bisa ditebak/diganti orang lain.
// throttle:5,1 -- kode 6 digit cuma 1 juta kemungkinan, wajib dibatasi
// biar tidak bisa ditebak brute-force dalam jendela 10 menit masa berlakunya.
Route::get('/verifikasi-2fa', [WebAuthController::class, 'showTwoFactorChallenge'])->name('web.2fa.challenge');
Route::post('/verifikasi-2fa', [WebAuthController::class, 'verifyTwoFactor'])->name('web.2fa.verify')->middleware('throttle:5,1');
Route::post('/verifikasi-2fa/kirim-ulang', [WebAuthController::class, 'resendTwoFactorCode'])->name('web.2fa.resend')->middleware('throttle:3,1');

Route::middleware('auth.web')->group(function () {
    Route::post('/email/kirim-ulang', [WebAuthController::class, 'resendVerification'])->name('web.verification.resend');
    Route::post('/2fa/aktifkan', [WebAuthController::class, 'startEnableTwoFactor'])->name('web.2fa.enable.start');
    Route::post('/2fa/aktifkan/konfirmasi', [WebAuthController::class, 'confirmEnableTwoFactor'])->name('web.2fa.enable.confirm')->middleware('throttle:5,1');
    Route::post('/2fa/nonaktifkan', [WebAuthController::class, 'disableTwoFactor'])->name('web.2fa.disable');
    Route::get('/pengaturan', [WebProfileController::class, 'show'])->name('web.profile');
    Route::post('/notifikasi/preferensi', [WebNotificationPreferencesController::class, 'update'])->name('web.notifications.preferences');
    Route::post('/profil/avatar', [WebAvatarController::class, 'store'])->name('web.avatar.upload');
    Route::delete('/profil/avatar', [WebAvatarController::class, 'destroy'])->name('web.avatar.delete');
    Route::post('/kos/{id}/favorit', [WebKosController::class, 'toggleFavorite'])->name('web.kos.favorite');
    Route::post('/kos/{id}/waitlist', [WebKosController::class, 'toggleWaitlist'])->name('web.kos.waitlist');
    Route::post('/kos/{id}/lapor', [WebKosController::class, 'report'])->name('web.kos.report');
    Route::get('/rekomendasi', [WebRecommendationController::class, 'index'])->name('web.recommendations');
    Route::post('/chatbot', [WebChatbotController::class, 'respond'])->name('web.chatbot')->middleware('throttle:20,1');
    Route::get('/booking-saya', [WebBookingController::class, 'index'])->name('web.bookings.index');
    Route::post('/booking-saya/{id}/batal', [WebBookingController::class, 'cancel'])->name('web.bookings.cancel');
    Route::get('/booking-saya/{id}/bukti', [WebBookingController::class, 'receipt'])->name('web.bookings.receipt');

    Route::middleware('verified.web')->group(function () {
        Route::post('/kos/{id}/ulasan', [WebKosController::class, 'storeReview'])->name('web.kos.reviews.store');
        Route::post('/kos/{id}/booking', [WebBookingController::class, 'store'])->name('web.bookings.store');

        // Pesan langsung penyewa <-> pemilik -- dipakai kedua peran, lihat
        // WebMessageController (logikanya sama dengan Api\MessageController).
        Route::get('/pesan', [WebMessageController::class, 'index'])->name('web.messages.index');
        Route::get('/pesan/{userId}', [WebMessageController::class, 'thread'])->name('web.messages.thread');
        Route::post('/pesan', [WebMessageController::class, 'store'])->name('web.messages.store')->middleware('throttle:30,1');
    });
});

// Portal Pemilik (situs) -- akun role "owner" yang sama dipakai di aplikasi
// Flutter bisa login lewat /masuk yang sama, lalu diarahkan ke sini.
// Read-mostly (lihat kos & kelola booking); tambah/edit kos dengan upload
// foto tetap lewat aplikasi -- lihat catatan di WebOwnerController.
Route::prefix('pemilik')->middleware(['auth.web', 'owner.web'])->group(function () {
    Route::get('/', [WebOwnerController::class, 'dashboard'])->name('web.owner.dashboard');

    Route::get('/kos', [WebOwnerController::class, 'koses'])->name('web.owner.koses.index');
    Route::get('/kos/ekspor', [WebOwnerController::class, 'exportCsv'])->name('web.owner.koses.export');
    Route::post('/kos/impor', [WebOwnerController::class, 'importCsv'])->name('web.owner.koses.import');
    Route::get('/kos/{id}', [WebOwnerController::class, 'kosShow'])->name('web.owner.koses.show');
    Route::post('/kos/{kosId}/ulasan/{reviewId}/balas', [WebOwnerController::class, 'replyToReview'])->name('web.owner.koses.reviews.reply');

    Route::get('/booking', [WebOwnerController::class, 'bookings'])->name('web.owner.bookings.index');
    Route::post('/booking/{id}/status', [WebOwnerController::class, 'updateBookingStatus'])->name('web.owner.bookings.status');
    Route::post('/booking/{id}/pembayaran', [WebOwnerController::class, 'updateBookingPayment'])->name('web.owner.bookings.payment');
    Route::post('/booking/tandai-lunas-massal', [WebOwnerController::class, 'bulkMarkPaid'])->name('web.owner.bookings.bulk-paid');

    Route::get('/analitik', [WebOwnerController::class, 'analytics'])->name('web.owner.analytics');

    Route::get('/pengaturan', [WebOwnerController::class, 'settings'])->name('web.owner.settings');
    Route::post('/verifikasi', [WebOwnerController::class, 'submitVerification'])->name('web.owner.verification.submit');
    Route::post('/qris', [WebOwnerController::class, 'uploadQris'])->name('web.owner.qris.upload');
    Route::delete('/qris', [WebOwnerController::class, 'deleteQris'])->name('web.owner.qris.delete');
});

// Otentikasi Admin (Web)
Route::get('/login', [AdminAuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AdminAuthController::class, 'login'])->name('admin.login.submit');
Route::post('/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

// Group Web Admin Panel (Proteksi login & role admin)
Route::prefix('admin')->middleware(\App\Http\Middleware\IsAdmin::class)->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/interactions', [AdminDashboardController::class, 'interactions'])->name('admin.interactions');

    // Data Responden & Trace Rekomendasi
    // (rute "export" WAJIB didaftar sebelum "{id}" -- kalau tidak,
    // "/users/export" akan ketangkap oleh wildcard {id} dan dianggap
    // id="export", bukan rute export-nya sendiri)
    Route::get('/users/export', [AdminUserController::class, 'exportCsv'])->name('admin.users.export');
    Route::get('/users', [AdminUserController::class, 'index'])->name('admin.users');
    Route::get('/users/{id}', [AdminUserController::class, 'show'])->name('admin.users.show');
    Route::put('/users/{id}', [AdminUserController::class, 'update'])->name('admin.users.update');
    Route::delete('/users/{id}', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');
    Route::put('/users/{id}/verifikasi', [AdminUserController::class, 'verifyOwner'])->name('admin.users.verify-owner');

    // CRUD Kos
    Route::get('/koses/export', [AdminKosController::class, 'exportCsv'])->name('admin.koses.export');
    Route::get('/koses', [AdminKosController::class, 'index'])->name('admin.koses.index');
    Route::get('/koses/create', [AdminKosController::class, 'create'])->name('admin.koses.create');
    Route::post('/koses', [AdminKosController::class, 'store'])->name('admin.koses.store');
    Route::get('/koses/{id}/edit', [AdminKosController::class, 'edit'])->name('admin.koses.edit');
    Route::put('/koses/{id}', [AdminKosController::class, 'update'])->name('admin.koses.update');
    Route::delete('/koses/{id}', [AdminKosController::class, 'destroy'])->name('admin.koses.destroy');
    Route::delete('/koses/{kos}/images/{image}', [AdminKosController::class, 'destroyImage'])->name('admin.koses.images.destroy');
    Route::put('/koses/{id}/verifikasi', [AdminKosController::class, 'toggleVerified'])->name('admin.koses.toggle-verified');

    // Master Data: Fasilitas & Aturan
    Route::get('/facilities', [AdminFacilityController::class, 'index'])->name('admin.facilities.index');
    Route::post('/facilities', [AdminFacilityController::class, 'store'])->name('admin.facilities.store');
    Route::put('/facilities/{id}', [AdminFacilityController::class, 'update'])->name('admin.facilities.update');
    Route::delete('/facilities/{id}', [AdminFacilityController::class, 'destroy'])->name('admin.facilities.destroy');

    Route::get('/rules', [AdminRuleController::class, 'index'])->name('admin.rules.index');
    Route::post('/rules', [AdminRuleController::class, 'store'])->name('admin.rules.store');
    Route::put('/rules/{id}', [AdminRuleController::class, 'update'])->name('admin.rules.update');
    Route::delete('/rules/{id}', [AdminRuleController::class, 'destroy'])->name('admin.rules.destroy');

    // Artikel "Tips Ngekos" (konten SEO publik)
    Route::get('/articles', [AdminArticleController::class, 'index'])->name('admin.articles.index');
    Route::get('/articles/create', [AdminArticleController::class, 'create'])->name('admin.articles.create');
    Route::post('/articles', [AdminArticleController::class, 'store'])->name('admin.articles.store');
    Route::get('/articles/{id}/edit', [AdminArticleController::class, 'edit'])->name('admin.articles.edit');
    Route::put('/articles/{id}', [AdminArticleController::class, 'update'])->name('admin.articles.update');
    Route::delete('/articles/{id}', [AdminArticleController::class, 'destroy'])->name('admin.articles.destroy');

    // Booking
    Route::get('/bookings/export', [AdminBookingController::class, 'exportCsv'])->name('admin.bookings.export');
    Route::get('/bookings', [AdminBookingController::class, 'index'])->name('admin.bookings.index');
    Route::get('/bookings/{id}', [AdminBookingController::class, 'show'])->name('admin.bookings.show');
    Route::put('/bookings/{id}', [AdminBookingController::class, 'update'])->name('admin.bookings.update');

    // Pengumuman (broadcast notifikasi ke penyewa/pemilik)
    Route::get('/broadcasts', [AdminBroadcastController::class, 'index'])->name('admin.broadcasts.index');
    Route::post('/broadcasts', [AdminBroadcastController::class, 'store'])->name('admin.broadcasts.store');
    Route::delete('/broadcasts/{id}', [AdminBroadcastController::class, 'destroy'])->name('admin.broadcasts.destroy');

    // Laporan/flag dari pengguna atas kos atau ulasan mencurigakan
    Route::get('/reports', [AdminReportController::class, 'index'])->name('admin.reports.index');
    Route::put('/reports/{id}', [AdminReportController::class, 'update'])->name('admin.reports.update');

    // Pencarian kos yang hasilnya nihil -- lihat SearchLogService.
    Route::get('/pencarian-nihil', [AdminSearchLogController::class, 'index'])->name('admin.search-logs.index');
});

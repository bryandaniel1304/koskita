<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminKosController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminFacilityController;
use App\Http\Controllers\Admin\AdminRuleController;
use App\Http\Controllers\Admin\AdminBookingController;

// Halaman depan dashboard API kustom
Route::get('/', function () {
    return view('welcome');
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
    Route::get('/users', [AdminUserController::class, 'index'])->name('admin.users');
    Route::get('/users/{id}', [AdminUserController::class, 'show'])->name('admin.users.show');
    Route::put('/users/{id}', [AdminUserController::class, 'update'])->name('admin.users.update');
    Route::delete('/users/{id}', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');

    // CRUD Kos
    Route::get('/koses', [AdminKosController::class, 'index'])->name('admin.koses.index');
    Route::get('/koses/create', [AdminKosController::class, 'create'])->name('admin.koses.create');
    Route::post('/koses', [AdminKosController::class, 'store'])->name('admin.koses.store');
    Route::get('/koses/{id}/edit', [AdminKosController::class, 'edit'])->name('admin.koses.edit');
    Route::put('/koses/{id}', [AdminKosController::class, 'update'])->name('admin.koses.update');
    Route::delete('/koses/{id}', [AdminKosController::class, 'destroy'])->name('admin.koses.destroy');
    Route::delete('/koses/{kos}/images/{image}', [AdminKosController::class, 'destroyImage'])->name('admin.koses.images.destroy');

    // Master Data: Fasilitas & Aturan
    Route::get('/facilities', [AdminFacilityController::class, 'index'])->name('admin.facilities.index');
    Route::post('/facilities', [AdminFacilityController::class, 'store'])->name('admin.facilities.store');
    Route::put('/facilities/{id}', [AdminFacilityController::class, 'update'])->name('admin.facilities.update');
    Route::delete('/facilities/{id}', [AdminFacilityController::class, 'destroy'])->name('admin.facilities.destroy');

    Route::get('/rules', [AdminRuleController::class, 'index'])->name('admin.rules.index');
    Route::post('/rules', [AdminRuleController::class, 'store'])->name('admin.rules.store');
    Route::put('/rules/{id}', [AdminRuleController::class, 'update'])->name('admin.rules.update');
    Route::delete('/rules/{id}', [AdminRuleController::class, 'destroy'])->name('admin.rules.destroy');

    // Booking
    Route::get('/bookings', [AdminBookingController::class, 'index'])->name('admin.bookings.index');
    Route::get('/bookings/{id}', [AdminBookingController::class, 'show'])->name('admin.bookings.show');
    Route::put('/bookings/{id}', [AdminBookingController::class, 'update'])->name('admin.bookings.update');
});

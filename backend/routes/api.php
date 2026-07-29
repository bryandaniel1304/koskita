<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\KosController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Api\BookingController;

// Public Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // Profile
    Route::get('/profile', [AuthController::class, 'getProfile']);
    Route::post('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/profile/reset-interactions', [AuthController::class, 'resetInteractions']);
    
    // Kos Listings & Interactions
    Route::get('/kos', [KosController::class, 'index']);
    Route::get('/kos/{id}', [KosController::class, 'show']);
    Route::post('/kos/{id}/rate', [KosController::class, 'rate']);
    Route::get('/favorites', [KosController::class, 'favorites']);

    // Recommendations
    Route::get('/recommendations', [RecommendationController::class, 'index']);

    // Booking
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::post('/bookings/{id}/cancel', [BookingController::class, 'cancel']);
});

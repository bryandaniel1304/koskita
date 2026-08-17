<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\RespondentController;
use App\Http\Controllers\SusController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::prefix('evaluation')->name('evaluation.')->group(function () {
    Route::get('/', [EvaluationController::class, 'index'])->name('index');
    Route::post('/run-baselines', [EvaluationController::class, 'runBaselines'])->name('run-baselines');
    Route::post('/compare-alphas', [EvaluationController::class, 'compareAlphas'])->name('compare-alphas');
});

Route::get('/respondents', [RespondentController::class, 'index'])->name('respondents.index');

Route::prefix('sus')->name('sus.')->group(function () {
    Route::get('/', [SusController::class, 'index'])->name('index');
    Route::get('/isi', [SusController::class, 'create'])->name('create');
    Route::post('/', [SusController::class, 'store'])->name('store');
});

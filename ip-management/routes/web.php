<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

// Authentication Routes (REQUIRED)
require __DIR__.'/auth.php';

// Dashboard Routes (protected)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/clients/{id}', [DashboardController::class, 'showClient'])->name('clients.show');
    Route::get('/clients/{id}/installer', [DashboardController::class, 'downloadInstaller'])->name('clients.installer');
});

// Root redirects to dashboard
Route::get('/', function () {
    return redirect()->route('dashboard');
})->middleware('auth');

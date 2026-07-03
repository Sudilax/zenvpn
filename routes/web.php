<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\VpnDeviceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Dashboard (requires auth + email verified)
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Authenticated routes
Route::middleware(['auth', 'verified'])->group(function () {
    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // VPN Devices
    Route::post('/devices', [VpnDeviceController::class, 'store'])->name('devices.store');
    Route::patch('/devices/{device}', [VpnDeviceController::class, 'update'])->name('devices.update');
    Route::delete('/devices/{device}', [VpnDeviceController::class, 'destroy'])->name('devices.destroy');
});

require __DIR__ . '/auth.php';

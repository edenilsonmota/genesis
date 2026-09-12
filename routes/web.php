<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'user.active'])->group(function (): void {
    Route::get('/trocar-senha', [PasswordController::class, 'edit'])->name('password.change.edit');
    Route::put('/trocar-senha', [PasswordController::class, 'update'])->name('password.change.update');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::middleware('password.changed')->group(function (): void {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
    });
});

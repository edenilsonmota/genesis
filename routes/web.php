<?php

use App\Http\Controllers\AreaController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\ChurchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MemberMembershipController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PostalCodeController;
use App\Http\Controllers\StateCityController;
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

        Route::resource('members', MemberController::class)
            ->only(['index', 'create', 'store', 'show', 'edit', 'update']);
        Route::patch('/members/{member}/inactivate', [MemberController::class, 'inactivate'])
            ->name('members.inactivate');
        Route::prefix('/members/{member}/church-memberships')->name('members.memberships.')->group(function (): void {
            Route::post('/', [MemberMembershipController::class, 'store'])->name('store');
            Route::patch('/{membership}/primary', [MemberMembershipController::class, 'primary'])->name('primary');
            Route::patch('/{membership}/end', [MemberMembershipController::class, 'end'])->name('end');
        });

        Route::prefix('organization')->name('organization.')->group(function (): void {
            Route::get('/', OrganizationController::class)->name('index');
            Route::post('/area', [AreaController::class, 'store'])->name('area.store');
            Route::put('/area/{area}', [AreaController::class, 'update'])->name('area.update');
            Route::post('/churches', [ChurchController::class, 'store'])->name('churches.store');
            Route::put('/churches/{church}', [ChurchController::class, 'update'])->name('churches.update');
            Route::patch('/churches/{church}/inactivate', [ChurchController::class, 'inactivate'])->name('churches.inactivate');
            Route::get('/states/{state}/cities', StateCityController::class)->name('cities.index');
            Route::get('/postal-codes/{postalCode}', PostalCodeController::class)
                ->middleware('throttle:30,1')
                ->name('postal-codes.show');
        });
    });
});

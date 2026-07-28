<?php

use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeveloperController;
use App\Http\Controllers\Admin\LeadController;
use App\Http\Controllers\Admin\PropertyController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

// ---------------------------------------------------------------- guest
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// ---------------------------------------------------------------- admin panel
Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', EnsureAdmin::class])
    ->group(function () {

        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals');
        Route::post('/approvals/{user}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
        Route::post('/approvals/{user}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject');

        Route::get('/developers', [DeveloperController::class, 'index'])->name('developers');
        Route::post('/developers', [DeveloperController::class, 'store'])->name('developers.store');
        Route::patch('/developers/{developer}', [DeveloperController::class, 'update'])->name('developers.update');

        Route::get('/properties', [PropertyController::class, 'index'])->name('properties');
        Route::post('/properties', [PropertyController::class, 'store'])->name('properties.store');
        Route::patch('/properties/{property}', [PropertyController::class, 'update'])->name('properties.update');

        Route::get('/leads', [LeadController::class, 'index'])->name('leads');

        Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
        Route::patch('/settings/fields/{field}', [SettingsController::class, 'toggleField'])->name('settings.field');
        Route::patch('/settings/theme', [SettingsController::class, 'updateTheme'])->name('settings.theme');
    });

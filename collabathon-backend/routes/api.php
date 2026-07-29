<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DeveloperController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\PropertyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API (Sanctum tokens)
|--------------------------------------------------------------------------
| Every list route is paginated and accepts ?page, ?per_page, ?search, ?sort,
| ?direction plus its own filters. per_page is capped server-side.
*/

Route::prefix('v1')->group(function () {

    // ---------------------------------------------------------------- public
    Route::post('auth/register', [AuthController::class, 'register'])
        ->middleware('throttle:10,1');

    // Tight limit — this is the credential-stuffing surface.
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1');

    // ---------------------------------------------------------------- authenticated
    Route::middleware('auth:sanctum')->group(function () {

        Route::get('auth/me', [AuthController::class, 'me']);
        Route::get('dashboard', DashboardController::class);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        // Catalogue
        Route::get('developers', [DeveloperController::class, 'index']);
        Route::get('developers/{developer}', [DeveloperController::class, 'show']);
        Route::get('developers/{developer}/properties', [DeveloperController::class, 'properties']);

        Route::get('properties', [PropertyController::class, 'index']);
        Route::get('properties/{property}', [PropertyController::class, 'show']);

        // Broker actions on a listing
        Route::post('properties/{property}/view', [LeadController::class, 'view']);
        Route::post('properties/{property}/interest', [LeadController::class, 'interest']);

        // Leads — scoped to the caller's role inside the controller
        Route::get('leads', [LeadController::class, 'index']);
        Route::patch('leads/{lead}', [LeadController::class, 'respond']);
    });
});

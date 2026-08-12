<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DeveloperController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\MyPropertyController;
use App\Http\Controllers\Api\PartnerController;
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

    // Channel-partner sign-in. Tighter than the above: a code costs money to deliver
    // once a real SMS provider is wired in, so this is the one endpoint worth
    // rate-limiting below the site-wide default even before that's true.
    Route::post('auth/otp/send', [AuthController::class, 'sendOtp'])
        ->middleware('throttle:6,1');
    Route::post('auth/otp/verify', [AuthController::class, 'verifyOtp'])
        ->middleware('throttle:15,1');

    // ---------------------------------------------------------------- authenticated
    Route::middleware('auth:sanctum')->group(function () {

        Route::get('auth/me', [AuthController::class, 'me']);
        Route::get('dashboard', DashboardController::class);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::delete('auth/account', [AuthController::class, 'deleteAccount']);

        // Push registration. Sent right after sign-in and cleared on sign-out.
        Route::post('auth/device-token', [AuthController::class, 'registerDevice']);
        Route::delete('auth/device-token', [AuthController::class, 'forgetDevice']);

        // Catalogue
        Route::get('developers', [DeveloperController::class, 'index']);
        Route::get('developers/{developer}', [DeveloperController::class, 'show']);
        Route::get('developers/{developer}/properties', [DeveloperController::class, 'properties']);

        // Developer's own inventory — every assignment, including ones still awaiting
        // their decision. Must sit BEFORE the {property} routes so `my` is not read
        // as a property id.
        Route::get('my/properties/summary', [MyPropertyController::class, 'summary']);
        Route::get('my/properties/filters', [MyPropertyController::class, 'filterOptions']);
        Route::get('my/properties', [MyPropertyController::class, 'index']);
        Route::get('my/properties/{property}', [MyPropertyController::class, 'show']);
        Route::patch('my/properties/{property}/response', [MyPropertyController::class, 'respond']);

        Route::get('properties', [PropertyController::class, 'index']);
        Route::get('properties/{property}', [PropertyController::class, 'show']);

        // Broker actions on a listing
        Route::post('properties/{property}/view', [LeadController::class, 'view']);
        Route::post('properties/{property}/interest', [LeadController::class, 'interest']);

        // Leads — scoped to the caller's role inside the controller
        Route::get('leads', [LeadController::class, 'index']);
        Route::patch('leads/{lead}', [LeadController::class, 'respond']);

        // Channel partners — the developer's accepted brokers, one row per broker.
        // `filters` is declared before `{broker}` so it is not swallowed as an id.
        Route::get('partners/filters', [PartnerController::class, 'filterOptions']);
        Route::get('partners', [PartnerController::class, 'index']);
        Route::get('partners/{broker}', [PartnerController::class, 'show']);
        Route::get('partners/{broker}/projects', [PartnerController::class, 'projects']);
    });
});

<?php

use App\Http\Controllers\Admin\AmenityController;
use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\ChannelPartnerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeveloperController;
use App\Http\Controllers\Admin\GeocodeController;
use App\Http\Controllers\Admin\LeadController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\MeasurementUnitController;
use App\Http\Controllers\Admin\ProjectTypeController;
use App\Http\Controllers\Admin\PropertyController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\UnitTypeController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;

// Conditional, not a flat redirect to /login: a signed-in admin landing on the root
// should go to the panel. Sending them to /login instead only worked because the guest
// middleware bounced them back — which is the redirect loop, not a route.
Route::get('/', fn () => redirect()->to(
    auth()->check() ? route('admin.dashboard') : route('login')
));

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

        Route::get('/dashboard', DashboardController::class)->name('dashboard')
            ->middleware("can:view-module,'dashboard'");

        Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals')
            ->middleware("can:view-module,'approvals'");
        // Declared before `{user}` so "decided" is not read as a broker id.
        Route::get('/approvals/decided', [ApprovalController::class, 'decided'])->name('approvals.decided')
            ->middleware("can:view-module,'approvals'");
        Route::get('/approvals/{user}', [ApprovalController::class, 'show'])->name('approvals.show')
            ->middleware("can:view-module,'approvals'");
        Route::post('/approvals/{user}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
        Route::post('/approvals/{user}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject');
        Route::post('/approvals/{user}/password', [ApprovalController::class, 'resetPassword'])
            ->name('approvals.password');

        Route::get('/cp', [ChannelPartnerController::class, 'index'])->name('cp')
            ->middleware("can:view-module,'cp'");

        // Address lookup behind the developer form's "find on map" control.
        Route::get('/geocode', GeocodeController::class)->name('geocode');

        Route::get('/developers', [DeveloperController::class, 'index'])->name('developers')
            ->middleware("can:view-module,'developers'");
        Route::get('/developers/{developer}', [DeveloperController::class, 'show'])->name('developers.show')
            ->middleware("can:view-module,'developers'");
        Route::post('/developers', [DeveloperController::class, 'store'])->name('developers.store');
        Route::delete('/developers/{developer}', [DeveloperController::class, 'destroy'])->name('developers.destroy');
        Route::patch('/developers/{developer}', [DeveloperController::class, 'update'])->name('developers.update');
        Route::post('/developers/{developer}/password', [DeveloperController::class, 'resetPassword'])
            ->name('developers.password');

        Route::get('/properties', [PropertyController::class, 'index'])->name('properties')
            ->middleware("can:view-module,'properties'");
        // `create` is declared before `{property}` so the literal segment wins the match.
        Route::get('/properties/create', [PropertyController::class, 'create'])->name('properties.create')
            ->middleware("can:view-module,'properties'");
        Route::get('/properties/{property}', [PropertyController::class, 'show'])->name('properties.show')
            ->middleware("can:view-module,'properties'");
        Route::get('/properties/{property}/edit', [PropertyController::class, 'edit'])->name('properties.edit')
            ->middleware("can:view-module,'properties'");
        Route::post('/properties', [PropertyController::class, 'store'])->name('properties.store');
        Route::patch('/properties/{property}', [PropertyController::class, 'update'])->name('properties.update');
        Route::delete('/properties/{property}', [PropertyController::class, 'destroy'])->name('properties.destroy');

        Route::get('/leads', [LeadController::class, 'index'])->name('leads')
            ->middleware("can:view-module,'leads'");
        Route::get('/leads/{lead}', [LeadController::class, 'show'])->name('leads.show')
            ->middleware("can:view-module,'leads'");
        Route::patch('/leads/{lead}', [LeadController::class, 'update'])->name('leads.update');

        Route::get('/settings', [SettingsController::class, 'index'])->name('settings')
            ->middleware("can:view-module,'settings'");
        Route::patch('/settings/fields/{field}', [SettingsController::class, 'toggleField'])->name('settings.field');
        Route::patch('/settings/theme', [SettingsController::class, 'updateTheme'])->name('settings.theme');
        Route::post('/settings/announce', [AnnouncementController::class, 'store'])->name('settings.announce');

        // Uploading a key that can send as the whole Firebase project is a super-admin
        // action; the controller re-checks, this keeps it off the route for everyone else.
        Route::middleware('can:manage-team')->group(function () {
            Route::post('/settings/firebase', [SettingsController::class, 'updateFirebase'])->name('settings.firebase');
            Route::delete('/settings/firebase', [SettingsController::class, 'forgetFirebase'])->name('settings.firebase.forget');
            Route::post('/settings/firebase/test', [SettingsController::class, 'testFirebase'])->name('settings.firebase.test');
        });
        Route::patch('/settings/mail', [SettingsController::class, 'updateMail'])->name('settings.mail');
        Route::post('/settings/mail/test', [SettingsController::class, 'testMail'])->name('settings.mail.test');

        // Measurement units — the "Project extent metric" options.
        Route::prefix('settings/measurement-units')->name('settings.measurement-units.')->group(function () {
            Route::post('/', [MeasurementUnitController::class, 'store'])->name('store');
            Route::patch('/{measurementUnit}', [MeasurementUnitController::class, 'update'])->name('update');
            Route::delete('/{measurementUnit}', [MeasurementUnitController::class, 'destroy'])->name('destroy');
        });

        // Unit type master data — the list the intake form's unit rows pick from.
        Route::prefix('settings/unit-types')->name('settings.unit-types.')->group(function () {
            Route::post('/', [UnitTypeController::class, 'store'])->name('store');
            Route::patch('/{unitType}', [UnitTypeController::class, 'update'])->name('update');
            Route::delete('/{unitType}', [UnitTypeController::class, 'destroy'])->name('destroy');
        });

        // Amenity master data — the checkbox grid on the intake form's specifications step.
        Route::prefix('settings/amenities')->name('settings.amenities.')->group(function () {
            Route::post('/', [AmenityController::class, 'store'])->name('store');
            Route::patch('/{amenity}', [AmenityController::class, 'update'])->name('update');
            Route::delete('/{amenity}', [AmenityController::class, 'destroy'])->name('destroy');
        });

        // Location master data — country -> state -> city, edited from the settings page.
        Route::prefix('settings/locations')->name('settings.locations.')->group(function () {
            Route::post('/countries', [LocationController::class, 'storeCountry'])->name('countries.store');
            Route::patch('/countries/{country}', [LocationController::class, 'updateCountry'])->name('countries.update');
            Route::delete('/countries/{country}', [LocationController::class, 'destroyCountry'])->name('countries.destroy');

            Route::post('/states', [LocationController::class, 'storeState'])->name('states.store');
            Route::patch('/states/{state}', [LocationController::class, 'updateState'])->name('states.update');
            Route::delete('/states/{state}', [LocationController::class, 'destroyState'])->name('states.destroy');

            Route::post('/cities', [LocationController::class, 'storeCity'])->name('cities.store');
            Route::patch('/cities/{city}', [LocationController::class, 'updateCity'])->name('cities.update');
            Route::delete('/cities/{city}', [LocationController::class, 'destroyCity'])->name('cities.destroy');
        });

        // Project type master data — the list the project form and filters pick from.
        Route::prefix('settings/project-types')->name('settings.project-types.')->group(function () {
            Route::post('/', [ProjectTypeController::class, 'store'])->name('store');
            Route::patch('/{projectType}', [ProjectTypeController::class, 'update'])->name('update');
            Route::delete('/{projectType}', [ProjectTypeController::class, 'destroy'])->name('destroy');
        });

        // ------------------------------------------------------ Super Admin only
        Route::middleware('can:manage-team')->group(function () {
            Route::get('/roles', [RoleController::class, 'index'])->name('roles');
            Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
            Route::patch('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
            Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

            Route::get('/team', [TeamController::class, 'index'])->name('team');
            Route::post('/team', [TeamController::class, 'store'])->name('team.store');
            Route::patch('/team/{user}', [TeamController::class, 'update'])->name('team.update');
            Route::post('/team/{user}/password', [TeamController::class, 'resetPassword'])->name('team.password');
            Route::delete('/team/{user}', [TeamController::class, 'destroy'])->name('team.destroy');
        });
    });

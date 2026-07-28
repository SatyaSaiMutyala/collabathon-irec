<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::get('/login', function () {
    return view('auth.login');
});

Route::prefix('admin')->group(function () {
    Route::get('/dashboard', fn () => view('admin.dashboard'));
    Route::get('/approvals', fn () => view('admin.approvals'));
    Route::get('/developers', fn () => view('admin.developers'));
    Route::get('/properties', fn () => view('admin.properties'));
    Route::get('/leads', fn () => view('admin.leads'));
    Route::get('/settings', fn () => view('admin.settings'));
});

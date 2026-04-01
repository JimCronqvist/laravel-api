<?php

use Cronqvist\Api\Auth\SSO\Http\Controllers\SsoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Laravel-API Package Routes - Auth
|--------------------------------------------------------------------------
|
| Handles all needed authentication endpoints for this package
|
*/

Route::namespace('Cronqvist\Api\Auth\SSO\Http\Controllers')->middleware('api')->prefix('/api')->group(function() {

    //
    // Public endpoints
    //

    Route::get('/auth/sso/{provider}/redirect', [SsoController::class, 'redirect'])->name('sso.redirect');
    Route::get('/auth/sso/{provider}/callback', [SsoController::class, 'callback'])->name('sso.callback');
    Route::get('/auth/sso/providers/{domain?}', [SsoController::class, 'providers'])->name('sso.providers');
    Route::post('/auth/sso/exchange', [SsoController::class, 'exchange'])->name('sso.exchange');
});
<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Laravel-API Package Routes - Auth
|--------------------------------------------------------------------------
|
| Handles all needed authentication endpoints for this package
|
*/

Route::namespace('Cronqvist\Api\Http\Controllers')->middleware('api')->prefix('/api')->group(function() {

    //
    // Public endpoints
    //

    Route::post('/auth/login', 'AuthController@login');
    Route::post('/auth/refresh', 'AuthController@refresh')->name('authRefreshToken');
    Route::post('/auth/send-reset-link', 'AuthController@sendResetLink')->middleware('throttle:5,1');
    Route::post('/auth/reset', 'AuthController@reset')->middleware('throttle:5,1');
    //
    // Authenticated endpoints
    //

    Route::middleware('auth:api')->group(function() {
        Route::get('/auth/user', 'AuthController@user');
        Route::any('/auth/logout', 'AuthController@logout');
    });
});
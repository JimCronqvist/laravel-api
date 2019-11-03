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

    //
    // Authenticated endpoints
    //

    Route::middleware('auth:api')->group(function() {
        Route::get('/auth/user', 'AuthController@user');
        Route::post('/auth/logout', 'AuthController@logout');
    });
});
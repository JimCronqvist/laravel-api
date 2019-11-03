<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Laravel-API Package Routes - Merge
|--------------------------------------------------------------------------
|
| Perform multiple HTTP requests in one
|
*/

Route::namespace('Cronqvist\Api\Http\Controllers')->middleware('api')->prefix('/api')->group(function() {

    //
    // Public endpoints
    //

    Route::any('/merge', 'ApiMergeController@merge');

});

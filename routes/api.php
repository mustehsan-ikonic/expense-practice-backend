<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Routes are registered here and mounted under the "/api" prefix by
| bootstrap/app.php. The concrete expense/account routes are added while
| building the domain.
|
*/

Route::get('/ping', fn () => response()->json(['message' => 'pong']));

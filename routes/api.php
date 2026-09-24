<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\ExpenseController;
use Illuminate\Support\Facades\Route;

Route::get('/expenses', [ExpenseController::class, 'index']);
Route::post('/expenses', [ExpenseController::class, 'store']);
Route::get('/expenses/{expense}', [ExpenseController::class, 'show']);

Route::get('/accounts/{account}', [AccountController::class, 'show']);
Route::get('/accounts/{account}/expenses', [AccountController::class, 'expenses']);

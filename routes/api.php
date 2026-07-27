<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

Route::post('/setup', [SetupController::class, 'store'])->middleware('throttle:10,1');

Route::post('/auth/prelogin', [AuthController::class, 'prelogin'])->middleware('throttle:20,1');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
Route::post('/auth/logout', [AuthController::class, 'logout']);

<?php

use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

Route::post('/setup', [SetupController::class, 'store'])->middleware('throttle:10,1');

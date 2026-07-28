<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\WebauthnController;
use Illuminate\Support\Facades\Route;

Route::post('/setup', [SetupController::class, 'store'])->middleware('throttle:10,1');

Route::post('/auth/prelogin', [AuthController::class, 'prelogin'])->middleware('throttle:20,1');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
Route::post('/auth/logout', [AuthController::class, 'logout']);

Route::post('/auth/webauthn/challenge', [WebauthnController::class, 'challenge']);
Route::post('/auth/webauthn/verify', [WebauthnController::class, 'verify'])->middleware('throttle:20,1');

Route::middleware('auth')->group(function () {
    Route::get('/vault/items', [ItemController::class, 'index']);
    Route::post('/vault/items', [ItemController::class, 'store']);
    Route::put('/vault/items/{id}', [ItemController::class, 'update']);
    Route::delete('/vault/items/{id}', [ItemController::class, 'destroy']);
    Route::post('/vault/items/{id}/restore', [ItemController::class, 'restore']);
    Route::get('/vault/export', [ItemController::class, 'export']);

    Route::get('/account/me', [AccountController::class, 'me']);
    Route::post('/account/master-password', [AccountController::class, 'rotateMasterPassword']);
    Route::put('/account/settings', [AccountController::class, 'settings']);

    Route::get('/account/passkeys', [WebauthnController::class, 'index']);
    Route::post('/account/passkeys/challenge', [WebauthnController::class, 'registrationChallenge']);
    Route::post('/account/passkeys', [WebauthnController::class, 'store']);
    Route::delete('/account/passkeys/{credential}', [WebauthnController::class, 'destroy']);
});

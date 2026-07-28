<?php

use Illuminate\Support\Facades\Route;

// One view for the whole SPA. Anything that is not the API or the health check
// is a client-side route, so the server just hands over the shell.
Route::get('/{any?}', fn () => view('app'))
    ->where('any', '^(?!api|up).*$')
    ->name('app');

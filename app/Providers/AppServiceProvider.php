<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // A stack trace on this application renders request payloads and config,
        // which is exactly the material the whole design exists to keep off the
        // server. Refusing to boot is safer than serving one page with it on.
        if ($this->app->environment('production') && config('app.debug')) {
            throw new RuntimeException(
                'APP_DEBUG is enabled in production. An error page here would leak sensitive material.'
            );
        }
    }
}

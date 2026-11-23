<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

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
        // WAMP's MySQL limits index keys to 767 bytes: 191 chars x 4 bytes (utf8mb4) fits.
        Schema::defaultStringLength(191);

        // API tokens of deactivated accounts stop working immediately.
        Sanctum::authenticateAccessTokensUsing(
            fn ($accessToken, bool $isValid) => $isValid && $accessToken->tokenable?->isActive(),
        );
    }
}

<?php

namespace App\Providers;

use App\Events\UserNotified;
use App\Models\User;
use App\Notifications\WorkspaceNotification;
use App\Support\Realtime;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
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

        // Every in-app notification also rings the recipient's bell live (Reverb), if it is up.
        Event::listen(NotificationSent::class, function (NotificationSent $event) {
            if ($event->channel === 'database'
                && $event->notification instanceof WorkspaceNotification
                && $event->notifiable instanceof User
                && $event->response instanceof DatabaseNotification) {
                Realtime::dispatch(new UserNotified($event->notifiable->id, $event->response, $event->notifiable));
            }
        });
    }
}

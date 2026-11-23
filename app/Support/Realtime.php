<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fire a broadcast event without ever breaking the request that caused it.
 *
 * Real-time updates are a bonus on top of the normal page flow: if the Reverb server
 * is not running (local dev, a restart, a hiccup) the message is still saved and the
 * pages still refresh themselves by polling — we only log the failure.
 */
class Realtime
{
    public static function dispatch(object $event): void
    {
        try {
            if (method_exists($event, 'dontBroadcastToCurrentUser')) {
                $event->dontBroadcastToCurrentUser(); // the sender's tab already shows the change
            }

            event($event);
        } catch (Throwable $e) {
            Log::warning('Realtime broadcast skipped: '.$e->getMessage(), ['event' => $event::class]);
        }
    }
}

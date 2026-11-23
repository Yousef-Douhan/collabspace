<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Posts a message to a Slack channel through an incoming webhook (SLACK_WEBHOOK_URL).
 * Does nothing when no webhook is configured; a Slack outage never breaks the request.
 */
class SlackNotifier
{
    public function enabled(): bool
    {
        return filled(config('services.slack.webhook_url'));
    }

    public function post(string $text, ?string $url = null): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        try {
            return Http::timeout(5)
                ->post(config('services.slack.webhook_url'), ['text' => $url ? "{$text}\n<{$url}|Open in CollabSpace>" : $text])
                ->successful();
        } catch (Throwable $e) {
            Log::warning('Slack webhook failed: '.$e->getMessage());

            return false;
        }
    }
}

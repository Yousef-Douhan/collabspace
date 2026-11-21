<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Http;

trait FakesAi
{
    /** Configure Gemini and make it reply with $text. No real HTTP request ever leaves the test. */
    protected function fakeAi(string $text): void
    {
        config(['services.ai.provider' => 'gemini', 'services.ai.key' => 'test-key', 'services.ai.model' => 'gemini-test']);

        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => $text]]]]],
            ]),
        ]);
    }
}

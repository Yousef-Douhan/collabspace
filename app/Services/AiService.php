<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin client for a real LLM provider, chosen in .env (AI_PROVIDER):
 *  - gemini    → Google Gemini API (default)
 *  - openai    → OpenAI or any OpenAI-compatible API (Groq, OpenRouter…) via AI_BASE_URL
 *  - anthropic → Anthropic Claude API
 *
 * Every failure is turned into a RuntimeException with a message safe to show users.
 */
class AiService
{
    public function isConfigured(): bool
    {
        return filled(config('services.ai.key'));
    }

    /** @param  list<array{role: 'user'|'assistant', content: string}>  $history */
    public function chat(string $system, string $user, array $history = [], int $maxTokens = 800, bool $json = false): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('AI is not configured. Add AI_API_KEY to the .env file.');
        }

        $provider = $this->provider();

        try {
            $text = match ($provider) {
                'gemini' => $this->gemini($system, $user, $history, $maxTokens, $json),
                'openai' => $this->openai($system, $user, $history, $maxTokens, $json),
                'anthropic' => $this->anthropic($system, $user, $history, $maxTokens),
                default => throw new RuntimeException("Unknown AI_PROVIDER \"{$provider}\"."),
            };
        } catch (ConnectionException $e) {
            Log::warning('AI connection failed', ['provider' => $provider, 'error' => $e->getMessage()]);
            throw new RuntimeException('Could not reach the AI service. Check the internet connection and try again.');
        }

        $text = trim((string) $text);

        if ($text === '') {
            throw new RuntimeException('The AI service returned an empty answer. Please try again.');
        }

        return $text;
    }

    /**
     * Ask for JSON and decode it (tolerates ```json fences or text around the object).
     *
     * Reasoning models spend part of the output budget on hidden "thinking", so a tight
     * budget can cut the JSON off mid-object. If the answer cannot be decoded, ask once
     * more with a larger budget before giving up.
     *
     * @return array<mixed>
     */
    public function chatJson(string $system, string $user, int $maxTokens = 2048): array
    {
        foreach ([$maxTokens, min($maxTokens * 2, 8192)] as $attempt => $budget) {
            $raw = $this->chat($system, $user, [], $budget, true);
            $decoded = $this->decodeJson($raw);

            if ($decoded !== null) {
                return $decoded;
            }

            Log::warning('AI returned unreadable JSON', [
                'attempt' => $attempt + 1,
                'max_tokens' => $budget,
                'length' => mb_strlen($raw),
                'tail' => mb_substr($raw, -120),
            ]);
        }

        throw new RuntimeException('The AI response could not be read. Please try again.');
    }

    /** @return array<mixed>|null */
    private function decodeJson(string $raw): ?array
    {
        $raw = (string) preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $raw);
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');

        if ($start === false || $end === false) {
            return null;
        }

        $decoded = json_decode(substr($raw, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : null;
    }

    /** @param  list<array{role: 'user'|'assistant', content: string}>  $history */
    private function gemini(string $system, string $user, array $history, int $maxTokens, bool $json): string
    {
        $base = rtrim(config('services.ai.base_url') ?: 'https://generativelanguage.googleapis.com/v1beta', '/');

        $contents = collect($history)->map(fn ($m) => [
            'role' => $m['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $m['content']]],
        ])->push(['role' => 'user', 'parts' => [['text' => $user]]])->values()->all();

        $config = ['maxOutputTokens' => $maxTokens, 'temperature' => 0.4];
        if ($json) {
            $config['responseMimeType'] = 'application/json';
        }
        if ($level = $this->thinkingLevel()) {
            $config['thinkingConfig'] = ['thinkingLevel' => $level];
        }

        $response = Http::timeout($this->timeout())
            ->withHeaders(['x-goog-api-key' => config('services.ai.key')])
            ->post("{$base}/models/{$this->model()}:generateContent", [
                'systemInstruction' => ['parts' => [['text' => $system]]],
                'contents' => $contents,
                'generationConfig' => $config,
            ]);

        $this->ensureOk($response);

        if ($response->json('candidates.0.finishReason') === 'MAX_TOKENS') {
            Log::info('Gemini answer hit maxOutputTokens', ['model' => $this->model(), 'max_tokens' => $maxTokens]);
        }

        /** @var list<array{text?: string}> $parts */
        $parts = $response->json('candidates.0.content.parts', []);

        return collect($parts)->pluck('text')->implode('');
    }

    /** @param  list<array{role: 'user'|'assistant', content: string}>  $history */
    private function openai(string $system, string $user, array $history, int $maxTokens, bool $json): string
    {
        $base = rtrim(config('services.ai.base_url') ?: 'https://api.openai.com/v1', '/');

        $payload = [
            'model' => $this->model(),
            'max_tokens' => $maxTokens,
            'temperature' => 0.4,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ...array_map(fn ($m) => ['role' => $m['role'], 'content' => $m['content']], $history),
                ['role' => 'user', 'content' => $user],
            ],
        ];
        if ($json) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = Http::timeout($this->timeout())->withToken(config('services.ai.key'))->post("{$base}/chat/completions", $payload);
        $this->ensureOk($response);

        return (string) $response->json('choices.0.message.content');
    }

    /** @param  list<array{role: 'user'|'assistant', content: string}>  $history */
    private function anthropic(string $system, string $user, array $history, int $maxTokens): string
    {
        $base = rtrim(config('services.ai.base_url') ?: 'https://api.anthropic.com/v1', '/');

        $response = Http::timeout($this->timeout())
            ->withHeaders(['x-api-key' => config('services.ai.key'), 'anthropic-version' => '2023-06-01'])
            ->post("{$base}/messages", [
                'model' => $this->model(),
                'max_tokens' => $maxTokens,
                'system' => $system,
                'messages' => [
                    ...array_map(fn ($m) => ['role' => $m['role'], 'content' => $m['content']], $history),
                    ['role' => 'user', 'content' => $user],
                ],
            ]);
        $this->ensureOk($response);

        /** @var list<array{type?: string, text?: string}> $blocks */
        $blocks = $response->json('content', []);

        return collect($blocks)->where('type', 'text')->pluck('text')->implode('');
    }

    private function ensureOk(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $status = $response->status();
        Log::warning('AI request failed', [
            'provider' => $this->provider(),
            'model' => $this->model(),
            'status' => $status,
            'detail' => $response->json('error.message'),
        ]);

        throw new RuntimeException(match (true) {
            in_array($status, [401, 403], true) => 'The AI API key was rejected. Check AI_API_KEY.',
            $status === 404 => "The AI model \"{$this->model()}\" was not found. Check AI_MODEL.",
            $status === 429 => 'AI usage limit reached. Wait a minute and try again.',
            $status >= 500 => 'The AI service is having problems right now. Try again shortly.',
            default => 'The AI request failed.',
        });
    }

    private function provider(): string
    {
        return strtolower((string) config('services.ai.provider', 'gemini'));
    }

    /**
     * Gemini 3.x reasons before it answers; the default level can take tens of seconds.
     * Older models reject the field, so it is only sent to gemini-3* models.
     */
    private function thinkingLevel(): ?string
    {
        $level = strtolower(trim((string) config('services.ai.thinking_level')));

        return in_array($level, ['minimal', 'low', 'medium', 'high'], true) && str_starts_with($this->model(), 'gemini-3')
            ? $level
            : null;
    }

    private function model(): string
    {
        return (string) (config('services.ai.model') ?: match ($this->provider()) {
            'openai' => 'gpt-4o-mini',
            'anthropic' => 'claude-haiku-5-5',
            default => 'gemini-3.5-flash',
        });
    }

    private function timeout(): int
    {
        return (int) config('services.ai.timeout', 30);
    }
}

<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Base for in-app notifications stored in the `notifications` table.
 *
 * Each subclass describes its type, an English title and a sentence (translation key +
 * parameters). Both are stored, so the bell can show them in the *reader's* language
 * (present()) while e-mails and the API keep a plain English `message`.
 */
abstract class WorkspaceNotification extends Notification
{
    abstract public function type(): string;

    /** English title; also its translation key. */
    abstract public function title(): string;

    /** @return array{0: string, 1: array<string, string>} [translation key, parameters] */
    abstract public function line(): array;

    /** @return array<string, mixed> ids the client can use to open the related item */
    public function context(): array
    {
        return [];
    }

    public function message(): string
    {
        [$key, $params] = $this->line();

        return __($key, $params, 'en');
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        [$key, $params] = $this->line();

        return [
            'type' => $this->type(),
            'title' => $this->title(),
            'message' => $this->message(),
            'line_key' => $key,
            'line_params' => $params,
            ...$this->context(),
        ];
    }

    /**
     * Title and sentence of a stored notification in the given (or current) language.
     *
     * @param  array<string, mixed>  $data
     * @return array{title: string, message: string}
     */
    public static function present(array $data, ?string $locale = null): array
    {
        return [
            'title' => __($data['title'] ?? 'Notification', [], $locale),
            'message' => isset($data['line_key'])
                ? __($data['line_key'], $data['line_params'] ?? [], $locale)
                : ($data['message'] ?? ''),
        ];
    }
}

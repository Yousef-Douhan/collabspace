<?php

namespace App\Notifications;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Str;

/** Someone wrote in a chat you are part of (one unread notice per chat, not one per message). */
class MessageReceived extends WorkspaceNotification
{
    public function __construct(public Conversation $conversation, public Message $message, public User $from) {}

    public function type(): string
    {
        return 'message_received';
    }

    public function title(): string
    {
        return 'New message';
    }

    public function line(): array
    {
        $text = $this->message->body !== null && $this->message->body !== ''
            ? Str::limit($this->message->body, 80)
            : '📎 '.($this->message->attachment_name ?? '');

        return $this->conversation->name
            ? [':name in #:chat: ":text"', ['name' => $this->from->name, 'chat' => $this->conversation->name, 'text' => $text]]
            : [':name: ":text"', ['name' => $this->from->name, 'text' => $text]];
    }

    public function context(): array
    {
        return ['conversation_id' => $this->conversation->id];
    }
}

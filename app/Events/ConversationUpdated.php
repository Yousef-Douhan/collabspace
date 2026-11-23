<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Something changed in a chat thread (new, edited or deleted message).
 *
 * The payload is deliberately tiny: the open page re-fetches its thread through the same
 * authorised endpoint it already polls, so message bodies never travel over the socket and
 * the rendering (read receipts, attachments, translations) lives in one Blade partial.
 */
class ConversationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public const SENT = 'sent';

    public const EDITED = 'edited';

    public const DELETED = 'deleted';

    public function __construct(
        public int $conversationId,
        public string $reason,
        public ?int $messageId = null,
        public ?int $senderId = null,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("conversation.{$this->conversationId}");
    }

    public function broadcastAs(): string
    {
        return 'conversation.updated';
    }

    /** @return array<string, int|string|null> */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'reason' => $this->reason,
            'message_id' => $this->messageId,
            'sender_id' => $this->senderId,
        ];
    }
}

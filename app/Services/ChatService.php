<?php

namespace App\Services;

use App\Enums\AttachmentType;
use App\Enums\ConversationType;
use App\Events\ConversationUpdated;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Support\Realtime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChatService
{
    public const DISK = 'local';

    /**
     * The user's conversations with: pivot state, participants, latest message
     * and an "unread_count" (messages from others after the user's last_read_at).
     */
    public function conversationsFor(User $user): BelongsToMany
    {
        return $user->conversations()
            ->with(['participants', 'latestMessage.sender'])
            ->withCount(['messages as unread_count' => fn (Builder $q) => $q
                ->where('sender_id', '!=', $user->id)
                ->whereRaw(
                    'messages.created_at > COALESCE((SELECT cu.last_read_at FROM conversation_user cu WHERE cu.conversation_id = messages.conversation_id AND cu.user_id = ?), ?)',
                    [$user->id, '1970-01-01 00:00:00'],
                )]);
    }

    /** Re-uses the existing direct chat between the two users if there is one. */
    public function startDirect(User $me, User $other): Conversation
    {
        $existing = Conversation::where('type', ConversationType::Direct)
            ->whereHas('participants', fn (Builder $q) => $q->whereKey($me->id))
            ->whereHas('participants', fn (Builder $q) => $q->whereKey($other->id))
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($me, $other) {
            $conversation = Conversation::create([
                'type' => ConversationType::Direct,
                'created_by' => $me->id,
            ]);
            $conversation->participants()->attach([$me->id, $other->id]);

            return $conversation;
        });
    }

    /** @param  list<int>  $memberIds */
    public function createChannel(User $creator, string $name, array $memberIds, ?int $projectId = null): Conversation
    {
        return DB::transaction(function () use ($creator, $name, $memberIds, $projectId) {
            $conversation = Conversation::create([
                'type' => ConversationType::Channel,
                'name' => Str::slug($name) ?: $name,
                'project_id' => $projectId,
                'created_by' => $creator->id,
            ]);
            $conversation->participants()->attach(collect($memberIds)->push($creator->id)->unique()->all());

            return $conversation;
        });
    }

    public function send(User $sender, Conversation $conversation, ?string $body, ?UploadedFile $attachment = null): Message
    {
        $data = ['sender_id' => $sender->id, 'body' => $body];

        if ($attachment) {
            $data['attachment_path'] = $attachment->store("chats/{$conversation->id}", self::DISK);
            $data['attachment_name'] = $attachment->getClientOriginalName();
            $data['attachment_type'] = $this->attachmentType($attachment);
        }

        $message = $conversation->messages()->create($data);

        $conversation->touch();                 // bubbles the chat to the top of the list
        $this->markRead($sender, $conversation); // your own message is never "unread"

        Realtime::dispatch(new ConversationUpdated($conversation->id, ConversationUpdated::SENT, $message->id, $sender->id));

        return $message;
    }

    public function edit(Message $message, string $body): Message
    {
        $message->update(['body' => $body, 'edited_at' => now()]);

        Realtime::dispatch(new ConversationUpdated($message->conversation_id, ConversationUpdated::EDITED, $message->id, $message->sender_id));

        return $message;
    }

    /** Delete a message together with its stored attachment. */
    public function remove(Message $message): void
    {
        if ($message->attachment_path) {
            Storage::disk(self::DISK)->delete($message->attachment_path);
        }
        $message->delete();

        Realtime::dispatch(new ConversationUpdated($message->conversation_id, ConversationUpdated::DELETED, $message->id, $message->sender_id));
    }

    public function markRead(User $user, Conversation $conversation): void
    {
        $conversation->participants()->updateExistingPivot($user->id, ['last_read_at' => now()]);
    }

    /** Pin / archive only for this user. */
    public function updateState(User $user, Conversation $conversation, array $state): void
    {
        $conversation->participants()->updateExistingPivot(
            $user->id,
            array_intersect_key($state, array_flip(['is_pinned', 'is_archived'])),
        );
    }

    private function attachmentType(UploadedFile $file): AttachmentType
    {
        $mime = (string) $file->getMimeType();

        return match (true) {
            str_starts_with($mime, 'image/') => AttachmentType::Image,
            str_starts_with($mime, 'audio/') => AttachmentType::Audio,
            default => AttachmentType::Document,
        };
    }
}

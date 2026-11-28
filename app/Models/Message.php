<?php

namespace App\Models;

use App\Enums\AttachmentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int $sender_id
 * @property string|null $body
 * @property string|null $attachment_path
 * @property string|null $attachment_name
 * @property AttachmentType|null $attachment_type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $edited_at
 * @property-read Conversation $conversation
 * @property-read User $sender
 * @property list<int> $read_by_ids not a column: set by the API for read receipts
 * @property Collection<int, User> $readers not a column: set by the chat page for read receipts
 */
class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'sender_id',
        'body',
        'attachment_path',
        'attachment_name',
        'attachment_type',
        'edited_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'attachment_type' => AttachmentType::class,
            'edited_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}

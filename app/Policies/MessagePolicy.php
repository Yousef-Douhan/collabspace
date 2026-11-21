<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

class MessagePolicy
{
    /** Only the sender can edit or delete their own message. */
    public function update(User $user, Message $message): bool
    {
        return $message->sender_id === $user->id;
    }

    public function delete(User $user, Message $message): bool
    {
        return $this->update($user, $message);
    }
}

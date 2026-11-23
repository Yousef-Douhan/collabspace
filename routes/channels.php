<?php

use App\Models\Conversation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
| Private channels — every subscription is authorised through the same policies as the
| HTTP endpoints, so a socket never shows what the page itself would refuse.
*/

Broadcast::channel('App.Models.User.{id}', function (User $user, $id) {
    return (string) $user->id === (string) $id;
});

// Chat thread: participants only (typing indicators are whispered on this channel too).
Broadcast::channel('conversation.{conversation}', function (User $user, Conversation $conversation) {
    return $user->can('view', $conversation);
});

// Kanban board of one project: members (and admins).
Broadcast::channel('project.{project}', function (User $user, Project $project) {
    return $user->can('view', $project);
});

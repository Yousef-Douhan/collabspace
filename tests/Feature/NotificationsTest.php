<?php

namespace Tests\Feature;

use App\Events\UserNotified;
use App\Models\Task;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsWorkspace;
use Tests\TestCase;

/** The bell: what creates a notification, in which language it reads, and the live push. */
class NotificationsTest extends TestCase
{
    use BuildsWorkspace, RefreshDatabase;

    public function test_a_chat_message_notifies_the_others_once_until_they_read_it(): void
    {
        [$sara, $omar] = User::factory()->count(2)->create();
        $chat = app(ChatService::class);
        $dm = $chat->startDirect($sara, $omar);

        $chat->send($sara, $dm, 'Hi Omar');
        $chat->send($sara, $dm, 'Are you there?');           // still unread: no second notice

        $this->assertSame(0, $sara->notifications()->count());
        $this->assertSame(1, $omar->unreadNotifications()->count());
        $this->assertSame('message_received', $omar->notifications()->first()->data['type']);

        $omar->unreadNotifications->markAsRead();
        $chat->send($sara, $dm, 'Ping');
        $this->assertSame(1, $omar->fresh()->unreadNotifications()->count());
    }

    public function test_opening_a_message_notification_goes_to_the_chat(): void
    {
        [$sara, $omar] = User::factory()->count(2)->create();
        $dm = app(ChatService::class)->startDirect($sara, $omar);
        app(ChatService::class)->send($sara, $dm, 'Hi');
        $id = $omar->notifications()->first()->id;

        $this->actingAs($omar)->get(route('notifications.open', $id))->assertRedirect(route('chats.show', $dm));
        $this->assertSame(0, $omar->unreadNotifications()->count());
    }

    public function test_a_comment_notifies_the_assignees_and_creator_but_not_its_author(): void
    {
        $owner = User::factory()->create();
        $project = $this->projectWith($owner);
        $dev = $this->member($project);
        $reviewer = $this->member($project);
        $task = Task::factory()->for($project)->create(['created_by' => $owner->id]);
        $task->assignees()->attach([$dev->id, $reviewer->id]);

        $this->actingAs($dev)->postJson("/api/v1/tasks/{$task->id}/comments", ['body' => 'Done, please review'])->assertCreated();

        $this->assertSame(0, $dev->notifications()->count());
        foreach ([$owner, $reviewer] as $user) {
            $this->assertSame(1, $user->notifications()->count());
            $this->assertSame('comment_added', $user->notifications()->first()->data['type']);
            $this->assertSame($task->id, $user->notifications()->first()->data['task_id']);
        }
    }

    public function test_the_bell_reads_in_the_readers_language(): void
    {
        [$sara, $omar] = User::factory()->count(2)->create();
        $omar->update(['locale' => 'ar']);
        $dm = app(ChatService::class)->startDirect($sara, $omar);
        app(ChatService::class)->send($sara, $dm, 'مرحبا');

        $this->actingAs($omar->fresh())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('رسالة جديدة')
            ->assertSee($sara->name.': "مرحبا"');

        // The API keeps the English sentence and adds the request's language.
        $this->withHeader('Accept-Language', 'en')->actingAs($omar, 'sanctum')
            ->getJson('/api/v1/notifications')
            ->assertJsonPath('data.0.title', 'New message')
            ->assertJsonPath('data.0.display.title', 'New message');
    }

    public function test_every_new_notification_is_pushed_to_the_recipients_private_channel(): void
    {
        Event::fake([UserNotified::class]);
        [$sara, $omar] = User::factory()->count(2)->create();
        $dm = app(ChatService::class)->startDirect($sara, $omar);

        app(ChatService::class)->send($sara, $dm, 'Live?');

        Event::assertDispatched(UserNotified::class, fn (UserNotified $e) => $e->userId === $omar->id
            && $e->broadcastOn()->name === "private-App.Models.User.{$omar->id}"
            && $e->payload['title'] === 'New message'
            && $e->payload['unread'] === 1
            && str_contains($e->payload['url'], '/notifications/'));
    }

    public function test_the_demo_admin_starts_with_unread_notifications(): void
    {
        Storage::fake('local');
        $this->seed();

        $admin = User::where('email', 'admin@collabspace.test')->firstOrFail();
        $this->assertGreaterThanOrEqual(3, $admin->unreadNotifications()->count());
        $this->assertSame(1, $admin->readNotifications()->count());
    }
}

<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Events\ConversationUpdated;
use App\Events\TaskBoardUpdated;
use App\Models\Message;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/** Laravel Reverb: which events fire, who may subscribe, and that a dead socket server never breaks a request. */
class RealtimeTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    private User $friend;

    protected function setUp(): void
    {
        parent::setUp();

        $this->me = User::factory()->create(['name' => 'Me']);
        $this->friend = User::factory()->create(['name' => 'Friend']);
    }

    /** Use a real (Reverb/Pusher-protocol) broadcaster; no network is needed to sign channel auth. */
    private function useReverb(string $host = '127.0.0.1', int $port = 8080): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => '1',
            'broadcasting.connections.reverb.options.host' => $host,
            'broadcasting.connections.reverb.options.port' => $port,
            'broadcasting.connections.reverb.options.scheme' => 'http',
            'broadcasting.connections.reverb.options.useTLS' => false,
        ]);
        Broadcast::purge();
        require base_path('routes/channels.php'); // register the channels on the new driver
    }

    private function authChannel(User $user, string $channel)
    {
        return $this->actingAs($user)->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-{$channel}"]);
    }

    public function test_sending_editing_and_deleting_a_message_broadcast_to_the_thread(): void
    {
        Event::fake([ConversationUpdated::class]);
        $conversation = app(ChatService::class)->startDirect($this->me, $this->friend);

        $this->actingAs($this->me)->post(route('chats.send', $conversation), ['body' => 'Hello'])->assertRedirect();
        $message = Message::firstOrFail();
        $this->patch(route('messages.update', $message), ['body' => 'Hello!'])->assertRedirect();
        $this->delete(route('messages.destroy', $message))->assertRedirect();

        foreach ([ConversationUpdated::SENT, ConversationUpdated::EDITED, ConversationUpdated::DELETED] as $reason) {
            Event::assertDispatched(ConversationUpdated::class, fn (ConversationUpdated $e) => $e->reason === $reason
                && $e->conversationId === $conversation->id
                && $e->broadcastOn()->name === "private-conversation.{$conversation->id}");
        }
    }

    public function test_the_socket_payload_carries_no_message_text(): void
    {
        $payload = (new ConversationUpdated(5, ConversationUpdated::SENT, 9, 1))->broadcastWith();

        $this->assertSame(['conversation_id', 'reason', 'message_id', 'sender_id'], array_keys($payload));
    }

    public function test_moving_a_task_broadcasts_its_new_place_on_the_project_board(): void
    {
        Event::fake([TaskBoardUpdated::class]);
        $project = Project::factory()->create(['owner_id' => $this->me->id]);
        $task = Task::factory()->for($project)->create(['status' => TaskStatus::Todo, 'position' => 0, 'created_by' => $this->me->id]);

        $this->actingAs($this->me)
            ->patchJson(route('tasks.move', $task), ['status' => 'in_progress', 'position' => 0])
            ->assertOk();

        Event::assertDispatched(TaskBoardUpdated::class, fn (TaskBoardUpdated $e) => $e->action === TaskBoardUpdated::MOVED
            && $e->taskId === $task->id
            && $e->status === 'in_progress'
            && $e->actorName === 'Me'
            && $e->broadcastOn()->name === "private-project.{$project->id}");
    }

    public function test_a_status_change_through_edit_is_announced_once(): void
    {
        Event::fake([TaskBoardUpdated::class]);
        $project = Project::factory()->create(['owner_id' => $this->me->id]);
        $task = Task::factory()->for($project)->create(['status' => TaskStatus::Todo, 'position' => 0, 'created_by' => $this->me->id]);

        $this->actingAs($this->me)->put(route('tasks.update', $task), ['status' => 'completed'])->assertRedirect();

        Event::assertDispatchedTimes(TaskBoardUpdated::class, 1);
        Event::assertDispatched(TaskBoardUpdated::class, fn (TaskBoardUpdated $e) => $e->action === TaskBoardUpdated::UPDATED);
    }

    public function test_only_participants_can_subscribe_to_a_conversation(): void
    {
        $this->useReverb();
        $conversation = app(ChatService::class)->startDirect($this->me, $this->friend);
        $stranger = User::factory()->create();

        $this->authChannel($this->friend, "conversation.{$conversation->id}")->assertOk()->assertJsonStructure(['auth']);
        $this->authChannel($stranger, "conversation.{$conversation->id}")->assertForbidden();
    }

    public function test_only_project_members_can_subscribe_to_its_board(): void
    {
        $this->useReverb();
        $project = Project::factory()->create(['owner_id' => $this->me->id]);

        $this->authChannel($this->me, "project.{$project->id}")->assertOk();
        $this->authChannel($this->friend, "project.{$project->id}")->assertForbidden();
    }

    public function test_chat_and_kanban_keep_working_when_the_socket_server_is_down(): void
    {
        $this->useReverb(port: 1); // nothing listens there: every broadcast fails
        $conversation = app(ChatService::class)->startDirect($this->me, $this->friend);
        $project = Project::factory()->create(['owner_id' => $this->me->id]);
        $task = Task::factory()->for($project)->create(['status' => TaskStatus::Todo, 'position' => 0, 'created_by' => $this->me->id]);

        $this->actingAs($this->me)->post(route('chats.send', $conversation), ['body' => 'Still here'])->assertRedirect();
        $this->patchJson(route('tasks.move', $task), ['status' => 'completed', 'position' => 0])->assertOk();

        $this->assertDatabaseHas('messages', ['body' => 'Still here']);
        $this->assertSame(TaskStatus::Completed, $task->fresh()->status);
    }
}

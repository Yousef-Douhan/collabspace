<?php

namespace Tests\Feature\Api;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatTest extends TestCase
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

    private function direct(): array
    {
        Sanctum::actingAs($this->me);

        return $this->postJson('/api/v1/conversations', ['type' => 'direct', 'user_id' => $this->friend->id])->json('data');
    }

    public function test_starting_a_direct_chat_twice_reuses_it(): void
    {
        Sanctum::actingAs($this->me);

        $first = $this->postJson('/api/v1/conversations', ['type' => 'direct', 'user_id' => $this->friend->id])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Friend')
            ->json('data.id');

        $this->postJson('/api/v1/conversations', ['type' => 'direct', 'user_id' => $this->friend->id])
            ->assertOk()
            ->assertJsonPath('data.id', $first);

        $this->assertSame(1, Conversation::count());
    }

    public function test_cannot_start_a_chat_with_yourself(): void
    {
        Sanctum::actingAs($this->me);

        $this->postJson('/api/v1/conversations', ['type' => 'direct', 'user_id' => $this->me->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_channel_includes_creator_and_members(): void
    {
        Sanctum::actingAs($this->me);

        $this->postJson('/api/v1/conversations', ['type' => 'channel', 'name' => 'Alpha Dev', 'members' => [$this->friend->id]])
            ->assertCreated()
            ->assertJsonPath('data.name', 'alpha-dev')
            ->assertJsonCount(2, 'data.participants');
    }

    public function test_messages_unread_counts_and_read_receipts(): void
    {
        $chat = $this->direct();

        $this->postJson("/api/v1/conversations/{$chat['id']}/messages", ['body' => 'Hi!'])
            ->assertCreated()
            ->assertJsonPath('data.is_mine', true);
        $this->postJson("/api/v1/conversations/{$chat['id']}/messages", ['body' => 'Are you there?'])->assertCreated();

        // The sender has nothing unread; the friend has 2.
        $this->getJson('/api/v1/conversations')->assertJsonPath('data.0.unread_count', 0);

        Sanctum::actingAs($this->friend);
        $this->getJson('/api/v1/conversations')
            ->assertJsonPath('data.0.unread_count', 2)
            ->assertJsonPath('data.0.latest_message.body', 'Are you there?');

        // Reading the messages clears the badge. Newest first.
        $this->travel(1)->seconds();
        $this->getJson("/api/v1/conversations/{$chat['id']}/messages")
            ->assertOk()
            ->assertJsonPath('data.0.body', 'Are you there?')
            ->assertJsonPath('data.0.is_mine', false);
        $this->getJson('/api/v1/conversations')->assertJsonPath('data.0.unread_count', 0);
    }

    public function test_empty_message_is_rejected(): void
    {
        $chat = $this->direct();

        $this->postJson("/api/v1/conversations/{$chat['id']}/messages", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
    }

    public function test_pin_and_archive_are_per_user(): void
    {
        $chat = $this->direct();

        $this->patchJson("/api/v1/conversations/{$chat['id']}", ['is_pinned' => true])
            ->assertOk()
            ->assertJsonPath('data.is_pinned', true);

        Sanctum::actingAs($this->friend);
        $this->getJson('/api/v1/conversations')->assertJsonPath('data.0.is_pinned', false);

        $this->patchJson("/api/v1/conversations/{$chat['id']}", ['is_archived' => true])->assertOk();
        $this->getJson('/api/v1/conversations')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/conversations?archived=1')->assertJsonCount(1, 'data');

        Sanctum::actingAs($this->me);
        $this->getJson('/api/v1/conversations')->assertJsonCount(1, 'data');
    }

    public function test_outsiders_cannot_read_or_post(): void
    {
        $chat = $this->direct();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/conversations/{$chat['id']}/messages")->assertForbidden();
        $this->postJson("/api/v1/conversations/{$chat['id']}/messages", ['body' => 'hi'])->assertForbidden();
    }

    public function test_attachments_are_private_and_typed(): void
    {
        Storage::fake('local');
        $chat = $this->direct();

        $message = $this->post("/api/v1/conversations/{$chat['id']}/messages", [
            'attachment' => UploadedFile::fake()->image('screen.png'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.attachment.type', 'image')
            ->assertJsonPath('data.attachment.name', 'screen.png')
            ->json('data');

        $this->get($message['attachment']['url'])->assertOk();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson($message['attachment']['url'])->assertForbidden();
    }
}

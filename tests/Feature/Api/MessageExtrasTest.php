<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessageExtrasTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    private User $friend;

    private int $chatId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->me = User::factory()->create();
        $this->friend = User::factory()->create();
        Sanctum::actingAs($this->me);
        $this->chatId = $this->postJson('/api/v1/conversations', ['type' => 'direct', 'user_id' => $this->friend->id])->json('data.id');
    }

    public function test_sender_can_edit_and_delete_their_message_only(): void
    {
        $id = $this->postJson("/api/v1/conversations/{$this->chatId}/messages", ['body' => 'Helo'])->json('data.id');

        $this->patchJson("/api/v1/messages/{$id}", ['body' => 'Hello'])
            ->assertOk()
            ->assertJsonPath('data.body', 'Hello')
            ->assertJsonPath('data.is_edited', true);

        Sanctum::actingAs($this->friend);
        $this->patchJson("/api/v1/messages/{$id}", ['body' => 'Changed'])->assertForbidden();
        $this->deleteJson("/api/v1/messages/{$id}")->assertForbidden();

        Sanctum::actingAs($this->me);
        $this->deleteJson("/api/v1/messages/{$id}")->assertNoContent();
        $this->getJson("/api/v1/conversations/{$this->chatId}/messages")->assertJsonCount(0, 'data');
    }

    public function test_read_receipts_show_who_has_seen_a_message(): void
    {
        $this->postJson("/api/v1/conversations/{$this->chatId}/messages", ['body' => 'Seen?'])->assertCreated();

        $this->getJson("/api/v1/conversations/{$this->chatId}/messages")->assertJsonPath('data.0.read_by', []);

        $this->travel(1)->seconds();
        Sanctum::actingAs($this->friend);
        $this->getJson("/api/v1/conversations/{$this->chatId}/messages")->assertOk(); // friend opens the chat

        Sanctum::actingAs($this->me);
        $this->getJson("/api/v1/conversations/{$this->chatId}/messages")
            ->assertJsonPath('data.0.read_by', [$this->friend->id]);
    }
}

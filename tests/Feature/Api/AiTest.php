<?php

namespace Tests\Feature\Api;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsWorkspace;
use Tests\Concerns\FakesAi;
use Tests\TestCase;

class AiTest extends TestCase
{
    use BuildsWorkspace, FakesAi, RefreshDatabase;

    public function test_ask_answers_from_visible_data_only_and_keeps_history(): void
    {
        $me = User::factory()->create();
        $mine = $this->projectWith($me);
        $mine->update(['name' => 'Alpha']);
        Task::factory()->create(['project_id' => $mine->id, 'title' => 'Visible task']);

        $secret = $this->projectWith(User::factory()->create());
        $secret->update(['name' => 'Secret Project']);
        Task::factory()->create(['project_id' => $secret->id, 'title' => 'Hidden task']);

        $this->fakeAi('You have **1** open task: Visible task.');
        Sanctum::actingAs($me);

        $this->postJson('/api/v1/ai/ask', ['message' => 'ما المهام المفتوحة؟'])
            ->assertCreated()
            ->assertJsonPath('data.role', 'assistant')
            ->assertJsonPath('data.content', 'You have **1** open task: Visible task.');

        Http::assertSent(function ($request) {
            $body = json_encode($request->data(), JSON_UNESCAPED_UNICODE);

            return str_contains($body, 'Visible task')
                && str_contains($body, 'ما المهام المفتوحة؟')
                && ! str_contains($body, 'Hidden task')
                && ! str_contains($body, 'Secret Project');
        });

        $this->getJson('/api/v1/ai/messages')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.role', 'user')
            ->assertJsonPath('data.1.role', 'assistant');

        $this->deleteJson('/api/v1/ai/messages')->assertNoContent();
        $this->getJson('/api/v1/ai/messages')->assertJsonCount(0, 'data');
    }

    public function test_previous_turns_are_sent_as_context(): void
    {
        $this->fakeAi('Sure.');
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/ai/ask', ['message' => 'First question'])->assertCreated();
        $this->postJson('/api/v1/ai/ask', ['message' => 'Follow-up'])->assertCreated();

        $last = Http::recorded()->last()[0];
        $roles = collect($last->data()['contents'])->pluck('role')->all();
        $this->assertSame(['user', 'model', 'user'], $roles);
    }

    public function test_failures_return_503_and_store_nothing(): void
    {
        config(['services.ai.provider' => 'gemini', 'services.ai.key' => 'bad']);
        Http::fake(['*' => Http::response(['error' => ['message' => 'API key not valid']], 401)]);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/ai/ask', ['message' => 'Hello'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'The AI API key was rejected. Check AI_API_KEY.');

        $this->assertSame(0, $user->aiMessages()->count());
    }
}

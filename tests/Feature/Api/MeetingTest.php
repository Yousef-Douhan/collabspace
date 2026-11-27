<?php

namespace Tests\Feature\Api;

use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingInvitation;
use App\Notifications\MeetingReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsWorkspace;
use Tests\Concerns\FakesAi;
use Tests\TestCase;

class MeetingTest extends TestCase
{
    use BuildsWorkspace, FakesAi, RefreshDatabase;

    private User $host;

    private User $guest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->host = User::factory()->create();
        $this->guest = User::factory()->create();
    }

    private function schedule(array $overrides = []): array
    {
        Sanctum::actingAs($this->host);

        return $this->postJson('/api/v1/meetings', [
            'title' => 'Sprint review',
            'location' => 'Meeting room',
            'starts_at' => now()->addHours(3)->toDateTimeString(),
            'ends_at' => now()->addHours(4)->toDateTimeString(),
            'reminder_minutes' => 60,
            'attendees' => [$this->guest->id],
            ...$overrides,
        ])->assertCreated()->json('data');
    }

    public function test_scheduling_invites_attendees(): void
    {
        Notification::fake();

        $meeting = $this->schedule();

        $this->assertSame('accepted', $meeting['my_response']);
        $this->assertSame(2, $meeting['attendees_count']);
        Notification::assertSentTo($this->guest, MeetingInvitation::class);
        Notification::assertNotSentTo($this->host, MeetingInvitation::class);
    }

    public function test_end_must_be_after_start(): void
    {
        Sanctum::actingAs($this->host);

        $this->postJson('/api/v1/meetings', [
            'title' => 'Bad',
            'starts_at' => now()->addHour()->toDateTimeString(),
            'ends_at' => now()->toDateTimeString(),
        ])->assertUnprocessable()->assertJsonValidationErrors(['ends_at']);
    }

    public function test_project_meetings_require_membership(): void
    {
        $project = $this->projectWith(User::factory()->create());
        Sanctum::actingAs($this->host);

        $this->postJson('/api/v1/meetings', [
            'project_id' => $project->id,
            'title' => 'Not my project',
            'starts_at' => now()->addHour()->toDateTimeString(),
            'ends_at' => now()->addHours(2)->toDateTimeString(),
        ])->assertForbidden();
    }

    public function test_visibility_ranges_and_rsvp(): void
    {
        $meeting = $this->schedule();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/meetings/{$meeting['id']}")->assertForbidden();
        $this->getJson('/api/v1/meetings')->assertJsonCount(0, 'data');

        Sanctum::actingAs($this->guest);
        $this->getJson('/api/v1/meetings?range=upcoming')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/meetings?range=past')->assertJsonCount(0, 'data');

        $this->postJson("/api/v1/meetings/{$meeting['id']}/respond", ['response' => 'declined'])
            ->assertOk()
            ->assertJsonPath('data.my_response', 'declined');

        $this->patchJson("/api/v1/meetings/{$meeting['id']}", ['title' => 'Hijacked'])->assertForbidden();
    }

    public function test_reminders_are_sent_once_when_the_window_opens(): void
    {
        Notification::fake();
        $this->schedule(); // starts in 3h, reminder 60 min before

        $this->artisan('meetings:send-reminders')->assertSuccessful();
        Notification::assertNotSentTo($this->guest, MeetingReminder::class);

        $this->travel(125)->minutes(); // reminder window (T-60 min) is now open
        $this->artisan('meetings:send-reminders')->assertSuccessful();
        $this->artisan('meetings:send-reminders')->assertSuccessful();

        Notification::assertSentToTimes($this->guest, MeetingReminder::class, 1);
        Notification::assertSentToTimes($this->host, MeetingReminder::class, 1);
    }

    public function test_ai_summary_is_generated_and_saved(): void
    {
        $meeting = $this->schedule(['transcript' => "[00:01] Host: Kanban is done.\n[00:02] Guest: I'll write the docs by Friday."]);
        $this->fakeAi(json_encode([
            'summary' => 'The team confirmed the Kanban board is finished.',
            'key_points' => ['Kanban done', 'Guest writes docs by Friday'],
        ]));

        Sanctum::actingAs($this->guest);
        $this->postJson("/api/v1/meetings/{$meeting['id']}/summary")
            ->assertOk()
            ->assertJsonPath('data.ai_summary', 'The team confirmed the Kanban board is finished.')
            ->assertJsonPath('data.key_points.1', 'Guest writes docs by Friday');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'gemini-test:generateContent')
            && str_contains(json_encode($request->data()), 'Kanban is done'));
        $this->assertSame(['Kanban done', 'Guest writes docs by Friday'], Meeting::find($meeting['id'])->key_points);
    }

    public function test_ai_errors_are_reported_cleanly(): void
    {
        $meeting = $this->schedule(['transcript' => 'Something']);
        config(['services.ai.key' => null]);

        $this->postJson("/api/v1/meetings/{$meeting['id']}/summary")
            ->assertStatus(503)
            ->assertJsonPath('message', 'AI is not configured. Add AI_API_KEY to the .env file.');
    }

    public function test_a_cut_off_ai_answer_is_retried_with_a_larger_budget(): void
    {
        $meeting = $this->schedule(['transcript' => '[00:01] Host: Kanban is done.']);
        config(['services.ai.provider' => 'gemini', 'services.ai.key' => 'test-key', 'services.ai.model' => 'gemini-test']);
        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push(['candidates' => [['finishReason' => 'MAX_TOKENS', 'content' => ['parts' => [['text' => '{"summary": "The team confirmed']]]]]])
                ->push(['candidates' => [['content' => ['parts' => [['text' => '{"summary": "Kanban is done.", "key_points": ["Kanban done"]}']]]]]]),
        ]);

        Sanctum::actingAs($this->guest);
        $this->postJson("/api/v1/meetings/{$meeting['id']}/summary")
            ->assertOk()
            ->assertJsonPath('data.ai_summary', 'Kanban is done.');

        Http::assertSentCount(2);
        $budgets = collect(Http::recorded())->map(fn ($pair) => $pair[0]->data()['generationConfig']['maxOutputTokens'])->all();
        $this->assertSame([2048, 4096], $budgets);
    }
}

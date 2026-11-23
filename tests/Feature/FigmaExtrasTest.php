<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\Task;
use App\Models\User;
use App\Notifications\MeetingInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\FakesAi;
use Tests\TestCase;

/** Features added after the final review against the Figma file. */
class FigmaExtrasTest extends TestCase
{
    use FakesAi, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        $this->seed();
        $this->admin = User::where('email', 'admin@collabspace.test')->firstOrFail();
    }

    public function test_dashboard_duration_and_team_filters(): void
    {
        $this->actingAs($this->admin)
            ->get(route('dashboard', ['period' => 'week', 'track' => 'front_end']))
            ->assertOk()
            ->assertSee(__('This Week'))
            ->assertSee('Omar Khaled');

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/dashboard?period=year')
            ->assertOk()
            ->assertJsonCount(now()->month, 'data.charts.project_overview');

        $team = $this->getJson('/api/v1/dashboard?period=all&track=ui_ux')->assertOk()->json('data.team_status');
        $this->assertSame(['ui_ux'], array_values(array_unique(array_column($team, 'track'))));
        $this->assertTrue(collect($team)->firstWhere('name', 'Lina Haddad')['is_leader']);

        $this->getJson('/api/v1/dashboard?track=nope')->assertUnprocessable();
    }

    public function test_dashboard_cards_show_figma_details(): void
    {
        Sanctum::actingAs($this->admin);

        $data = $this->getJson('/api/v1/dashboard?period=all')->assertOk()->json('data');

        $this->assertArrayHasKey('tasks', $data['active_projects'][0]);
        $this->assertArrayHasKey('uploaded_by', $data['recent_files'][0]);
        $this->assertArrayHasKey('attendees', $data['upcoming_meetings'][0]);
    }

    public function test_kanban_overdue_filter(): void
    {
        $overdue = Task::where('title', 'Write API documentation')->firstOrFail();
        $onTime = Task::where('title', 'Chat UI with attachments')->firstOrFail();

        $this->actingAs($this->admin)
            ->get(route('tasks.index', ['due' => 'overdue']))
            ->assertOk()
            ->assertSee($overdue->title)
            ->assertDontSee($onTime->title);
    }

    public function test_new_meeting_with_duration_attachments_and_email(): void
    {
        Notification::fake();
        $guest = User::where('email', 'sara@collabspace.test')->firstOrFail();
        $start = now()->addDays(2)->setTime(10, 0);

        $this->actingAs($this->admin)->post(route('meetings.store'), [
            'title' => 'Figma review',
            'starts_at' => $start->format('Y-m-d\TH:i'),
            'ends_at' => $start->copy()->addMinutes(45)->format('Y-m-d\TH:i'),
            'attendees' => [$guest->id],
            'reminder_minutes' => 60,
            'notify_channels' => ['email'],
            'attachments' => [UploadedFile::fake()->create('agenda.pdf', 120, 'application/pdf')],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $meeting = Meeting::where('title', 'Figma review')->firstOrFail();
        $this->assertSame(45, (int) $meeting->starts_at->diffInMinutes($meeting->ends_at));
        $this->assertSame(['email'], $meeting->notify_channels);
        $this->assertSame('agenda.pdf', $meeting->attachments()->first()->original_name);

        Notification::assertSentTo($guest, MeetingInvitation::class, fn ($n, array $channels) => in_array('mail', $channels, true));
    }

    public function test_slack_channel_posts_to_the_webhook(): void
    {
        config(['services.slack.webhook_url' => 'https://hooks.slack.test/T000/B000/XXX']);
        Http::fake(['hooks.slack.test/*' => Http::response('ok')]);

        $start = now()->addDay()->setTime(9, 0);
        $this->actingAs($this->admin)->post(route('meetings.store'), [
            'title' => 'Slack sync',
            'starts_at' => $start->format('Y-m-d\TH:i'),
            'ends_at' => $start->copy()->addHour()->format('Y-m-d\TH:i'),
            'notify_channels' => ['slack'],
        ])->assertSessionHasNoErrors();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'hooks.slack.test') && str_contains($request['text'], 'Slack sync'));
    }

    public function test_reports_page_has_print_weekly_and_ai_draft(): void
    {
        $this->actingAs($this->admin)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee(__('Print / Save PDF'))
            ->assertSee(__('Weekly'));

        $this->fakeAi('Alpha is 29% done; one task is overdue.');

        $this->actingAs($this->admin)
            ->postJson(route('reports.draft'), ['type' => 'project_progress'])
            ->assertOk()
            ->assertJsonPath('data.notes', 'Alpha is 29% done; one task is overdue.');
    }

    public function test_french_interface_and_profile_join_date(): void
    {
        $this->actingAs($this->admin)->post(route('locale.switch'), ['locale' => 'fr'])->assertRedirect();

        $this->actingAs($this->admin->fresh())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('lang="fr"', false)
            ->assertSee('dir="ltr"', false)
            ->assertSee('Tableau de bord');

        $this->actingAs($this->admin->fresh())
            ->get(route('members.show', $this->admin))
            ->assertOk()
            ->assertSee('Membre depuis');
    }
}

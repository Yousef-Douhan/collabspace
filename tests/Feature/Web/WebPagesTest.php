<?php

namespace Tests\Feature\Web;

use App\Enums\TaskStatus;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Smoke tests for the Blade UI, on top of the demo seeder. */
class WebPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        $this->seed();
        $this->admin = User::where('email', 'admin@collabspace.test')->firstOrFail();
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk()->assertSee('Sign in', false);
    }

    public function test_login_and_logout(): void
    {
        $this->post(route('login'), ['email' => 'admin@collabspace.test', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->admin);

        $this->post(route('logout'))->assertRedirect();
        $this->assertGuest();
    }

    public function test_every_page_renders(): void
    {
        $project = Project::firstOrFail();
        $task = Task::firstOrFail();
        $meeting = Meeting::firstOrFail();
        $conversation = $this->admin->conversations()->firstOrFail();

        $pages = [
            route('dashboard'),
            route('projects.index'),
            route('projects.show', $project),
            route('projects.show', [$project, 'tab' => 'tasks']),
            route('projects.show', [$project, 'tab' => 'teams']),
            route('projects.show', [$project, 'tab' => 'files']),
            route('tasks.index'),
            route('tasks.index', ['mine' => 1, 'project_id' => $project->id]),
            route('tasks.show', $task),
            route('files.index'),
            route('chats.index'),
            route('chats.index', ['tab' => 'projects']),
            route('chats.show', $conversation),
            route('meetings.index'),
            route('meetings.index', ['week' => $meeting->starts_at->toDateString()]),
            route('meetings.show', $meeting),
            route('meetings.show', [$meeting, 'tab' => 'notes']),
            route('meetings.show', [$meeting, 'tab' => 'comments']),
            route('reports.index'),
            route('ai.index'),
            route('notifications.index'),
            route('members.show', $this->admin),
            route('members.show', [$this->admin, 'tab' => 'tasks']),
            route('members.show', [$this->admin, 'tab' => 'files']),
            route('settings.edit'),
            route('search', ['q' => 'Alpha']),
        ];

        foreach ($pages as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    public function test_kanban_move_returns_json(): void
    {
        $task = Task::where('status', TaskStatus::Todo)->firstOrFail();

        $this->actingAs($this->admin)
            ->patchJson(route('tasks.move', $task), ['status' => 'in_review', 'position' => 0])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_review');

        $this->assertSame(TaskStatus::InReview, $task->fresh()->status);
    }

    public function test_create_project_and_task_from_forms(): void
    {
        $this->actingAs($this->admin)
            ->post(route('projects.store'), ['name' => 'Web Project', 'priority' => 'high', 'type' => 'website'])
            ->assertRedirect();

        $project = Project::where('name', 'Web Project')->firstOrFail();

        $this->post(route('tasks.store'), [
            'project_id' => $project->id,
            'title' => 'From the board',
            'priority' => 'low',
            'track' => 'front_end',
            'status' => 'in_progress',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tasks', ['title' => 'From the board', 'status' => 'in_progress']);
    }

    public function test_comment_and_chat_message(): void
    {
        $task = Task::firstOrFail();
        $conversation = $this->admin->conversations()->firstOrFail();

        $this->actingAs($this->admin)->post(route('tasks.comments.store', $task), ['body' => 'Looks good'])->assertRedirect();
        $this->assertDatabaseHas('comments', ['body' => 'Looks good']);

        $this->post(route('chats.send', $conversation), ['body' => 'Hello from Blade'])
            ->assertRedirect(route('chats.show', $conversation));
        $this->assertDatabaseHas('messages', ['body' => 'Hello from Blade', 'sender_id' => $this->admin->id]);
    }

    public function test_chat_poll_returns_new_messages_and_marks_read(): void
    {
        $conversation = $this->admin->conversations()->with('participants')->firstOrFail();
        $other = $conversation->participants->firstWhere('id', '!=', $this->admin->id);

        $conversation->messages()->create(['sender_id' => $other->id, 'body' => 'Polled hello']);

        $this->actingAs($this->admin)
            ->getJson(route('chats.poll', $conversation))
            ->assertOk()
            ->assertJsonStructure(['html', 'last_id'])
            ->assertSee('Polled hello');

        $this->assertNotNull($conversation->participants()->whereKey($this->admin->id)->first()->pivot->last_read_at);
    }

    public function test_outsiders_cannot_poll_a_conversation(): void
    {
        $conversation = $this->admin->conversations()->firstOrFail();

        $this->actingAs(User::factory()->create())
            ->getJson(route('chats.poll', $conversation))
            ->assertForbidden();
    }

    public function test_outsiders_cannot_open_a_private_project(): void
    {
        $outsider = User::factory()->create();
        $project = Project::firstOrFail();

        $this->actingAs($outsider)->get(route('projects.show', $project))->assertForbidden();
    }
}

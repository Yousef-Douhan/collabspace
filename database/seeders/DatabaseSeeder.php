<?php

namespace Database\Seeders;

use App\Enums\Availability;
use App\Enums\ConversationType;
use App\Enums\Priority;
use App\Enums\ProjectRole;
use App\Enums\TaskStatus;
use App\Enums\Track;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Conversation;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Notifications\MessageReceived;
use App\Notifications\TaskAssigned;
use App\Services\FileService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Demo workspace. Every account uses the password "password".
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // --- Team ------------------------------------------------------------
        $admin = $this->user('Yousef Douhan', 'admin@collabspace.test', Track::BackEnd, 'Team Lead', UserRole::Admin, 4);
        $lina = $this->user('Lina Haddad', 'lina@collabspace.test', Track::UiUx, 'UI/UX Designer', UserRole::Manager, 3);
        $omar = $this->user('Omar Khaled', 'omar@collabspace.test', Track::FrontEnd, 'Frontend Developer', UserRole::Member, 2);
        $sara = $this->user('Sara Nasser', 'sara@collabspace.test', Track::BackEnd, 'Backend Developer', UserRole::Member, 2, Availability::Busy);
        $ahmad = $this->user('Ahmad Saleh', 'ahmad@collabspace.test', Track::GraphicDesign, 'Graphic Designer', UserRole::Member, 1, Availability::Away);

        // --- Projects ----------------------------------------------------------
        $alpha = Project::create([
            'owner_id' => $admin->id,
            'name' => 'Alpha',
            'description' => 'Team collaboration SaaS: projects, kanban tasks, chat and meetings in one workspace.',
            'type' => 'saas',
            'priority' => Priority::High,
            'start_date' => now()->subWeeks(2),
            'end_date' => now()->addWeeks(4),
            'wiki' => "# Alpha\n\n## Goals\n- Ship the MVP by the end of the month\n- Kanban board with drag & drop\n\n## Links\n- Figma: Collabpspace, Team C",
        ]);
        $alpha->members()->attach([
            $admin->id => ['role' => ProjectRole::Owner->value],
            $lina->id => ['role' => ProjectRole::Manager->value],
            $omar->id => ['role' => ProjectRole::Member->value],
            $sara->id => ['role' => ProjectRole::Member->value],
        ]);

        $nova = Project::create([
            'owner_id' => $lina->id,
            'name' => 'Nova Mobile',
            'description' => 'Companion mobile app for task notifications and quick updates.',
            'type' => 'mobile',
            'priority' => Priority::Medium,
            'start_date' => now()->subWeek(),
            'end_date' => now()->addWeeks(8),
        ]);
        $nova->members()->attach([
            $lina->id => ['role' => ProjectRole::Owner->value],
            $ahmad->id => ['role' => ProjectRole::Member->value],
            $omar->id => ['role' => ProjectRole::Member->value],
        ]);

        // --- Teams (members already belong to the linked projects) ------------
        $teams = [
            ['Backend Squad', 'APIs, auth, database and the realtime layer.', $admin, [$sara->id => 'lead', $admin->id => 'member'], [$alpha]],
            ['Design Studio', 'UI/UX and visual design across all products.', $lina, [$lina->id => 'lead', $ahmad->id => 'member'], [$nova]],
            ['Mobile Crew', 'Builds and ships the Nova companion app.', $lina, [$omar->id => 'lead', $lina->id => 'member', $ahmad->id => 'member'], [$nova]],
        ];
        foreach ($teams as [$name, $description, $creator, $members, $linked]) {
            $team = Team::create(['name' => $name, 'description' => $description, 'created_by' => $creator->id]);
            $team->members()->attach(collect($members)->map(fn ($role) => ['role' => $role])->all());
            $team->projects()->attach(collect($linked)->pluck('id'));
        }

        // --- Tasks -------------------------------------------------------------
        $tasks = [
            [$alpha, 'Design Kanban board screens', Track::UiUx, TaskStatus::Completed, Priority::High, [$lina], -10, -2],
            [$alpha, 'Build authentication API with Sanctum', Track::BackEnd, TaskStatus::Completed, Priority::High, [$sara], -9, -3],
            [$alpha, 'Implement projects & tasks endpoints', Track::BackEnd, TaskStatus::InProgress, Priority::High, [$sara, $admin], -4, 3],
            [$alpha, 'Kanban drag & drop on the frontend', Track::FrontEnd, TaskStatus::InReview, Priority::Medium, [$omar], -3, 2],
            [$alpha, 'Chat UI with attachments', Track::FrontEnd, TaskStatus::Todo, Priority::Medium, [$omar], 1, 7],
            [$alpha, 'Meeting AI summaries', Track::BackEnd, TaskStatus::Todo, Priority::Low, [$admin], 2, 9],
            [$alpha, 'Write API documentation', Track::BackEnd, TaskStatus::InProgress, Priority::Medium, [$sara], -6, -1], // overdue
            [$nova, 'App icon and splash screen', Track::GraphicDesign, TaskStatus::InProgress, Priority::Medium, [$ahmad], -2, 4],
            [$nova, 'Notification settings screen', Track::UiUx, TaskStatus::Todo, Priority::Low, [$lina], 1, 10],
        ];

        $positions = [];
        foreach ($tasks as [$project, $title, $track, $status, $priority, $assignees, $startOffset, $dueOffset]) {
            $key = $project->id.'-'.$status->value;
            $positions[$key] = ($positions[$key] ?? -1) + 1;

            $task = Task::create([
                'project_id' => $project->id,
                'created_by' => $project->owner_id,
                'title' => $title,
                'description' => "{$title} for the {$project->name} project.",
                'status' => $status,
                'priority' => $priority,
                'track' => $track,
                'progress' => match ($status) {
                    TaskStatus::Todo => 0,
                    TaskStatus::InProgress => 50,
                    TaskStatus::InReview => 80,
                    TaskStatus::Completed => 100,
                },
                'estimated_hours' => 8,
                'position' => $positions[$key],
                'start_date' => now()->addDays($startOffset),
                'due_date' => now()->addDays($dueOffset),
                'completed_at' => $status === TaskStatus::Completed ? now()->addDays($dueOffset) : null,
            ]);
            $task->assignees()->attach(collect($assignees)->pluck('id'));
        }

        // Review + discussion on the card that is in review.
        $inReview = Task::where('title', 'Kanban drag & drop on the frontend')->firstOrFail();
        $inReview->assignees()->attach($lina->id, ['is_reviewer' => true]);
        $inReview->comments()->create(['user_id' => $lina->id, 'body' => 'Dragging works well. Can we animate the drop?']);
        $inReview->comments()->create(['user_id' => $omar->id, 'body' => 'Sure, adding a 150ms transition now.']);

        // --- Files (real files on the private disk so downloads work) -----------
        $this->file($alpha, $admin, 'alpha-project-scope.md', "# Alpha — Project scope\n\nMVP: projects, kanban, chat, meetings, AI assistant.\n");
        $this->file($alpha, $lina, 'kanban-wireframes-notes.txt', "Kanban wireframes v2\n- 4 columns\n- priority chips\n- assignee avatars\n");
        $this->file($nova, $ahmad, 'nova-brand-colors.txt', "Primary: #4F46E5\nSecondary: #0EA5E9\nSuccess: #10B981\n");

        // --- Chats ------------------------------------------------------------
        $general = Conversation::create(['type' => ConversationType::Channel, 'name' => 'general', 'created_by' => $admin->id]);
        $general->participants()->attach([$admin->id, $lina->id, $omar->id, $sara->id, $ahmad->id]);
        $general->messages()->create(['sender_id' => $admin->id, 'body' => 'Welcome to CollabSpace! Daily sync is at 11:00.']);
        $general->messages()->create(['sender_id' => $lina->id, 'body' => 'Kanban screens are ready in Figma 🎨']);

        $alphaChannel = Conversation::create(['type' => ConversationType::Channel, 'name' => 'alpha-dev', 'project_id' => $alpha->id, 'created_by' => $admin->id]);
        $alphaChannel->participants()->attach([$admin->id, $lina->id, $omar->id, $sara->id]);
        $alphaChannel->messages()->create(['sender_id' => $sara->id, 'body' => 'Auth endpoints are merged. Tokens via Sanctum.']);
        $alphaChannel->messages()->create(['sender_id' => $omar->id, 'body' => 'Great, wiring the login screen now.']);

        $dm = Conversation::create(['type' => ConversationType::Direct, 'created_by' => $admin->id]);
        $dm->participants()->attach([$admin->id, $sara->id]);
        $dm->messages()->create(['sender_id' => $admin->id, 'body' => 'Can you take the API docs task this week?']);
        $dm->messages()->create(['sender_id' => $sara->id, 'body' => 'Sure, I will start after the tasks endpoints.']);

        // --- Meetings ---------------------------------------------------------
        $sync = Meeting::create([
            'project_id' => $alpha->id,
            'created_by' => $admin->id,
            'title' => 'Daily Sync & Sprint Review',
            'agenda' => 'Review task board progress, blockers and next deliverables.',
            'location' => 'Meeting room',
            'meeting_link' => null,
            'starts_at' => now()->subHours(2)->startOfHour(),
            'ends_at' => now()->subHour()->startOfHour(),
            'reminder_minutes' => 60,
            'notes' => "- Move endpoint before Wednesday\n- Omar reviews on Thursday",
            'transcript' => implode("\n", [
                '[00:01] Yousef Douhan: Let\'s review Alpha progress and blockers.',
                '[01:20] Lina Haddad: Kanban screens are done; notification overlay is next.',
                '[03:05] Sara Nasser: Auth API is merged. Projects endpoints are about 60% done.',
                '[04:40] Omar Khaled: Drag and drop is ready for review, waiting on the move endpoint.',
                '[06:10] Sara Nasser: API documentation is late, I need two more days.',
                '[07:30] Yousef Douhan: Sara finishes the move endpoint by Wednesday, Omar reviews it Thursday.',
            ]),
        ]);
        // The past sync was attended (counts towards "Hours Worked").
        $sync->attendees()->attach([$admin->id, $lina->id, $omar->id, $sara->id], ['response' => 'accepted']);

        $planning = Meeting::create([
            'project_id' => $nova->id,
            'created_by' => $lina->id,
            'title' => 'Nova design review',
            'agenda' => 'Walk through the app icon and notification settings.',
            'location' => 'Meeting room',
            'starts_at' => now()->addDay()->setTime(13, 0),
            'ends_at' => now()->addDay()->setTime(14, 0),
            'reminder_minutes' => 60,
        ]);
        $planning->attendees()->attach([$lina->id, $ahmad->id, $omar->id]);

        // --- Notifications ----------------------------------------------------
        // The two comments above already notified the card's owner (the admin). Add a task
        // hand-over and an unread DM, then spread them over the last few hours.
        $aiSummaries = Task::where('title', 'Meeting AI summaries')->firstOrFail();
        $admin->notify(new TaskAssigned($aiSummaries->load('project'), $lina));
        $admin->notify(new MessageReceived($dm, $dm->messages()->latest('id')->firstOrFail(), $sara));
        $minutesAgo = ['comment_added' => [190, 150], 'task_assigned' => [70], 'message_received' => [12]];
        foreach ($admin->notifications()->get() as $notification) {
            $ago = array_shift($minutesAgo[$notification->data['type']]) ?? 30;
            $notification->forceFill([
                'created_at' => now()->subMinutes($ago),
                'read_at' => $ago > 180 ? now()->subMinutes($ago - 5) : null,   // the oldest one is already read
            ])->save();
        }

        // --- Activity --------------------------------------------------------
        foreach ([
            [$admin, $alpha, 'project.created', 'created project Alpha'],
            [$sara, $alpha, 'task.completed', 'completed "Build authentication API with Sanctum"'],
            [$omar, $alpha, 'task.moved', 'moved "Kanban drag & drop on the frontend" to In Review'],
            [$lina, $nova, 'project.created', 'created project Nova Mobile'],
        ] as [$user, $project, $action, $description]) {
            ActivityLog::create([
                'user_id' => $user->id,
                'project_id' => $project->id,
                'subject_type' => Project::class,
                'subject_id' => $project->id,
                'action' => $action,
                'description' => $description,
            ]);
        }
    }

    private function user(
        string $name,
        string $email,
        Track $track,
        string $jobTitle,
        UserRole $role,
        int $years,
        Availability $availability = Availability::Available,
    ): User {
        return User::factory()->create([
            'name' => $name,
            'email' => $email,
            'track' => $track,
            'job_title' => $jobTitle,
            'role' => $role,
            'years_of_experience' => $years,
            'availability' => $availability,
            'joined_at' => now()->subMonths($years * 6),
        ]);
    }

    private function file(Project $project, User $uploader, string $name, string $contents): void
    {
        $path = "projects/{$project->id}/{$name}";
        Storage::disk(FileService::DISK)->put($path, $contents);

        ProjectFile::create([
            'project_id' => $project->id,
            'uploaded_by' => $uploader->id,
            'original_name' => $name,
            'path' => $path,
            'mime_type' => str_ends_with($name, '.md') ? 'text/markdown' : 'text/plain',
            'size_bytes' => strlen($contents),
        ]);
    }
}

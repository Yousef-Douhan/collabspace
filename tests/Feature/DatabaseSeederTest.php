<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_builds_a_consistent_workspace(): void
    {
        Storage::fake('public');

        $this->seed();

        $this->assertSame(5, User::count());
        $this->assertSame(2, Project::count());
        $this->assertSame(9, Task::count());
        $this->assertSame(3, Conversation::count());
        $this->assertSame(2, Meeting::count());

        // Every seeded file really exists on disk, so downloads work.
        ProjectFile::all()->each(fn (ProjectFile $file) => Storage::disk('public')->assertExists($file->path));

        // Alpha: 2 of 7 tasks completed.
        $alpha = Project::where('name', 'Alpha')->firstOrFail();
        $this->assertSame(29, $alpha->completion_percentage);

        // Admin sees every project; a member only sees theirs.
        $admin = User::where('email', 'admin@collabspace.test')->firstOrFail();
        $ahmad = User::where('email', 'ahmad@collabspace.test')->firstOrFail();
        $this->assertSame(2, Project::visibleTo($admin)->count());
        $this->assertSame(['Nova Mobile'], Project::visibleTo($ahmad)->pluck('name')->all());
    }
}

<?php

namespace Database\Factories;

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Enums\Track;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'created_by' => fn (array $attributes) => Project::find($attributes['project_id'])->owner_id,
            'title' => rtrim(fake()->sentence(5), '.'),
            'description' => fake()->paragraph(),
            'status' => TaskStatus::Todo,
            'priority' => fake()->randomElement(Priority::cases()),
            'track' => fake()->randomElement(Track::cases()),
            'progress' => 0,
            'position' => 0,
            'start_date' => now(),
            'due_date' => fake()->dateTimeBetween('+1 day', '+3 weeks'),
        ];
    }

    public function status(TaskStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'progress' => match ($status) {
                TaskStatus::Todo => 0,
                TaskStatus::InProgress => 40,
                TaskStatus::InReview => 80,
                TaskStatus::Completed => 100,
            },
            'completed_at' => $status === TaskStatus::Completed ? now() : null,
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => ['due_date' => now()->subDays(3)]);
    }
}

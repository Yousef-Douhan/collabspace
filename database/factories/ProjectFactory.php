<?php

namespace Database\Factories;

use App\Enums\Priority;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 month', 'now');

        return [
            'owner_id' => User::factory(),
            'name' => ucfirst(fake()->unique()->word()).' '.fake()->randomElement(['App', 'Platform', 'Portal', 'Dashboard']),
            'description' => fake()->sentence(12),
            'type' => fake()->randomElement(['saas', 'mobile', 'website']),
            'priority' => fake()->randomElement(Priority::cases()),
            'status' => ProjectStatus::Active,
            'start_date' => $start,
            'end_date' => fake()->dateTimeBetween('+1 week', '+3 months'),
        ];
    }

    /** The owner is always added as a member with the "owner" role. */
    public function configure(): static
    {
        return $this->afterCreating(function (Project $project) {
            $project->members()->syncWithoutDetaching([
                $project->owner_id => ['role' => ProjectRole::Owner->value],
            ]);
        });
    }
}

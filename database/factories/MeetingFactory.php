<?php

namespace Database\Factories;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meeting>
 */
class MeetingFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 hour', '+1 week');

        return [
            'project_id' => null,
            'created_by' => User::factory(),
            'title' => fake()->randomElement(['Daily Sync', 'Sprint Planning', 'Design Review', 'Retrospective']),
            'agenda' => fake()->sentence(10),
            'location' => 'Meeting room',
            'meeting_link' => null,
            'starts_at' => $start,
            'ends_at' => (clone $start)->modify('+1 hour'),
            'reminder_minutes' => 60,
        ];
    }
}

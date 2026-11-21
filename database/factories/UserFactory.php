<?php

namespace Database\Factories;

use App\Enums\Availability;
use App\Enums\Track;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'job_title' => fake()->jobTitle(),
            'track' => fake()->randomElement(Track::cases()),
            'role' => UserRole::Member,
            'availability' => Availability::Available,
            'years_of_experience' => fake()->numberBetween(0, 10),
            'locale' => 'en',
            'joined_at' => fake()->dateTimeBetween('-2 years'),
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => UserRole::Admin]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}

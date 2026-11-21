<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receives_a_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'track' => 'front_end',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'name', 'email', 'track', 'role']])
            ->assertJsonPath('user.track', 'front_end')
            ->assertJsonPath('user.role', 'member');

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_register_validates_input(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'name' => '',
            'email' => 'taken@example.com',
            'password' => 'short',
            'password_confirmation' => 'different',
            'track' => 'not-a-track',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password', 'track']);
    }

    public function test_user_can_login_with_correct_credentials(): void
    {
        User::factory()->create(['email' => 'lina@example.com']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'lina@example.com',
            'password' => 'password',
        ])->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'email']]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['email' => 'lina@example.com']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'lina@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_protected_routes_require_a_token(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}

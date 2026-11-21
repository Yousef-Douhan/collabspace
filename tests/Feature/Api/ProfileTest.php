<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['name' => 'Omar']);
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    public function test_me_returns_the_authenticated_user(): void
    {
        $this->withToken($this->token)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $this->user->id)
            ->assertJsonPath('data.name', 'Omar')
            ->assertJsonMissingPath('data.password');
    }

    public function test_user_can_update_profile(): void
    {
        $this->withToken($this->token)->patchJson('/api/v1/me', [
            'job_title' => 'Frontend Developer',
            'availability' => 'busy',
            'locale' => 'ar',
        ])->assertOk()
            ->assertJsonPath('data.job_title', 'Frontend Developer')
            ->assertJsonPath('data.availability', 'busy')
            ->assertJsonPath('data.locale', 'ar');
    }

    public function test_profile_update_rejects_invalid_values(): void
    {
        $this->withToken($this->token)->patchJson('/api/v1/me', [
            'availability' => 'sleeping',
            'locale' => 'fr',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['availability', 'locale']);
    }

    public function test_user_can_upload_an_avatar(): void
    {
        Storage::fake('public');

        $response = $this->withToken($this->token)->postJson('/api/v1/me/avatar', [
            'avatar' => UploadedFile::fake()->image('me.png', 200, 200),
        ])->assertOk();

        $path = $this->user->fresh()->avatar_path;
        Storage::disk('public')->assertExists($path);
        $this->assertNotNull($response->json('data.avatar_url'));
    }

    public function test_user_can_change_password_and_other_tokens_are_revoked(): void
    {
        $otherDeviceToken = $this->user->createToken('phone')->plainTextToken;

        $this->withToken($this->token)->putJson('/api/v1/me/password', [
            'current_password' => 'password',
            'password' => 'new-secret-password',
            'password_confirmation' => 'new-secret-password',
        ])->assertNoContent();

        $this->assertTrue(Hash::check('new-secret-password', $this->user->fresh()->password));
        $this->assertSame(1, $this->user->tokens()->count());
        $this->assertNotSame(
            explode('|', $otherDeviceToken)[0],
            (string) $this->user->tokens()->first()->id,
        );
    }

    public function test_change_password_requires_the_current_password(): void
    {
        $this->withToken($this->token)->putJson('/api/v1/me/password', [
            'current_password' => 'wrong',
            'password' => 'new-secret-password',
            'password_confirmation' => 'new-secret-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);
    }
}

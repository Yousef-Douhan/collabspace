<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/** "Forgot password?" for the web UI and the API. */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_login_page_links_to_the_reset_form(): void
    {
        $this->get(route('login'))->assertSee(route('password.request'), false);
        $this->get(route('password.request'))->assertOk()->assertSee(__('Send reset link'));
    }

    public function test_a_reset_link_is_emailed_without_revealing_which_emails_exist(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'mona@example.com']);
        $message = __('If an account exists for this email, a reset link is on its way.');

        $this->from(route('password.request'))->post(route('password.email'), ['email' => 'mona@example.com'])
            ->assertRedirect(route('password.request'))->assertSessionHas('status', $message);
        $this->from(route('password.request'))->post(route('password.email'), ['email' => 'nobody@example.com'])
            ->assertRedirect(route('password.request'))->assertSessionHas('status', $message);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use ($user) {
            $url = $n->toMail($user)->actionUrl;

            return str_contains($url, '/reset-password/'.$n->token) && str_contains($url, urlencode('mona@example.com'));
        });
        Notification::assertSentTimes(ResetPassword::class, 1);
    }

    public function test_the_link_sets_a_new_password_and_signs_out_everywhere(): void
    {
        $user = User::factory()->create(['email' => 'mona@example.com']);
        $user->createToken('phone');
        $token = Password::createToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => 'mona@example.com']))
            ->assertOk()->assertSee('value="mona@example.com"', false);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'mona@example.com',
            'password' => 'new-secret-123',
            'password_confirmation' => 'new-secret-123',
        ])->assertRedirect(route('login'))->assertSessionHas('status', __('passwords.reset'));

        $this->assertTrue(Hash::check('new-secret-123', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->post(route('login'), ['email' => 'mona@example.com', 'password' => 'new-secret-123'])->assertRedirect(route('dashboard'));
    }

    public function test_a_wrong_or_used_token_is_refused(): void
    {
        $user = User::factory()->create(['email' => 'mona@example.com']);

        $this->from(route('password.request'))->post(route('password.update'), [
            'token' => 'not-a-real-token',
            'email' => 'mona@example.com',
            'password' => 'new-secret-123',
            'password_confirmation' => 'new-secret-123',
        ])->assertSessionHasErrors('email');

        $this->assertFalse(Hash::check('new-secret-123', $user->fresh()->password));
    }

    public function test_the_api_offers_the_same_flow(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'mona@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'mona@example.com'])->assertStatus(202);
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])->assertStatus(202);
        Notification::assertSentTo($user, ResetPassword::class);

        $token = Password::createToken($user);
        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token, 'email' => 'mona@example.com', 'password' => 'new-secret-123', 'password_confirmation' => 'new-secret-123',
        ])->assertOk();
        $this->postJson('/api/v1/auth/login', ['email' => 'mona@example.com', 'password' => 'new-secret-123'])->assertOk();

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token, 'email' => 'mona@example.com', 'password' => 'again-123456', 'password_confirmation' => 'again-123456',
        ])->assertStatus(422);
    }
}

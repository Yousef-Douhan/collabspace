<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use RuntimeException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Tests\TestCase;

/** "Continue with GitHub / Google" — the provider is mocked, nothing leaves the machine. */
class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.github.client_id' => 'id', 'services.github.client_secret' => 'secret']);
        config(['services.google.client_id' => null, 'services.google.client_secret' => null]);
    }

    private function profile(array $overrides = []): SocialiteUser
    {
        return (new SocialiteUser)->map(array_merge([
            'id' => '583231',
            'nickname' => 'octocat',
            'name' => 'Mona Octocat',
            'email' => 'mona@example.com',
            'avatar' => 'https://avatars.githubusercontent.com/u/583231',
        ], $overrides));
    }

    private function providerReturns(SocialiteUser $profile): void
    {
        Socialite::shouldReceive('driver->user')->andReturn($profile);
    }

    public function test_only_configured_providers_show_a_button(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('social.redirect', 'github'), false)
            ->assertDontSee(route('social.redirect', 'google'), false);

        $this->get(route('social.redirect', 'google'))->assertNotFound();
        $this->get('/auth/facebook/redirect')->assertNotFound();
    }

    public function test_redirect_sends_the_visitor_to_the_provider(): void
    {
        Socialite::shouldReceive('driver->redirect')
            ->andReturn(new RedirectResponse('https://github.com/login/oauth/authorize?client_id=id'));

        $this->get(route('social.redirect', 'github'))
            ->assertRedirect('https://github.com/login/oauth/authorize?client_id=id');
    }

    public function test_a_new_person_gets_an_account_and_is_signed_in(): void
    {
        $this->providerReturns($this->profile());

        $this->get(route('social.callback', 'github'))->assertRedirect(route('dashboard'));

        $user = User::where('email', 'mona@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Mona Octocat', $user->name);
        $this->assertSame('583231', $user->github_id);
        $this->assertSame('https://avatars.githubusercontent.com/u/583231', $user->avatar_url);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_an_existing_account_with_the_same_email_is_linked_not_duplicated(): void
    {
        $existing = User::factory()->create(['email' => 'Mona@Example.com']);
        $this->providerReturns($this->profile());

        $this->get(route('social.callback', 'github'))->assertRedirect(route('dashboard'));

        $this->assertSame(1, User::count());
        $this->assertSame('583231', $existing->fresh()->github_id);
        $this->assertAuthenticatedAs($existing->fresh());
    }

    public function test_a_returning_user_is_found_by_provider_id_even_if_the_email_changed(): void
    {
        $user = User::factory()->create(['github_id' => '583231', 'email' => 'old@example.com']);
        $this->providerReturns($this->profile(['email' => 'new@example.com']));

        $this->get(route('social.callback', 'github'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::count());
    }

    public function test_deactivated_accounts_stay_locked_out(): void
    {
        User::factory()->create(['email' => 'mona@example.com', 'deactivated_at' => now()]);
        $this->providerReturns($this->profile());

        $this->get(route('social.callback', 'github'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_a_failed_or_cancelled_sign_in_returns_to_the_login_page(): void
    {
        Socialite::shouldReceive('driver->user')->andThrow(new RuntimeException('access_denied'));

        $this->get(route('social.callback', 'github'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_a_profile_without_email_is_refused(): void
    {
        $this->providerReturns($this->profile(['email' => null]));

        $this->get(route('social.callback', 'github'))->assertSessionHasErrors('email');
        $this->assertSame(0, User::count());
    }
}

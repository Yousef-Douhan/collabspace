<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_pages_render_for_admins_only(): void
    {
        $this->actingAs($this->admin)->get(route('admin.index'))->assertOk()->assertSee(__('Admin panel'));
        $this->actingAs($this->admin)->get(route('admin.users', ['role' => 'admin']))->assertOk()->assertSee($this->admin->email);

        $member = User::factory()->create();
        $this->actingAs($member)->get(route('admin.index'))->assertForbidden();
        $this->actingAs($member)->get(route('admin.users'))->assertForbidden();
    }

    public function test_admin_link_only_shows_for_admins(): void
    {
        $this->actingAs($this->admin)->get(route('dashboard'))->assertSee(route('admin.index'), false);
        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertDontSee(route('admin.index'), false);
    }

    public function test_admin_can_change_a_role(): void
    {
        $member = User::factory()->create();

        $this->actingAs($this->admin)
            ->patch(route('admin.users.role', $member), ['role' => 'manager'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(UserRole::Manager, $member->fresh()->role);
        $this->assertDatabaseHas('activity_logs', ['action' => 'user.role_changed', 'subject_id' => $member->id]);
    }

    public function test_admin_cannot_demote_themselves_or_the_last_admin(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('admin.users.role', $this->admin), ['role' => 'member'])
            ->assertSessionHasErrors('role');

        $this->actingAs($this->admin)
            ->patch(route('admin.users.status', $this->admin), ['active' => 0])
            ->assertSessionHasErrors('status');

        $this->assertTrue($this->admin->fresh()->isAdmin());
        $this->assertTrue($this->admin->fresh()->isActive());
    }

    public function test_deactivated_user_is_signed_out_and_cannot_sign_in(): void
    {
        $member = User::factory()->create(['password' => 'secret-123']);
        $token = $member->createToken('phone')->plainTextToken;

        $this->actingAs($this->admin)->patch(route('admin.users.status', $member), ['active' => 0])->assertSessionHasNoErrors();
        $this->assertFalse($member->fresh()->isActive());
        $this->assertSame(0, $member->tokens()->count());

        // Existing web session is ended on the next request. (fresh(): a real request reloads
        // the user from the database; the in-memory $member still has the old status.)
        $this->actingAs($member->fresh())->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();

        // Web and API sign-in are refused.
        $this->post(route('login'), ['email' => $member->email, 'password' => 'secret-123'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->postJson('/api/v1/auth/login', ['email' => $member->email, 'password' => 'secret-123'])->assertUnprocessable();

        // The old API token no longer works.
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/me', ['Authorization' => "Bearer {$token}"])->assertUnauthorized();
    }

    public function test_reactivated_user_can_sign_in_again(): void
    {
        $member = User::factory()->create(['password' => 'secret-123', 'deactivated_at' => now()]);

        $this->actingAs($this->admin)->patch(route('admin.users.status', $member), ['active' => 1])->assertSessionHasNoErrors();
        auth()->logout();

        $this->post(route('login'), ['email' => $member->email, 'password' => 'secret-123'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($member);
    }

    public function test_admin_api(): void
    {
        $member = User::factory()->create(['name' => 'Zed Member']);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/admin/stats')
            ->assertOk()
            ->assertJsonPath('data.users.total', 2)
            ->assertJsonStructure(['data' => ['users', 'projects', 'tasks', 'files', 'recent_activity']]);

        $this->getJson('/api/v1/admin/users?q=Zed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_active', true);

        $this->patchJson("/api/v1/admin/users/{$member->id}/role", ['role' => 'admin'])
            ->assertOk()
            ->assertJsonPath('data.role', 'admin');

        $this->patchJson("/api/v1/admin/users/{$member->id}/status", ['active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        Sanctum::actingAs($member->fresh()->forceFill(['role' => UserRole::Member]));
        $this->getJson('/api/v1/admin/stats')->assertForbidden();
    }
}

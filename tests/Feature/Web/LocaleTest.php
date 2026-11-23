<?php

namespace Tests\Feature\Web;

use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_switch_to_arabic(): void
    {
        $this->from(route('login'))->post(route('locale.switch'), ['locale' => 'ar'])->assertRedirect(route('login'));

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('تسجيل الدخول');
    }

    public function test_unknown_locale_is_rejected(): void
    {
        $this->post(route('locale.switch'), ['locale' => 'de'])->assertNotFound();
    }

    public function test_switching_is_a_form_post_with_no_followable_link(): void
    {
        $this->get('/locale/ar')->assertNotFound();
        $this->get(route('login'))->assertSee('name="locale" value="ar"', false)->assertDontSee('/locale/ar', false);
    }

    public function test_switching_never_redirects_off_site(): void
    {
        $this->post(route('locale.switch'), ['locale' => 'fr'], ['referer' => 'https://evil.example/phish'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_choice_is_saved_on_the_account(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)->post(route('locale.switch'), ['locale' => 'ar']);

        $this->assertSame('ar', $user->fresh()->locale);
    }

    public function test_every_page_renders_in_arabic(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->seed();

        $admin = User::where('email', 'admin@collabspace.test')->firstOrFail();
        $admin->update(['locale' => 'ar']);

        $pages = [
            route('dashboard'),
            route('projects.index'),
            route('projects.show', Project::firstOrFail()),
            route('tasks.index'),
            route('tasks.show', Task::firstOrFail()),
            route('files.index'),
            route('chats.index'),
            route('meetings.index'),
            route('meetings.show', Meeting::firstOrFail()),
            route('reports.index'),
            route('ai.index'),
            route('notifications.index'),
            route('members.show', $admin),
            route('settings.edit'),
            route('search', ['q' => 'Alpha']),
        ];

        foreach ($pages as $url) {
            $this->actingAs($admin)->get($url)->assertOk()->assertSee('dir="rtl"', false);
        }

        $this->actingAs($admin)->get(route('tasks.index'))->assertSee('قيد التنفيذ');
    }

    public function test_validation_messages_are_arabic(): void
    {
        $this->post(route('locale.switch'), ['locale' => 'ar']);

        $this->post(route('login'), ['email' => '', 'password' => ''])
            ->assertSessionHasErrors(['email' => 'حقل البريد الإلكتروني مطلوب.']);
    }

    public function test_api_respects_accept_language(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/meta', ['Accept-Language' => 'ar'])->assertOk()->assertJsonFragment(['label' => 'قيد التنفيذ']);
        $this->getJson('/api/v1/meta')->assertOk()->assertJsonFragment(['label' => 'In Progress']);
    }
}

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
        $this->get(route('locale.switch', 'ar'))->assertRedirect();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('تسجيل الدخول');
    }

    public function test_unknown_locale_is_rejected(): void
    {
        $this->get('/locale/de')->assertNotFound();
    }

    public function test_choice_is_saved_on_the_account(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)->get(route('locale.switch', 'ar'));

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
        $this->get(route('locale.switch', 'ar'));

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

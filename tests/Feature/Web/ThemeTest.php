<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Dark mode: chosen before first paint (no flash) and switchable from every page. */
class ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_pages_set_the_theme_before_paint_and_offer_the_toggle(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee("localStorage.getItem('cs-theme')", false)
            ->assertSee('x-data="themeToggle"', false);
    }

    public function test_app_pages_offer_the_toggle(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee("localStorage.getItem('cs-theme')", false)
            ->assertSee('x-data="themeToggle"', false);
    }
}

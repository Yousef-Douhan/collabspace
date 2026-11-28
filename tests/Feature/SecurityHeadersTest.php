<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_pages_carry_the_hardening_headers(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy')
            ->assertHeader('Content-Security-Policy')
            ->assertHeaderMissing('X-Powered-By');
    }

    public function test_api_errors_carry_them_too(): void
    {
        $this->getJson('/api/v1/projects')
            ->assertUnauthorized()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_every_response_gets_a_request_id(): void
    {
        $id = $this->get(route('login'))->headers->get('X-Request-Id');

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', (string) $id);
    }

    public function test_a_well_formed_incoming_request_id_is_kept_and_a_bad_one_is_replaced(): void
    {
        $this->get(route('login'), ['X-Request-Id' => 'abc12345-from-proxy'])
            ->assertHeader('X-Request-Id', 'abc12345-from-proxy');

        $replaced = $this->get(route('login'), ['X-Request-Id' => 'bad id <script>'])->headers->get('X-Request-Id');
        $this->assertNotSame('bad id <script>', $replaced);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', (string) $replaced);
    }

    public function test_hsts_is_only_sent_over_https_in_production(): void
    {
        $this->get(route('login'))->assertHeaderMissing('Strict-Transport-Security');
    }
}

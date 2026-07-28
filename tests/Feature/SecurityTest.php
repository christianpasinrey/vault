<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create([
            'email' => 'me@example.com',
            'salt' => 'c2FsdA==', 'kdf_algo' => 'pbkdf2-sha256', 'kdf_iterations' => 600000,
            'auth_hash' => 'secret-hash', 'wrapped_vault_key' => 'ZW52', 'vault_key_iv' => 'aXY=',
        ]);
    }

    public function test_the_security_headers_are_present(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $response->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_the_csp_allows_neither_inline_scripts_nor_external_origins(): void
    {
        $csp = $this->get('/login')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringNotContainsString('unsafe-inline', $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
        $this->assertStringNotContainsString('http://', $csp);
    }

    public function test_no_vault_endpoint_returns_anything_in_the_clear(): void
    {
        $user = $this->user();

        Item::create([
            'id' => (string) Str::uuid(), 'user_id' => $user->id,
            'ciphertext' => base64_encode('ENCRYPTED-CONTENT'), 'iv' => base64_encode(random_bytes(12)),
        ]);

        foreach (['/api/vault/items', '/api/vault/export'] as $route) {
            $body = $this->actingAs($user)->getJson($route)->getContent();

            foreach (['password', 'username', 'fields', 'plaintext', 'name'] as $forbidden) {
                $this->assertStringNotContainsString(
                    "\"{$forbidden}\"", $body,
                    "The endpoint {$route} exposes the key '{$forbidden}'.",
                );
            }
        }
    }

    public function test_the_serialized_user_never_includes_the_auth_hash(): void
    {
        $user = $this->user();

        $this->assertArrayNotHasKey('auth_hash', $user->toArray());
        $this->assertStringNotContainsString('secret-hash', json_encode($user));
    }

    public function test_the_session_cookie_is_locked_down(): void
    {
        $this->assertTrue(config('session.http_only'));
        $this->assertTrue(config('session.encrypt'));
        $this->assertSame('strict', config('session.same_site'));
    }

    public function test_debug_mode_is_refused_in_production(): void
    {
        $this->expectException(\RuntimeException::class);

        config(['app.debug' => true]);
        $this->app->detectEnvironment(fn () => 'production');

        (new AppServiceProvider($this->app))->boot();
    }
}

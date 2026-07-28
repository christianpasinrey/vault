<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The SPA loses everything on a page reload except the session cookie. This
 * endpoint is how it learns what it needs to rebuild the wrapping key and unwrap
 * the Vault Key again, without repeating the WebAuthn ceremony.
 */
class AccountMeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create([
            'email' => 'me@example.com',
            'salt' => 'c2FsdA==', 'kdf_algo' => 'pbkdf2-sha256', 'kdf_iterations' => 600000,
            'auth_hash' => Hash::make('hash'),
            'wrapped_vault_key' => 'ZW52', 'vault_key_iv' => 'aXY=',
            'auto_lock_seconds' => 300,
        ]);
    }

    public function test_returns_what_the_client_needs_to_unlock(): void
    {
        $this->actingAs($this->user)->getJson('/api/account/me')
            ->assertOk()
            ->assertExactJson([
                'email' => 'me@example.com',
                'salt' => 'c2FsdA==',
                'kdf_algo' => 'pbkdf2-sha256',
                'kdf_iterations' => 600000,
                'wrapped_vault_key' => 'ZW52',
                'vault_key_iv' => 'aXY=',
                'auto_lock_seconds' => 300,
            ]);
    }

    public function test_never_exposes_the_auth_hash(): void
    {
        $body = $this->actingAs($this->user)->getJson('/api/account/me')->getContent();

        $this->assertStringNotContainsString('auth_hash', $body);
        $this->assertStringNotContainsString($this->user->auth_hash, $body);
    }

    public function test_requires_a_full_session(): void
    {
        $this->getJson('/api/account/me')->assertUnauthorized();
    }
}

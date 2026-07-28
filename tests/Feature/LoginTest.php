<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private string $authHash = 'YXV0aC1oYXNoLWZvci10ZXN0cw==';

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('login:me@example.com');

        User::create([
            'email' => 'me@example.com',
            'salt' => 'dGVzdC1zYWx0',
            'kdf_algo' => 'pbkdf2-sha256',
            'kdf_iterations' => 600000,
            'auth_hash' => Hash::make($this->authHash),
            'wrapped_vault_key' => 'd3JhcHBlZA==',
            'vault_key_iv' => 'aXY=',
        ]);
    }

    public function test_prelogin_returns_the_kdf_parameters(): void
    {
        $this->postJson('/api/auth/prelogin', ['email' => 'me@example.com'])
            ->assertOk()
            ->assertJson([
                'salt' => 'dGVzdC1zYWx0',
                'kdf_algo' => 'pbkdf2-sha256',
                'kdf_iterations' => 600000,
            ]);
    }

    public function test_prelogin_never_exposes_the_auth_hash_or_the_vault_key(): void
    {
        $response = $this->postJson('/api/auth/prelogin', ['email' => 'me@example.com']);

        $response->assertJsonMissingPath('auth_hash');
        $response->assertJsonMissingPath('wrapped_vault_key');
    }

    public function test_login_accepts_the_correct_auth_hash(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'me@example.com',
            'auth_hash' => $this->authHash,
        ])->assertOk()->assertJson(['webauthn_required' => true]);
    }

    public function test_login_does_not_hand_over_the_vault_key_before_the_passkey(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'me@example.com',
            'auth_hash' => $this->authHash,
        ])->assertJsonMissingPath('wrapped_vault_key');
    }

    public function test_login_rejects_an_incorrect_auth_hash(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'me@example.com',
            'auth_hash' => 'aW5jb3JyZWN0',
        ])->assertStatus(422);
    }

    public function test_login_locks_out_after_repeated_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'me@example.com',
                'auth_hash' => 'aW5jb3JyZWN0',
            ]);
        }

        $this->postJson('/api/auth/login', [
            'email' => 'me@example.com',
            'auth_hash' => $this->authHash,
        ])->assertStatus(429);
    }

    public function test_an_unknown_email_does_not_reveal_that_it_does_not_exist(): void
    {
        $this->postJson('/api/auth/prelogin', ['email' => 'other@example.com'])
            ->assertOk()
            ->assertJsonStructure(['salt', 'kdf_algo', 'kdf_iterations']);
    }
}

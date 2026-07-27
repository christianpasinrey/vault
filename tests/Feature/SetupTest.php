<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SetupTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = Str::random(64);

        User::create([
            'email' => 'me@example.com',
            'salt' => '', 'auth_hash' => '', 'wrapped_vault_key' => '', 'vault_key_iv' => '',
            'setup_token' => hash('sha256', $this->token),
            'setup_token_expires_at' => now()->addMinutes(15),
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'token' => $this->token,
            'salt' => base64_encode(random_bytes(16)),
            'kdf_algo' => 'pbkdf2-sha256',
            'kdf_iterations' => 600000,
            'auth_hash' => base64_encode(random_bytes(32)),
            'wrapped_vault_key' => base64_encode(random_bytes(48)),
            'vault_key_iv' => base64_encode(random_bytes(12)),
        ], $overrides);
    }

    public function test_completes_the_setup_and_burns_the_token(): void
    {
        $this->postJson('/api/setup', $this->payload())->assertNoContent();

        $user = User::first();
        $this->assertNotSame('', $user->auth_hash);
        $this->assertNull($user->setup_token);
    }

    public function test_stores_the_auth_hash_hashed_and_not_in_the_clear(): void
    {
        $payload = $this->payload();
        $this->postJson('/api/setup', $payload)->assertNoContent();

        $this->assertNotSame($payload['auth_hash'], User::first()->auth_hash);
        $this->assertStringStartsWith('$argon2id$', User::first()->auth_hash);
    }

    public function test_rejects_a_wrong_token(): void
    {
        $this->postJson('/api/setup', $this->payload(['token' => 'not-the-token']))
            ->assertForbidden();
    }

    public function test_rejects_an_expired_token(): void
    {
        User::first()->update(['setup_token_expires_at' => now()->subMinute()]);

        $this->postJson('/api/setup', $this->payload())->assertForbidden();
    }

    public function test_does_not_allow_setting_up_twice(): void
    {
        $this->postJson('/api/setup', $this->payload())->assertNoContent();
        $this->postJson('/api/setup', $this->payload())->assertForbidden();
    }

    public function test_requires_a_minimum_iteration_count(): void
    {
        $this->postJson('/api/setup', $this->payload(['kdf_iterations' => 1000]))
            ->assertStatus(422);
    }

    public function test_rejects_fields_that_are_not_base64(): void
    {
        $this->postJson('/api/setup', $this->payload(['salt' => 'not base64 !!']))
            ->assertStatus(422);
    }
}

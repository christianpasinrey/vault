<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RotateMasterPasswordTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $current = 'YWN0dWFs';

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create([
            'email' => 'me@example.com',
            'salt' => 'dmllam8=', 'kdf_algo' => 'pbkdf2-sha256', 'kdf_iterations' => 600000,
            'auth_hash' => Hash::make($this->current),
            'wrapped_vault_key' => 'ZW52dWVsdGEtdmllamE=', 'vault_key_iv' => 'aXYtdmllam8=',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'current_auth_hash' => $this->current,
            'salt' => base64_encode(random_bytes(16)),
            'kdf_algo' => 'pbkdf2-sha256',
            'kdf_iterations' => 600000,
            'auth_hash' => base64_encode(random_bytes(32)),
            'wrapped_vault_key' => base64_encode(random_bytes(48)),
            'vault_key_iv' => base64_encode(random_bytes(12)),
        ], $overrides);
    }

    public function test_rotates_salt_auth_hash_and_wrapped_vault_key(): void
    {
        $payload = $this->payload();

        $this->actingAs($this->user)
            ->postJson('/api/account/master-password', $payload)
            ->assertNoContent();

        $user = $this->user->fresh();
        $this->assertSame($payload['salt'], $user->salt);
        $this->assertSame($payload['wrapped_vault_key'], $user->wrapped_vault_key);
        $this->assertTrue(Hash::check($payload['auth_hash'], $user->auth_hash));
    }

    public function test_rejects_when_the_current_master_password_is_wrong(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/account/master-password', $this->payload(['current_auth_hash' => 'bWFs']))
            ->assertStatus(422);

        $this->assertSame('dmllam8=', $this->user->fresh()->salt);
    }

    public function test_does_not_leave_the_user_half_updated_on_invalid_input(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/account/master-password', $this->payload(['wrapped_vault_key' => 'no base64 !!']))
            ->assertStatus(422);

        $user = $this->user->fresh();
        $this->assertSame('dmllam8=', $user->salt);
        $this->assertSame('ZW52dWVsdGEtdmllamE=', $user->wrapped_vault_key);
        $this->assertTrue(Hash::check($this->current, $user->auth_hash));
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/account/master-password', $this->payload())->assertStatus(401);
    }

    public function test_allows_changing_the_auto_lock_timeout(): void
    {
        $this->actingAs($this->user)
            ->putJson('/api/account/settings', ['auto_lock_seconds' => 900])
            ->assertOk();

        $this->assertSame(900, $this->user->fresh()->auto_lock_seconds);
    }

    public function test_rejects_an_unreasonable_auto_lock_timeout(): void
    {
        $this->actingAs($this->user)
            ->putJson('/api/account/settings', ['auto_lock_seconds' => 999999])
            ->assertStatus(422);
    }
}

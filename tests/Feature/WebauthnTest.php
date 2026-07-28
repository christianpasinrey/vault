<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebauthnCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Tests\TestCase;

class WebauthnTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    /** Credential ids are stored base64url encoded, exactly as the browser emits them. */
    private function credentialId(): string
    {
        return Base64UrlSafe::encodeUnpadded(random_bytes(32));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'email' => 'me@example.com',
            'salt' => 'c2FsdA==', 'kdf_algo' => 'pbkdf2-sha256', 'kdf_iterations' => 600000,
            'auth_hash' => Hash::make('YXV0aA=='),
            'wrapped_vault_key' => 'd3JhcHBlZA==', 'vault_key_iv' => 'aXY=',
        ]);

        WebauthnCredential::create([
            'user_id' => $this->user->id,
            'credential_id' => $this->credentialId(), 'public_key' => 'pk', 'sign_count' => 0,
            'name' => 'Windows Hello',
        ]);
    }

    public function test_the_challenge_requires_having_passed_the_login(): void
    {
        $this->postJson('/api/auth/webauthn/challenge')->assertStatus(403);
    }

    public function test_the_challenge_is_issued_after_the_login(): void
    {
        $this->withSession(['half_authenticated' => $this->user->id])
            ->postJson('/api/auth/webauthn/challenge')
            ->assertOk()
            ->assertJsonStructure(['challenge', 'allowCredentials']);
    }

    public function test_verification_fails_without_a_prior_challenge(): void
    {
        $this->withSession(['half_authenticated' => $this->user->id])
            ->postJson('/api/auth/webauthn/verify', ['assertion' => []])
            ->assertStatus(403);
    }

    public function test_the_vault_key_is_only_handed_to_an_authenticated_session(): void
    {
        $this->getJson('/api/vault/items')->assertStatus(401);
    }

    public function test_listing_passkeys_requires_an_authenticated_session(): void
    {
        $this->getJson('/api/account/passkeys')->assertStatus(401);
    }

    public function test_the_last_passkey_cannot_be_deleted(): void
    {
        $this->actingAs($this->user)
            ->deleteJson('/api/account/passkeys/'.WebauthnCredential::first()->id)
            ->assertStatus(422);
    }

    public function test_a_passkey_can_be_deleted_when_another_one_remains(): void
    {
        WebauthnCredential::create([
            'user_id' => $this->user->id,
            'credential_id' => $this->credentialId(), 'public_key' => 'pk2', 'sign_count' => 0,
            'name' => 'iPhone',
        ]);

        $this->actingAs($this->user)
            ->deleteJson('/api/account/passkeys/'.WebauthnCredential::first()->id)
            ->assertNoContent();

        $this->assertSame(1, WebauthnCredential::count());
    }
}

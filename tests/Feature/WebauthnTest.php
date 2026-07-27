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

    private User $usuario;

    /** Los credential_id se guardan en base64url, igual que los emite el navegador. */
    private function idDeCredencial(): string
    {
        return Base64UrlSafe::encodeUnpadded(random_bytes(32));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = User::create([
            'email' => 'yo@ejemplo.com',
            'salt' => 'c2FsdA==', 'kdf_algo' => 'pbkdf2-sha256', 'kdf_iterations' => 600000,
            'auth_hash' => Hash::make('YXV0aA=='),
            'wrapped_vault_key' => 'ZW52dWVsdGE=', 'vault_key_iv' => 'aXY=',
        ]);

        WebauthnCredential::create([
            'user_id' => $this->usuario->id,
            'credential_id' => $this->idDeCredencial(), 'public_key' => 'pk', 'sign_count' => 0,
            'name' => 'Windows Hello',
        ]);
    }

    public function test_el_reto_exige_haber_pasado_el_login(): void
    {
        $this->postJson('/api/auth/webauthn/challenge')->assertStatus(403);
    }

    public function test_el_reto_se_emite_tras_el_login(): void
    {
        $this->withSession(['semiautenticado' => $this->usuario->id])
            ->postJson('/api/auth/webauthn/challenge')
            ->assertOk()
            ->assertJsonStructure(['challenge', 'allowCredentials']);
    }

    public function test_la_verificacion_falla_sin_reto_previo(): void
    {
        $this->withSession(['semiautenticado' => $this->usuario->id])
            ->postJson('/api/auth/webauthn/verify', ['assertion' => []])
            ->assertStatus(403);
    }

    public function test_la_vault_key_solo_se_entrega_a_una_sesion_autenticada(): void
    {
        $this->getJson('/api/vault/items')->assertStatus(401);
    }

    public function test_listar_passkeys_exige_sesion_autenticada(): void
    {
        $this->getJson('/api/account/passkeys')->assertStatus(401);
    }

    public function test_no_se_puede_borrar_la_ultima_passkey(): void
    {
        $this->actingAs($this->usuario)
            ->deleteJson('/api/account/passkeys/'.WebauthnCredential::first()->id)
            ->assertStatus(422);
    }

    public function test_se_puede_borrar_una_passkey_si_queda_otra(): void
    {
        WebauthnCredential::create([
            'user_id' => $this->usuario->id,
            'credential_id' => $this->idDeCredencial(), 'public_key' => 'pk2', 'sign_count' => 0,
            'name' => 'iPhone',
        ]);

        $this->actingAs($this->usuario)
            ->deleteJson('/api/account/passkeys/'.WebauthnCredential::first()->id)
            ->assertNoContent();

        $this->assertSame(1, WebauthnCredential::count());
    }
}

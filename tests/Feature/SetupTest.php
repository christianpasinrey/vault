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
            'email' => 'yo@ejemplo.com',
            'salt' => '', 'auth_hash' => '', 'wrapped_vault_key' => '', 'vault_key_iv' => '',
            'setup_token' => hash('sha256', $this->token),
            'setup_token_expires_at' => now()->addMinutes(15),
        ]);
    }

    private function payload(array $sobrescribir = []): array
    {
        return array_merge([
            'token' => $this->token,
            'salt' => base64_encode(random_bytes(16)),
            'kdf_algo' => 'pbkdf2-sha256',
            'kdf_iterations' => 600000,
            'auth_hash' => base64_encode(random_bytes(32)),
            'wrapped_vault_key' => base64_encode(random_bytes(48)),
            'vault_key_iv' => base64_encode(random_bytes(12)),
        ], $sobrescribir);
    }

    public function test_completa_la_configuracion_y_quema_el_token(): void
    {
        $this->postJson('/api/setup', $this->payload())->assertNoContent();

        $usuario = User::first();
        $this->assertNotSame('', $usuario->auth_hash);
        $this->assertNull($usuario->setup_token);
    }

    public function test_guarda_el_auth_hash_hasheado_y_no_en_claro(): void
    {
        $payload = $this->payload();
        $this->postJson('/api/setup', $payload)->assertNoContent();

        $this->assertNotSame($payload['auth_hash'], User::first()->auth_hash);
        $this->assertStringStartsWith('$argon2id$', User::first()->auth_hash);
    }

    public function test_rechaza_un_token_incorrecto(): void
    {
        $this->postJson('/api/setup', $this->payload(['token' => 'no-es-el-token']))
            ->assertForbidden();
    }

    public function test_rechaza_un_token_caducado(): void
    {
        User::first()->update(['setup_token_expires_at' => now()->subMinute()]);

        $this->postJson('/api/setup', $this->payload())->assertForbidden();
    }

    public function test_no_permite_configurar_dos_veces(): void
    {
        $this->postJson('/api/setup', $this->payload())->assertNoContent();
        $this->postJson('/api/setup', $this->payload())->assertForbidden();
    }

    public function test_exige_un_minimo_de_iteraciones(): void
    {
        $this->postJson('/api/setup', $this->payload(['kdf_iterations' => 1000]))
            ->assertStatus(422);
    }

    public function test_rechaza_campos_que_no_sean_base64(): void
    {
        $this->postJson('/api/setup', $this->payload(['salt' => 'no es base64 !!']))
            ->assertStatus(422);
    }
}

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

    private string $authHash = 'YXV0aC1oYXNoLWRlLXBydWViYQ==';

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('login:yo@ejemplo.com');

        User::create([
            'email' => 'yo@ejemplo.com',
            'salt' => 'c2FsdC1kZS1wcnVlYmE=',
            'kdf_algo' => 'pbkdf2-sha256',
            'kdf_iterations' => 600000,
            'auth_hash' => Hash::make($this->authHash),
            'wrapped_vault_key' => 'ZW52dWVsdGE=',
            'vault_key_iv' => 'aXY=',
        ]);
    }

    public function test_prelogin_devuelve_los_parametros_kdf(): void
    {
        $this->postJson('/api/auth/prelogin', ['email' => 'yo@ejemplo.com'])
            ->assertOk()
            ->assertJson([
                'salt' => 'c2FsdC1kZS1wcnVlYmE=',
                'kdf_algo' => 'pbkdf2-sha256',
                'kdf_iterations' => 600000,
            ]);
    }

    public function test_prelogin_nunca_expone_el_auth_hash_ni_la_vault_key(): void
    {
        $respuesta = $this->postJson('/api/auth/prelogin', ['email' => 'yo@ejemplo.com']);

        $respuesta->assertJsonMissingPath('auth_hash');
        $respuesta->assertJsonMissingPath('wrapped_vault_key');
    }

    public function test_login_acepta_el_auth_hash_correcto(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'yo@ejemplo.com',
            'auth_hash' => $this->authHash,
        ])->assertOk()->assertJson(['webauthn_required' => true]);
    }

    public function test_login_no_entrega_la_vault_key_antes_de_la_passkey(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'yo@ejemplo.com',
            'auth_hash' => $this->authHash,
        ])->assertJsonMissingPath('wrapped_vault_key');
    }

    public function test_login_rechaza_un_auth_hash_incorrecto(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'yo@ejemplo.com',
            'auth_hash' => 'aW5jb3JyZWN0bw==',
        ])->assertStatus(422);
    }

    public function test_login_bloquea_tras_intentos_repetidos(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'yo@ejemplo.com',
                'auth_hash' => 'aW5jb3JyZWN0bw==',
            ]);
        }

        $this->postJson('/api/auth/login', [
            'email' => 'yo@ejemplo.com',
            'auth_hash' => $this->authHash,
        ])->assertStatus(429);
    }

    public function test_un_email_desconocido_no_revela_que_no_existe(): void
    {
        $this->postJson('/api/auth/prelogin', ['email' => 'otro@ejemplo.com'])
            ->assertOk()
            ->assertJsonStructure(['salt', 'kdf_algo', 'kdf_iterations']);
    }
}

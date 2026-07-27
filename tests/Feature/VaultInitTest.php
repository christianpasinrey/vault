<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VaultInitTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_el_usuario_y_emite_un_token_de_setup(): void
    {
        $this->artisan('vault:init', ['--email' => 'yo@ejemplo.com'])->assertSuccessful();

        $usuario = User::firstWhere('email', 'yo@ejemplo.com');

        $this->assertNotNull($usuario);
        $this->assertNotNull($usuario->setup_token);
        $this->assertTrue($usuario->setup_token_expires_at->isFuture());
    }

    public function test_el_token_no_se_guarda_en_claro(): void
    {
        $this->artisan('vault:init', ['--email' => 'yo@ejemplo.com'])->assertSuccessful();

        // Lo almacenado debe ser un SHA-256 de 64 caracteres hexadecimales,
        // no el token que se muestra por consola.
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', User::first()->setup_token);
    }

    public function test_rechaza_crear_un_segundo_usuario(): void
    {
        $this->artisan('vault:init', ['--email' => 'primero@ejemplo.com'])->assertSuccessful();
        $this->artisan('vault:init', ['--email' => 'segundo@ejemplo.com'])->assertFailed();

        $this->assertSame(1, User::count());
    }

    public function test_reemite_el_token_si_la_cuenta_sigue_sin_completar(): void
    {
        $this->artisan('vault:init', ['--email' => 'yo@ejemplo.com'])->assertSuccessful();
        $primero = User::first()->setup_token;

        $this->artisan('vault:init', ['--email' => 'yo@ejemplo.com'])->assertSuccessful();

        $this->assertNotSame($primero, User::first()->setup_token);
    }

    public function test_rechaza_reemitir_si_la_cuenta_ya_esta_completa(): void
    {
        $this->artisan('vault:init', ['--email' => 'yo@ejemplo.com'])->assertSuccessful();

        User::first()->update(['auth_hash' => 'ya-configurada', 'setup_token' => null]);

        $this->artisan('vault:init', ['--email' => 'yo@ejemplo.com'])->assertFailed();
    }

    public function test_rechaza_un_correo_invalido(): void
    {
        $this->artisan('vault:init', ['--email' => 'esto-no-es-un-correo'])->assertFailed();

        $this->assertSame(0, User::count());
    }
}

<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use App\Models\WebauthnCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EsquemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_tabla_users_guarda_el_material_criptografico(): void
    {
        $usuario = User::create([
            'email' => 'yo@ejemplo.com',
            'salt' => 'c2FsdA==',
            'kdf_algo' => 'pbkdf2-sha256',
            'kdf_iterations' => 600000,
            'auth_hash' => 'hash-argon2id',
            'wrapped_vault_key' => 'envuelta',
            'vault_key_iv' => 'iv',
        ]);

        $this->assertSame(600000, $usuario->fresh()->kdf_iterations);
        $this->assertSame(300, $usuario->fresh()->auto_lock_seconds);
    }

    public function test_la_tabla_items_no_tiene_ninguna_columna_de_contenido_en_claro(): void
    {
        $permitidas = ['id', 'user_id', 'ciphertext', 'iv', 'version',
            'deleted_at', 'created_at', 'updated_at'];

        $this->assertEqualsCanonicalizing($permitidas, Schema::getColumnListing('items'));
    }

    public function test_un_item_nace_con_version_uno(): void
    {
        $item = Item::create([
            'user_id' => $this->usuarioDePrueba()->id,
            'ciphertext' => 'blob',
            'iv' => 'iv',
        ]);

        $this->assertSame(1, $item->fresh()->version);
        $this->assertIsString($item->id);
        $this->assertSame(36, strlen($item->id));
    }

    public function test_las_credenciales_webauthn_pertenecen_al_usuario(): void
    {
        $usuario = $this->usuarioDePrueba();

        WebauthnCredential::create([
            'user_id' => $usuario->id,
            'credential_id' => 'cred-1',
            'public_key' => 'clave-publica',
            'sign_count' => 0,
            'name' => 'Windows Hello',
        ]);

        $this->assertCount(1, $usuario->fresh()->webauthnCredentials);
    }

    private function usuarioDePrueba(): User
    {
        return User::create([
            'email' => 'yo@ejemplo.com',
            'salt' => 'c2FsdA==',
            'kdf_algo' => 'pbkdf2-sha256',
            'kdf_iterations' => 600000,
            'auth_hash' => 'hash',
            'wrapped_vault_key' => 'envuelta',
            'vault_key_iv' => 'iv',
        ]);
    }
}

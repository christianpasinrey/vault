<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ItemTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usuario = User::create([
            'email' => 'yo@ejemplo.com',
            'salt' => 'c2FsdA==', 'kdf_algo' => 'pbkdf2-sha256', 'kdf_iterations' => 600000,
            'auth_hash' => 'hash', 'wrapped_vault_key' => 'ZW52', 'vault_key_iv' => 'aXY=',
        ]);
    }

    private function crear(): Item
    {
        return Item::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->usuario->id,
            'ciphertext' => base64_encode(random_bytes(64)),
            'iv' => base64_encode(random_bytes(12)),
        ]);
    }

    public function test_exige_autenticacion(): void
    {
        $this->getJson('/api/vault/items')->assertStatus(401);
        $this->postJson('/api/vault/items', [])->assertStatus(401);
    }

    public function test_crea_un_item_con_el_uuid_que_envia_el_cliente(): void
    {
        $id = (string) Str::uuid();

        $this->actingAs($this->usuario)->postJson('/api/vault/items', [
            'id' => $id,
            'ciphertext' => base64_encode(random_bytes(64)),
            'iv' => base64_encode(random_bytes(12)),
        ])->assertCreated()->assertJsonPath('id', $id);

        $this->assertSame(1, Item::count());
    }

    public function test_lista_los_items_del_usuario_incluida_la_papelera(): void
    {
        $this->crear();
        $this->crear()->delete();

        $this->actingAs($this->usuario)->getJson('/api/vault/items')
            ->assertOk()
            ->assertJsonCount(2, 'items');
    }

    public function test_actualizar_incrementa_la_version(): void
    {
        $item = $this->crear();

        $this->actingAs($this->usuario)->putJson("/api/vault/items/{$item->id}", [
            'ciphertext' => base64_encode(random_bytes(64)),
            'iv' => base64_encode(random_bytes(12)),
            'version' => 1,
        ])->assertOk()->assertJsonPath('version', 2);
    }

    public function test_actualizar_con_una_version_antigua_devuelve_409_y_no_sobrescribe(): void
    {
        $item = $this->crear();
        $original = $item->ciphertext;

        $this->actingAs($this->usuario)->putJson("/api/vault/items/{$item->id}", [
            'ciphertext' => base64_encode(random_bytes(64)),
            'iv' => base64_encode(random_bytes(12)),
            'version' => 99,
        ])->assertStatus(409);

        $this->assertSame($original, $item->fresh()->ciphertext);
    }

    public function test_borrar_manda_a_la_papelera_sin_destruir(): void
    {
        $item = $this->crear();

        $this->actingAs($this->usuario)->deleteJson("/api/vault/items/{$item->id}")
            ->assertNoContent();

        $this->assertNotNull($item->fresh()->deleted_at);
    }

    public function test_restaurar_saca_de_la_papelera(): void
    {
        $item = $this->crear();
        $item->delete();

        $this->actingAs($this->usuario)->postJson("/api/vault/items/{$item->id}/restore")
            ->assertOk();

        $this->assertNull($item->fresh()->deleted_at);
    }

    public function test_rechaza_ciphertext_que_no_sea_base64(): void
    {
        $this->actingAs($this->usuario)->postJson('/api/vault/items', [
            'id' => (string) Str::uuid(),
            'ciphertext' => 'esto no es base64 !!!',
            'iv' => base64_encode(random_bytes(12)),
        ])->assertStatus(422);
    }

    public function test_el_export_devuelve_solo_blobs_cifrados(): void
    {
        $this->crear();

        $respuesta = $this->actingAs($this->usuario)->getJson('/api/vault/export')->assertOk();

        foreach ($respuesta->json('items') as $item) {
            $this->assertArrayHasKey('ciphertext', $item);
            $this->assertArrayNotHasKey('name', $item);
            $this->assertArrayNotHasKey('fields', $item);
        }
    }
}

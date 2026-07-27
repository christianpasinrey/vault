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

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create([
            'email' => 'me@example.com',
            'salt' => 'c2FsdA==', 'kdf_algo' => 'pbkdf2-sha256', 'kdf_iterations' => 600000,
            'auth_hash' => 'hash', 'wrapped_vault_key' => 'd3JhcA==', 'vault_key_iv' => 'aXY=',
        ]);
    }

    private function makeItem(): Item
    {
        return Item::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'ciphertext' => base64_encode(random_bytes(64)),
            'iv' => base64_encode(random_bytes(12)),
        ]);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/vault/items')->assertStatus(401);
        $this->postJson('/api/vault/items', [])->assertStatus(401);
    }

    public function test_creates_an_item_with_the_uuid_sent_by_the_client(): void
    {
        $id = (string) Str::uuid();

        $this->actingAs($this->user)->postJson('/api/vault/items', [
            'id' => $id,
            'ciphertext' => base64_encode(random_bytes(64)),
            'iv' => base64_encode(random_bytes(12)),
        ])->assertCreated()->assertJsonPath('id', $id);

        $this->assertSame(1, Item::count());
    }

    public function test_lists_the_items_of_the_user_including_the_trash(): void
    {
        $this->makeItem();
        $this->makeItem()->delete();

        $this->actingAs($this->user)->getJson('/api/vault/items')
            ->assertOk()
            ->assertJsonCount(2, 'items');
    }

    public function test_updating_bumps_the_version(): void
    {
        $item = $this->makeItem();

        $this->actingAs($this->user)->putJson("/api/vault/items/{$item->id}", [
            'ciphertext' => base64_encode(random_bytes(64)),
            'iv' => base64_encode(random_bytes(12)),
            'version' => 1,
        ])->assertOk()->assertJsonPath('version', 2);
    }

    public function test_updating_with_a_stale_version_returns_409_and_overwrites_nothing(): void
    {
        $item = $this->makeItem();
        $original = $item->ciphertext;

        $this->actingAs($this->user)->putJson("/api/vault/items/{$item->id}", [
            'ciphertext' => base64_encode(random_bytes(64)),
            'iv' => base64_encode(random_bytes(12)),
            'version' => 99,
        ])->assertStatus(409);

        $this->assertSame($original, $item->fresh()->ciphertext);
    }

    public function test_deleting_moves_to_the_trash_without_destroying(): void
    {
        $item = $this->makeItem();

        $this->actingAs($this->user)->deleteJson("/api/vault/items/{$item->id}")
            ->assertNoContent();

        $this->assertNotNull($item->fresh()->deleted_at);
    }

    public function test_restoring_takes_it_out_of_the_trash(): void
    {
        $item = $this->makeItem();
        $item->delete();

        $this->actingAs($this->user)->postJson("/api/vault/items/{$item->id}/restore")
            ->assertOk();

        $this->assertNull($item->fresh()->deleted_at);
    }

    public function test_rejects_a_ciphertext_that_is_not_base64(): void
    {
        $this->actingAs($this->user)->postJson('/api/vault/items', [
            'id' => (string) Str::uuid(),
            'ciphertext' => 'this is not base64 !!!',
            'iv' => base64_encode(random_bytes(12)),
        ])->assertStatus(422);
    }

    public function test_the_export_returns_only_encrypted_blobs(): void
    {
        $this->makeItem();

        $response = $this->actingAs($this->user)->getJson('/api/vault/export')->assertOk();

        foreach ($response->json('items') as $item) {
            $this->assertArrayHasKey('ciphertext', $item);
            $this->assertArrayNotHasKey('name', $item);
            $this->assertArrayNotHasKey('fields', $item);
        }
    }
}

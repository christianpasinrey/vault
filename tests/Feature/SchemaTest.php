<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use App\Models\WebauthnCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_users_table_stores_the_cryptographic_material(): void
    {
        $user = User::create([
            'email' => 'me@example.com',
            'salt' => 'c2FsdA==',
            'kdf_algo' => 'pbkdf2-sha256',
            'kdf_iterations' => 600000,
            'auth_hash' => 'argon2id-hash',
            'wrapped_vault_key' => 'wrapped',
            'vault_key_iv' => 'iv',
        ]);

        $this->assertSame(600000, $user->fresh()->kdf_iterations);
        $this->assertSame(300, $user->fresh()->auto_lock_seconds);
    }

    public function test_the_items_table_has_no_plaintext_content_column(): void
    {
        $allowed = ['id', 'user_id', 'ciphertext', 'iv', 'version',
            'deleted_at', 'created_at', 'updated_at'];

        $this->assertEqualsCanonicalizing($allowed, Schema::getColumnListing('items'));
    }

    public function test_an_item_is_born_at_version_one(): void
    {
        $item = Item::create([
            'user_id' => $this->makeUser()->id,
            'ciphertext' => 'blob',
            'iv' => 'iv',
        ]);

        $this->assertSame(1, $item->fresh()->version);
        $this->assertIsString($item->id);
        $this->assertSame(36, strlen($item->id));
    }

    public function test_webauthn_credentials_belong_to_the_user(): void
    {
        $user = $this->makeUser();

        WebauthnCredential::create([
            'user_id' => $user->id,
            'credential_id' => 'cred-1',
            'public_key' => 'public-key',
            'sign_count' => 0,
            'name' => 'Windows Hello',
        ]);

        $this->assertCount(1, $user->fresh()->webauthnCredentials);
    }

    private function makeUser(): User
    {
        return User::create([
            'email' => 'me@example.com',
            'salt' => 'c2FsdA==',
            'kdf_algo' => 'pbkdf2-sha256',
            'kdf_iterations' => 600000,
            'auth_hash' => 'hash',
            'wrapped_vault_key' => 'wrapped',
            'vault_key_iv' => 'iv',
        ]);
    }
}

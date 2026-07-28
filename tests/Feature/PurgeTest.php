<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurgeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create([
            'email' => 'me@example.com',
            'salt' => 'c2FsdA==', 'kdf_algo' => 'pbkdf2-sha256', 'kdf_iterations' => 600000,
            'auth_hash' => 'hash', 'wrapped_vault_key' => 'ZW52', 'vault_key_iv' => 'aXY=',
        ]);
    }

    private function item(?int $deletedDaysAgo = null): Item
    {
        $item = Item::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'ciphertext' => base64_encode('opaque'),
            'iv' => base64_encode(random_bytes(12)),
            'version' => 1,
        ]);

        if ($deletedDaysAgo !== null) {
            $item->forceFill(['deleted_at' => now()->subDays($deletedDaysAgo)])->save();
        }

        return $item;
    }

    public function test_destroys_only_what_has_been_in_the_trash_long_enough(): void
    {
        $live = $this->item();
        $recentlyDeleted = $this->item(deletedDaysAgo: 10);
        $longDeleted = $this->item(deletedDaysAgo: 40);

        $this->artisan('vault:purge')->assertSuccessful();

        $this->assertDatabaseHas('items', ['id' => $live->id]);
        $this->assertDatabaseHas('items', ['id' => $recentlyDeleted->id]);
        $this->assertDatabaseMissing('items', ['id' => $longDeleted->id]);
    }

    public function test_reports_how_many_it_destroyed(): void
    {
        $this->item(deletedDaysAgo: 40);
        $this->item(deletedDaysAgo: 40);

        $this->artisan('vault:purge')
            ->expectsOutputToContain('2')
            ->assertSuccessful();
    }

    public function test_does_nothing_when_the_trash_is_cold(): void
    {
        $this->item();

        $this->artisan('vault:purge')->assertSuccessful();

        $this->assertSame(1, Item::withTrashed()->count());
    }
}

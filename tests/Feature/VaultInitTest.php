<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VaultInitTest extends TestCase
{
    use RefreshDatabase;

    private const QUESTION = 'Email address you will sign in with';

    public function test_creates_the_user_and_issues_a_setup_token(): void
    {
        $this->artisan('vault:init', ['--email' => 'me@example.com'])->assertSuccessful();

        $user = User::firstWhere('email', 'me@example.com');

        $this->assertNotNull($user);
        $this->assertNotNull($user->setup_token);
        $this->assertTrue($user->setup_token_expires_at->isFuture());
    }

    public function test_the_token_is_not_stored_in_the_clear(): void
    {
        $this->artisan('vault:init', ['--email' => 'me@example.com'])->assertSuccessful();

        // What is stored must be a 64-character hexadecimal SHA-256, not the
        // token printed on the console.
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', User::first()->setup_token);
    }

    public function test_refuses_to_create_a_second_user(): void
    {
        $this->artisan('vault:init', ['--email' => 'first@example.com'])->assertSuccessful();
        $this->artisan('vault:init', ['--email' => 'second@example.com'])->assertFailed();

        $this->assertSame(1, User::count());
    }

    public function test_reissues_the_token_while_the_account_is_incomplete(): void
    {
        $this->artisan('vault:init', ['--email' => 'me@example.com'])->assertSuccessful();
        $first = User::first()->setup_token;

        $this->artisan('vault:init', ['--email' => 'me@example.com'])->assertSuccessful();

        $this->assertNotSame($first, User::first()->setup_token);
    }

    public function test_refuses_to_reissue_once_the_account_is_set_up(): void
    {
        $this->artisan('vault:init', ['--email' => 'me@example.com'])->assertSuccessful();

        User::first()->update(['auth_hash' => 'already-configured', 'setup_token' => null]);

        $this->artisan('vault:init', ['--email' => 'me@example.com'])->assertFailed();
    }

    public function test_rejects_an_invalid_email(): void
    {
        $this->artisan('vault:init', ['--email' => 'not-an-email'])->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_asks_for_the_email_when_the_option_is_missing(): void
    {
        $this->artisan('vault:init')
            ->expectsQuestion(self::QUESTION, 'me@example.com')
            ->assertSuccessful();

        $this->assertNotNull(User::firstWhere('email', 'me@example.com'));
    }

    public function test_asks_again_when_the_email_is_invalid(): void
    {
        // A mistyped address would tie the installation to the wrong account
        // forever: better to insist than to fail.
        $this->artisan('vault:init')
            ->expectsQuestion(self::QUESTION, 'not-an-email')
            ->expectsOutputToContain('That email address is not valid.')
            ->expectsQuestion(self::QUESTION, 'me@example.com')
            ->assertSuccessful();

        $this->assertSame(1, User::count());
        $this->assertNotNull(User::firstWhere('email', 'me@example.com'));
    }

    public function test_gives_up_after_several_invalid_emails(): void
    {
        $this->artisan('vault:init')
            ->expectsQuestion(self::QUESTION, 'one')
            ->expectsQuestion(self::QUESTION, 'two')
            ->expectsQuestion(self::QUESTION, 'three')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_prints_the_setup_link(): void
    {
        config(['app.url' => 'https://vault.example.com']);

        $this->artisan('vault:init', ['--email' => 'me@example.com'])
            ->expectsOutputToContain('https://vault.example.com/setup?token=')
            ->assertSuccessful();
    }
}

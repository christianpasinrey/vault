<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class VaultInit extends Command
{
    protected $signature = 'vault:init {--email= : Email address you will sign in with}';

    protected $description = 'Create the single account of this system and issue a one-time setup link';

    /** Attempts before giving up when the email is typed by hand. */
    private const ATTEMPTS = 3;

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->askForEmail();

        if ($email === null || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('That email address is not valid.');

            return self::FAILURE;
        }

        $existing = User::first();

        if ($existing && $existing->email !== $email) {
            $this->error("An account already exists ({$existing->email}). This system is single-user.");

            return self::FAILURE;
        }

        if ($existing && $existing->auth_hash !== '') {
            $this->error('The account is already set up. Reconfiguring it would mean destroying the vault.');

            return self::FAILURE;
        }

        $token = Str::random(64);

        $user = $existing ?? new User;
        $user->fill([
            'email' => $email,
            'salt' => '',
            'auth_hash' => '',
            'wrapped_vault_key' => '',
            'vault_key_iv' => '',
            // Only the SHA-256 is stored, never the token itself: a database
            // dump does not let anyone reuse the setup link.
            'setup_token' => hash('sha256', $token),
            'setup_token_expires_at' => now()->addMinutes(15),
        ])->save();

        $url = rtrim(config('app.url'), '/')."/setup?token={$token}";

        $this->newLine();
        $this->info('Account ready. Open this link in your browser within the next 15 minutes:');
        $this->newLine();
        $this->line($url);
        $this->newLine();
        $this->warn('The master password you choose cannot be recovered. Store it outside this system.');
        $this->warn('Register at least two passkeys before trusting it with anything important.');

        return self::SUCCESS;
    }

    /**
     * Ask for the email on the console until it is valid.
     *
     * This system is single-user and the account cannot be renamed without
     * destroying the vault, so a typo is expensive. Returns null once the
     * attempts run out.
     */
    private function askForEmail(): ?string
    {
        for ($attempt = 1; $attempt <= self::ATTEMPTS; $attempt++) {
            $email = trim((string) $this->ask('Email address you will sign in with'));

            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }

            if ($attempt < self::ATTEMPTS) {
                $this->error('That email address is not valid.');
            }
        }

        return null;
    }
}

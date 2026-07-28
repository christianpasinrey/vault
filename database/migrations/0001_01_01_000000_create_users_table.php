<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();

            // Cryptographic material. None of this decrypts anything on its own:
            // auth_hash is an access credential, and the Vault Key arrives
            // wrapped with a key that only ever exists in the browser.
            $table->string('salt');                    // base64, 16 bytes
            $table->string('kdf_algo')->default('pbkdf2-sha256');
            $table->unsignedInteger('kdf_iterations')->default(600000);
            $table->string('auth_hash');               // Argon2id of the received auth hash
            $table->text('wrapped_vault_key');
            $table->string('vault_key_iv');

            $table->unsignedInteger('auto_lock_seconds')->default(300);

            // Single-use bootstrap. There is no public sign-up.
            $table->string('setup_token')->nullable();
            $table->timestamp('setup_token_expires_at')->nullable();

            $table->rememberToken();
            $table->timestamps();
        });

        // No password reset table: by design, this system offers no recovery.

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('sessions');
    }
};

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

            // Material criptografico. Nada de esto permite descifrar por si solo:
            // el auth_hash es una credencial de acceso y la Vault Key llega
            // envuelta con una clave que solo existe en el navegador.
            $table->string('salt');                    // base64, 16 bytes
            $table->string('kdf_algo')->default('pbkdf2-sha256');
            $table->unsignedInteger('kdf_iterations')->default(600000);
            $table->string('auth_hash');               // Argon2id del auth hash recibido
            $table->text('wrapped_vault_key');
            $table->string('vault_key_iv');

            $table->unsignedInteger('auto_lock_seconds')->default(300);

            // Bootstrap de un solo uso. No existe registro publico.
            $table->string('setup_token')->nullable();
            $table->timestamp('setup_token_expires_at')->nullable();

            $table->rememberToken();
            $table->timestamps();
        });

        // No hay tabla de reseteo de contrasena: en este sistema no existe
        // recuperacion posible, por diseno.

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

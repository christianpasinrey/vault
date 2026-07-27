<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Todo el contenido vive aqui dentro, cifrado: nombre, carpeta, tipo
            // y campos. El servidor no entiende nada de lo que almacena.
            $table->longText('ciphertext');
            $table->string('iv');

            // Concurrencia optimista entre dispositivos.
            $table->unsignedInteger('version')->default(1);

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};

<?php

namespace App\Http\Controllers;

use App\Http\Requests\SetupRequest;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class SetupController extends Controller
{
    public function store(SetupRequest $peticion): Response
    {
        $usuario = User::first();

        abort_if($usuario === null, 403);
        abort_if($usuario->setup_token === null, 403, 'La cuenta ya está configurada.');
        abort_if($usuario->setup_token_expires_at?->isPast() ?? true, 403, 'El enlace ha caducado.');

        abort_unless(
            hash_equals($usuario->setup_token, hash('sha256', $peticion->string('token')->toString())),
            403,
        );

        $usuario->update([
            'salt' => $peticion->string('salt')->toString(),
            'kdf_algo' => $peticion->string('kdf_algo')->toString(),
            'kdf_iterations' => $peticion->integer('kdf_iterations'),
            // El auth hash llega derivado del cliente; aquí se vuelve a hashear
            // para que un volcado de la base de datos no entregue una credencial usable.
            'auth_hash' => Hash::make($peticion->string('auth_hash')->toString()),
            'wrapped_vault_key' => $peticion->string('wrapped_vault_key')->toString(),
            'vault_key_iv' => $peticion->string('vault_key_iv')->toString(),
            'setup_token' => null,
            'setup_token_expires_at' => null,
        ]);

        return response()->noContent();
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\SetupRequest;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class SetupController extends Controller
{
    public function store(SetupRequest $request): Response
    {
        $user = User::first();

        abort_if($user === null, 403);
        abort_if($user->setup_token === null, 403, 'The account is already set up.');
        abort_if($user->setup_token_expires_at?->isPast() ?? true, 403, 'The link has expired.');

        abort_unless(
            hash_equals($user->setup_token, hash('sha256', $request->string('token')->toString())),
            403,
        );

        $user->update([
            'salt' => $request->string('salt')->toString(),
            'kdf_algo' => $request->string('kdf_algo')->toString(),
            'kdf_iterations' => $request->integer('kdf_iterations'),
            // The auth hash arrives already derived by the client; it is hashed
            // again here so that a database dump does not hand over a usable
            // credential.
            'auth_hash' => Hash::make($request->string('auth_hash')->toString()),
            'wrapped_vault_key' => $request->string('wrapped_vault_key')->toString(),
            'vault_key_iv' => $request->string('vault_key_iv')->toString(),
            'setup_token' => null,
            'setup_token_expires_at' => null,
        ]);

        // The account has no passkey yet, and registering one requires an
        // authenticated session — while signing in requires a passkey. The
        // one-time token, delivered by `vault:init` on the server console, is
        // what breaks that tie: it authenticates this session exactly once so
        // the first passkey can be enrolled.
        Auth::login($user);
        $request->session()->regenerate();

        return response()->noContent();
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function rotateMasterPassword(Request $request): Response
    {
        // Validate the full payload before touching anything: a partial write
        // that updates the auth hash but leaves the old wrapped Vault Key would
        // permanently lock the vault.
        $data = $request->validate([
            'current_auth_hash' => ['required', 'string'],
            'salt' => ['required', 'string', 'base64'],
            'kdf_algo' => ['required', Rule::in(['pbkdf2-sha256'])],
            'kdf_iterations' => ['required', 'integer', 'min:600000'],
            'auth_hash' => ['required', 'string', 'base64'],
            'wrapped_vault_key' => ['required', 'string', 'base64'],
            'vault_key_iv' => ['required', 'string', 'base64'],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_auth_hash'], $user->auth_hash)) {
            throw ValidationException::withMessages([
                'current_auth_hash' => 'The current master password is incorrect.',
            ]);
        }

        // All or nothing: salt, auth hash and re-wrapped Vault Key move together.
        DB::transaction(function () use ($user, $data) {
            $user->update([
                'salt' => $data['salt'],
                'kdf_algo' => $data['kdf_algo'],
                'kdf_iterations' => $data['kdf_iterations'],
                'auth_hash' => Hash::make($data['auth_hash']),
                'wrapped_vault_key' => $data['wrapped_vault_key'],
                'vault_key_iv' => $data['vault_key_iv'],
            ]);
        });

        return response()->noContent();
    }

    public function settings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'auto_lock_seconds' => ['required', 'integer', 'min:60', 'max:3600'],
        ]);

        $request->user()->update($data);

        return response()->json(['auto_lock_seconds' => $data['auto_lock_seconds']]);
    }
}

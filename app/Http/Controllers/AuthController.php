<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function prelogin(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        $user = User::firstWhere('email', $data['email']);

        // An unknown address gets a fake but stable salt, derived with an HMAC
        // of APP_KEY: the response neither reveals whether the account exists
        // nor changes between requests, which would be just as revealing.
        if ($user === null) {
            return response()->json([
                'salt' => base64_encode(substr(hash_hmac('sha256', $data['email'], config('app.key'), true), 0, 16)),
                'kdf_algo' => 'pbkdf2-sha256',
                'kdf_iterations' => 600000,
            ]);
        }

        return response()->json([
            'salt' => $user->salt,
            'kdf_algo' => $user->kdf_algo,
            'kdf_iterations' => $user->kdf_iterations,
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'auth_hash' => ['required', 'string'],
        ]);

        $key = 'login:'.$data['email'];

        if (RateLimiter::tooManyAttempts($key, 5)) {
            abort(429, 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.');
        }

        $user = User::firstWhere('email', $data['email']);

        if ($user === null || ! Hash::check($data['auth_hash'], $user->auth_hash)) {
            RateLimiter::hit($key, 300);

            throw ValidationException::withMessages([
                'auth_hash' => 'Invalid credentials.',
            ]);
        }

        RateLimiter::clear($key);

        // Half-authenticated: the passkey is still required before the wrapped
        // Vault Key is handed over.
        $request->session()->put('half_authenticated', $user->id);

        return response()->json(['webauthn_required' => true]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->session()->flush();
        $request->session()->regenerate();

        return response()->json(['ok' => true]);
    }
}

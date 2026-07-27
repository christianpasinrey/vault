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
    public function prelogin(Request $peticion): JsonResponse
    {
        $datos = $peticion->validate(['email' => ['required', 'email']]);

        $usuario = User::firstWhere('email', $datos['email']);

        // Un correo desconocido recibe un salt falso pero estable, derivado con
        // HMAC de APP_KEY: la respuesta no delata si la cuenta existe ni cambia
        // entre peticiones, que sería igual de revelador.
        if ($usuario === null) {
            return response()->json([
                'salt' => base64_encode(substr(hash_hmac('sha256', $datos['email'], config('app.key'), true), 0, 16)),
                'kdf_algo' => 'pbkdf2-sha256',
                'kdf_iterations' => 600000,
            ]);
        }

        return response()->json([
            'salt' => $usuario->salt,
            'kdf_algo' => $usuario->kdf_algo,
            'kdf_iterations' => $usuario->kdf_iterations,
        ]);
    }

    public function login(Request $peticion): JsonResponse
    {
        $datos = $peticion->validate([
            'email' => ['required', 'email'],
            'auth_hash' => ['required', 'string'],
        ]);

        $clave = 'login:'.$datos['email'];

        if (RateLimiter::tooManyAttempts($clave, 5)) {
            abort(429, 'Demasiados intentos. Espera '.RateLimiter::availableIn($clave).' segundos.');
        }

        $usuario = User::firstWhere('email', $datos['email']);

        if ($usuario === null || ! Hash::check($datos['auth_hash'], $usuario->auth_hash)) {
            RateLimiter::hit($clave, 300);

            throw ValidationException::withMessages([
                'auth_hash' => 'Credenciales incorrectas.',
            ]);
        }

        RateLimiter::clear($clave);

        // Semiautenticado: falta la passkey antes de entregar la Vault Key envuelta.
        $peticion->session()->put('semiautenticado', $usuario->id);

        return response()->json(['webauthn_required' => true]);
    }

    public function logout(Request $peticion): JsonResponse
    {
        $peticion->session()->flush();
        $peticion->session()->regenerate();

        return response()->json(['ok' => true]);
    }
}

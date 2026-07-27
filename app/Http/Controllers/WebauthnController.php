<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WebauthnCredential;
use App\Services\WebauthnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class WebauthnController extends Controller
{
    public function __construct(private readonly WebauthnService $webauthn) {}

    // --- Segundo factor del login ---------------------------------------

    public function challenge(Request $peticion): JsonResponse
    {
        return response()->json($this->webauthn->opcionesDeAsercion($this->semiautenticado($peticion)));
    }

    public function verify(Request $peticion): JsonResponse
    {
        $usuario = $this->semiautenticado($peticion);

        abort_unless($peticion->session()->has(WebauthnService::CLAVE_RETO), 403, 'No hay ningún reto pendiente.');

        abort_unless(
            $this->webauthn->verificar($usuario, (array) $peticion->input('assertion', [])),
            422,
            'La passkey no ha podido verificarse.',
        );

        Auth::login($usuario);
        $peticion->session()->forget('semiautenticado');
        $peticion->session()->regenerate();

        // La Vault Key viaja envuelta: sin la master password no sirve de nada.
        return response()->json([
            'wrapped_vault_key' => $usuario->wrapped_vault_key,
            'vault_key_iv' => $usuario->vault_key_iv,
            'auto_lock_seconds' => $usuario->auto_lock_seconds,
        ]);
    }

    // --- Gestión de passkeys de la cuenta -------------------------------

    public function index(Request $peticion): JsonResponse
    {
        return response()->json(
            $peticion->user()->webauthnCredentials()
                ->get(['id', 'name', 'last_used_at', 'created_at'])
        );
    }

    public function registroChallenge(Request $peticion): JsonResponse
    {
        return response()->json($this->webauthn->opcionesDeRegistro($peticion->user()));
    }

    public function store(Request $peticion): Response
    {
        $datos = $peticion->validate([
            'name' => ['required', 'string', 'max:100'],
            'credential' => ['required', 'array'],
        ]);

        $this->webauthn->registrar($peticion->user(), $datos['credential'], $datos['name']);

        return response()->noContent();
    }

    public function destroy(Request $peticion, WebauthnCredential $credencial): Response
    {
        abort_unless($credencial->user_id === $peticion->user()->id, 404);

        // Quedarse sin passkeys dejaría la cuenta inaccesible para siempre: no
        // hay recuperación posible en este sistema.
        abort_if(
            $peticion->user()->webauthnCredentials()->count() <= 1,
            422,
            'No puedes borrar la última passkey.',
        );

        $credencial->delete();

        return response()->noContent();
    }

    private function semiautenticado(Request $peticion): User
    {
        $id = $peticion->session()->get('semiautenticado');

        abort_if($id === null, 403, 'Primero hay que pasar el login.');

        return User::findOrFail($id);
    }
}

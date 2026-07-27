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

    // --- Second factor of the login -------------------------------------

    public function challenge(Request $request): JsonResponse
    {
        return response()->json($this->webauthn->assertionOptions($this->halfAuthenticated($request)));
    }

    public function verify(Request $request): JsonResponse
    {
        $user = $this->halfAuthenticated($request);

        abort_unless($request->session()->has(WebauthnService::CHALLENGE_KEY), 403, 'There is no pending challenge.');

        abort_unless(
            $this->webauthn->verify($user, (array) $request->input('assertion', [])),
            422,
            'The passkey could not be verified.',
        );

        Auth::login($user);
        $request->session()->forget('half_authenticated');
        $request->session()->regenerate();

        // The Vault Key travels wrapped: without the master password it is useless.
        return response()->json([
            'wrapped_vault_key' => $user->wrapped_vault_key,
            'vault_key_iv' => $user->vault_key_iv,
            'auto_lock_seconds' => $user->auto_lock_seconds,
        ]);
    }

    // --- Passkey management ---------------------------------------------

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->webauthnCredentials()
                ->get(['id', 'name', 'last_used_at', 'created_at'])
        );
    }

    public function registrationChallenge(Request $request): JsonResponse
    {
        return response()->json($this->webauthn->registrationOptions($request->user()));
    }

    public function store(Request $request): Response
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'credential' => ['required', 'array'],
        ]);

        $this->webauthn->register($request->user(), $data['credential'], $data['name']);

        return response()->noContent();
    }

    public function destroy(Request $request, WebauthnCredential $credential): Response
    {
        abort_unless($credential->user_id === $request->user()->id, 404);

        // Running out of passkeys would lock the account forever: this system
        // has no recovery path.
        abort_if(
            $request->user()->webauthnCredentials()->count() <= 1,
            422,
            'You cannot delete your last passkey.',
        );

        $credential->delete();

        return response()->noContent();
    }

    private function halfAuthenticated(Request $request): User
    {
        $id = $request->session()->get('half_authenticated');

        abort_if($id === null, 403, 'You have to pass the login first.');

        return User::findOrFail($id);
    }
}

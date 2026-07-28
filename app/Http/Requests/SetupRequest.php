<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'salt' => ['required', 'string', 'base64'],
            'kdf_algo' => ['required', Rule::in(['pbkdf2-sha256'])],
            'kdf_iterations' => ['required', 'integer', 'min:600000'],
            'auth_hash' => ['required', 'string', 'base64'],
            'wrapped_vault_key' => ['required', 'string', 'base64'],
            'vault_key_iv' => ['required', 'string', 'base64'],
        ];
    }
}

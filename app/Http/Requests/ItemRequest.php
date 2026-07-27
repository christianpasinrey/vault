<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $rules = [
            'ciphertext' => ['required', 'string', 'base64', 'max:1048576'],
            'iv' => ['required', 'string', 'base64'],
        ];

        if ($this->isMethod('POST')) {
            $rules['id'] = ['required', 'uuid'];
        }

        if ($this->isMethod('PUT')) {
            $rules['version'] = ['required', 'integer', 'min:1'];
        }

        return $rules;
    }
}

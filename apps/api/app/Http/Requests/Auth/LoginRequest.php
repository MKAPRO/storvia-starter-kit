<?php

namespace App\Http\Requests\Auth;

use App\Support\Auth\UsernameNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The `email` request key remains stable for API v1 compatibility, but it
     * now accepts either an email address or a username as the login identifier.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $identifier = trim((string) $this->input('email'));
            $this->merge([
                'email' => filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false
                    ? Str::lower($identifier)
                    : UsernameNormalizer::normalize($identifier),
            ]);
        }
    }
}

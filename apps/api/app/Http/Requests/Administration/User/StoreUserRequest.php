<?php

namespace App\Http\Requests\Administration\User;

use App\Support\Auth\UsernameNormalizer;
use App\Support\Localization\StorviaLocale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:64',
                'regex:/\A[\p{L}\p{N}][\p{L}\p{N}._-]*\z/u',
                'unique:users,username',
            ],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => [
                'required',
                'confirmed',
                Password::min(12)->mixedCase()->numbers()->symbols(),
            ],
            'locale' => ['sometimes', 'string', Rule::in(StorviaLocale::SUPPORTED)],
            'personal_space_enabled' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('name')) {
            $merge['name'] = trim((string) $this->input('name'));
        }

        if ($this->has('username')) {
            $merge['username'] = UsernameNormalizer::normalize((string) $this->input('username'));
        }

        if ($this->has('email')) {
            $merge['email'] = Str::lower(trim((string) $this->input('email')));
        }

        if ($this->has('locale')) {
            $merge['locale'] = Str::lower(trim((string) $this->input('locale')));
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}

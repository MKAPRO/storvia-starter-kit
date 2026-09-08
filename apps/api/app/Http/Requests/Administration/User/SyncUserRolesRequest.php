<?php

namespace App\Http\Requests\Administration\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class SyncUserRolesRequest extends FormRequest
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
            'roles' => ['required', 'array', 'size:1'],
            'roles.*' => ['required', 'string', 'distinct', Rule::exists('roles', 'name')],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! is_array($this->input('roles'))) {
            return;
        }

        $this->merge([
            'roles' => array_values(array_map(
                static fn ($role): string => Str::lower(trim((string) $role)),
                $this->input('roles'),
            )),
        ]);
    }
}

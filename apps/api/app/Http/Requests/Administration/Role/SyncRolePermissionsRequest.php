<?php

namespace App\Http\Requests\Administration\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class SyncRolePermissionsRequest extends FormRequest
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
            'permissions' => ['present', 'array', 'max:250'],
            'permissions.*' => [
                'required',
                'string',
                'max:100',
                'distinct',
                Rule::exists('permissions', 'name'),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! is_array($this->input('permissions'))) {
            return;
        }

        $this->merge([
            'permissions' => array_values(array_map(
                static fn ($permission): string => Str::lower(trim((string) $permission)),
                $this->input('permissions'),
            )),
        ]);
    }
}

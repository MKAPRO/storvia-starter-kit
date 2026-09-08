<?php

namespace App\Http\Requests\Administration\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexUsersRequest extends FormRequest
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
            'q' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'disabled'])],
            'role' => ['sometimes', 'nullable', 'string', Rule::exists('roles', 'name')],
            'department' => ['sometimes', 'nullable', 'uuid', Rule::exists('departments', 'uuid')],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('q')) {
            $merge['q'] = trim((string) $this->input('q'));
        }

        if ($this->has('status')) {
            $merge['status'] = strtolower(trim((string) $this->input('status')));
        }

        if ($this->has('role')) {
            $merge['role'] = strtolower(trim((string) $this->input('role')));
        }

        if ($this->has('department')) {
            $merge['department'] = trim((string) $this->input('department'));
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}

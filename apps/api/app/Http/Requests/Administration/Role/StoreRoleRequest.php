<?php

namespace App\Http\Requests\Administration\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

final class StoreRoleRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'min:2',
                'max:64',
                'regex:/\A[a-z][a-z0-9_]*\z/',
                'unique:roles,name',
            ],
            'label' => ['required', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('name')) {
            $merge['name'] = Str::lower(trim((string) $this->input('name')));
        }

        if ($this->has('label')) {
            $merge['label'] = trim((string) $this->input('label'));
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}

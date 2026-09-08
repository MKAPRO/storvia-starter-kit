<?php

namespace App\Http\Requests\Administration\Role;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateRoleRequest extends FormRequest
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
        /** @var Role $role */
        $role = $this->route('role');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'min:2',
                'max:64',
                'regex:/\A[a-z][a-z0-9_]*\z/',
                Rule::unique('roles', 'name')->ignore($role->getKey()),
            ],
            'label' => ['sometimes', 'required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $role = $this->route('role');

                if (! $role instanceof Role || ! $role->is_system || ! $this->has('name')) {
                    return;
                }

                if ($this->string('name')->toString() !== $role->name) {
                    $validator->errors()->add(
                        'name',
                        'The name of a system role cannot be changed.',
                    );
                }
            },
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

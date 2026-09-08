<?php

namespace App\Http\Requests\Administration\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SyncUserDepartmentsRequest extends FormRequest
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
            'departments' => ['present', 'array', 'max:250'],
            'departments.*' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists('departments', 'uuid'),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! is_array($this->input('departments'))) {
            return;
        }

        $this->merge([
            'departments' => array_values(array_map(
                static fn ($uuid): string => trim((string) $uuid),
                $this->input('departments'),
            )),
        ]);
    }
}

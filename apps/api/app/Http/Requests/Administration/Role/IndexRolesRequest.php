<?php

namespace App\Http\Requests\Administration\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexRolesRequest extends FormRequest
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
            'type' => ['sometimes', 'nullable', Rule::in(['system', 'custom'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('q')) {
            $merge['q'] = trim((string) $this->input('q'));
        }

        if ($this->has('type')) {
            $merge['type'] = strtolower(trim((string) $this->input('type')));
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}

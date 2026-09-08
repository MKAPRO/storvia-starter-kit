<?php

namespace App\Http\Requests\Administration\StorageQuota;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class ListStorageQuotasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('system.manage');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['nullable', Rule::in(['personal', 'department'])],
            'department_id' => ['nullable', 'uuid', 'exists:departments,uuid'],
            'department_scope' => [
                'nullable',
                Rule::in(['self', 'descendants', 'self_and_descendants']),
            ],
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

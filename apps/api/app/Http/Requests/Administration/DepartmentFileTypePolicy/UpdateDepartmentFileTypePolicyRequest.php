<?php

namespace App\Http\Requests\Administration\DepartmentFileTypePolicy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class UpdateDepartmentFileTypePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('system.manage');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'disabled_file_type_ids' => ['required', 'array', 'max:250'],
            'disabled_file_type_ids.*' => [
                'required',
                'uuid',
                'distinct',
                'exists:file_types,uuid',
            ],
        ];
    }
}

<?php

namespace App\Http\Requests\Administration\DepartmentFileTypePolicy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class ShowDepartmentFileTypePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('system.manage');
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [];
    }
}

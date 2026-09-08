<?php

namespace App\Http\Requests\Organization;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;

final class SyncDepartmentMembersRequest extends FormRequest
{
    public function authorize(): bool
    {
        $department = $this->route('department');

        return $department instanceof Department
            && ($this->user()?->can('manageMembers', $department) ?? false);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'user_ids' => ['present', 'array'],
            'user_ids.*' => ['required', 'uuid', 'distinct', 'exists:users,uuid'],
        ];
    }
}

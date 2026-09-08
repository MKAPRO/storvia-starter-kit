<?php

namespace App\Http\Requests\Administration\UserFileTypePolicy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class ShowUserFileTypePolicyRequest extends FormRequest
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

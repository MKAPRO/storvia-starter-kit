<?php

namespace App\Http\Requests\FileManager;

use App\Models\NodeAccessPolicy;
use Illuminate\Validation\Rule;

final class UpdateNodeAccessPolicyRequest extends ManageNodeAccessPolicyRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'visibility' => [
                'required',
                'string',
                Rule::in(NodeAccessPolicy::VISIBILITIES),
            ],
        ];
    }
}

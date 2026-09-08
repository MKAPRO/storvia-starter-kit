<?php

namespace App\Http\Requests\FileManager;

final class SetNodeAccessPasswordRequest extends ManageNodeAccessPolicyRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'password' => [
                'required',
                'string',
                'min:8',
                'max:128',
                'confirmed',
            ],
        ];
    }
}

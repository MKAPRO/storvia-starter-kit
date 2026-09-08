<?php

namespace App\Http\Requests\FileManager;

final class StoreNodeAccessGrantRequest extends ManageNodeAccessPolicyRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'recipient_id' => ['required', 'uuid'],
        ];
    }
}

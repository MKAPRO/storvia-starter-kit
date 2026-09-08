<?php

namespace App\Http\Requests\FileManager;

final class BrowseNodeAccessRecipientsRequest extends ManageNodeAccessPolicyRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}

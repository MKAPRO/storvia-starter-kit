<?php

namespace App\Http\Requests\Setup;

use App\Services\Setup\InitialSetupService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSetupPreferencesRequest extends FormRequest
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
            'default_locale' => [
                'required',
                'string',
                Rule::in(InitialSetupService::SUPPORTED_LOCALES),
            ],
            'storage_disk' => [
                'required',
                'string',
                Rule::in(InitialSetupService::SUPPORTED_STORAGE_DISKS),
            ],
        ];
    }
}

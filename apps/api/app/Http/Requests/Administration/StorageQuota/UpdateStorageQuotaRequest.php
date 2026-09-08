<?php

namespace App\Http\Requests\Administration\StorageQuota;

use App\Support\FileManager\StorageQuotaSnapshot;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class UpdateStorageQuotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('system.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'limit_bytes' => [
                'present',
                'nullable',
                static function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === null) {
                        return;
                    }

                    if (! is_int($value)) {
                        $fail("The {$attribute} field must be a JSON integer or null.");

                        return;
                    }

                    if ($value < 0 || $value > StorageQuotaSnapshot::MAX_BYTES) {
                        $fail("The {$attribute} field is outside the supported storage quota range.");
                    }
                },
            ],
        ];
    }
}

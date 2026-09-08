<?php

namespace App\Http\Requests\Auth;

use App\Support\Localization\StorviaLocale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class UpdateLocaleRequest extends FormRequest
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
            'locale' => [
                'required',
                'string',
                Rule::in(StorviaLocale::SUPPORTED),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('locale')) {
            $this->merge([
                'locale' => Str::lower(trim((string) $this->input('locale'))),
            ]);
        }
    }
}

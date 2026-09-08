<?php

namespace App\Http\Requests\Administration\FileType;

use App\Models\FileType;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class StoreFileTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('system.manage');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'extension' => [
                'required',
                'string',
                'max:32',
                'regex:/\A[a-z0-9][a-z0-9+-]{0,31}\z/',
                'unique:file_types,extension',
            ],
            'label' => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::in(FileType::CATEGORIES)],
            'mime_types' => ['required', 'array', 'min:1', 'max:8'],
            'mime_types.*' => [
                'required',
                'string',
                'max:191',
                'distinct',
                static function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || ! FileType::isValidMimeType($value)) {
                        $fail("The {$attribute} field must be a concrete MIME type without wildcards.");
                    }
                },
            ],
            'is_enabled' => ['required', 'boolean'],
            'preview_mode' => ['required', Rule::in(FileType::PREVIEW_MODES)],
            'icon_svg' => ['nullable', 'string', 'max:16384'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $prepared = [];
        $extension = $this->input('extension');
        $label = $this->input('label');
        $category = $this->input('category');
        $previewMode = $this->input('preview_mode');
        $mimeTypes = $this->input('mime_types');

        if (is_string($extension)) {
            $prepared['extension'] = strtolower(ltrim(trim($extension), '.'));
        }

        if (is_string($label)) {
            $prepared['label'] = trim($label);
        }

        if (is_string($category)) {
            $prepared['category'] = strtolower(trim($category));
        }

        if (is_string($previewMode)) {
            $prepared['preview_mode'] = strtolower(trim($previewMode));
        }

        if (is_array($mimeTypes)) {
            $prepared['mime_types'] = array_map(
                static fn (mixed $mimeType): mixed => is_string($mimeType)
                    ? strtolower(trim($mimeType))
                    : $mimeType,
                $mimeTypes,
            );
        }

        if ($prepared !== []) {
            $this->merge($prepared);
        }
    }
}

<?php

namespace App\Http\Requests\Administration\FileType;

use App\Models\FileType;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class UpdateFileTypeRequest extends FormRequest
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
                'sometimes',
                'required',
                'string',
                'max:32',
                'regex:/\A[a-z0-9][a-z0-9+-]{0,31}\z/',
                Rule::unique('file_types', 'extension')->ignore($this->route('fileType')),
            ],
            'label' => ['sometimes', 'required', 'string', 'max:120'],
            'category' => ['sometimes', 'required', Rule::in(FileType::CATEGORIES)],
            'mime_types' => ['sometimes', 'required', 'array', 'min:1', 'max:8'],
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
            'is_enabled' => ['sometimes', 'required', 'boolean'],
            'preview_mode' => ['sometimes', 'required', Rule::in(FileType::PREVIEW_MODES)],
            'icon_svg' => ['sometimes', 'nullable', 'string', 'max:16384'],
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

        if ($this->has('extension') && is_string($extension)) {
            $prepared['extension'] = strtolower(ltrim(trim($extension), '.'));
        }

        if ($this->has('label') && is_string($label)) {
            $prepared['label'] = trim($label);
        }

        if ($this->has('category') && is_string($category)) {
            $prepared['category'] = strtolower(trim($category));
        }

        if ($this->has('preview_mode') && is_string($previewMode)) {
            $prepared['preview_mode'] = strtolower(trim($previewMode));
        }

        if ($this->has('mime_types') && is_array($mimeTypes)) {
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

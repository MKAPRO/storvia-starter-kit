<?php

namespace App\Http\Requests\FileManager;

use App\Models\FileSpace;
use App\Models\User;
use App\Services\FileManager\FileManagerRouteResolver;
use Illuminate\Foundation\Http\FormRequest;

final class ListTrashRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        if (! $actor instanceof User) {
            return false;
        }

        $fileSpace = app(FileManagerRouteResolver::class)
            ->visibleSpaceOrFail($actor, (string) $this->route('fileSpace'));

        $this->attributes->set('file_space', $fileSpace);

        return $actor->can('view', $fileSpace);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'cursor' => ['nullable', 'string', 'max:4096'],
        ];
    }

    public function fileSpace(): FileSpace
    {
        /** @var FileSpace $fileSpace */
        $fileSpace = $this->attributes->get('file_space');

        return $fileSpace;
    }
}

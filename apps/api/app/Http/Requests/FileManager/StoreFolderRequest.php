<?php

namespace App\Http\Requests\FileManager;

use App\Models\FileSpace;
use App\Models\User;
use App\Services\FileManager\FileManagerRouteResolver;
use App\Services\FileManager\FileSpaceAccessService;
use Illuminate\Foundation\Http\FormRequest;

final class StoreFolderRequest extends FormRequest
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

        $access = app(FileSpaceAccessService::class);

        if (! $access->canCreateFolder($actor, $fileSpace)) {
            return false;
        }

        return blank($this->input('parent_id'))
            || $access->canViewFoldersInSpace($actor, $fileSpace);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'uuid'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge([
                'name' => trim((string) $this->input('name')),
            ]);
        }
    }

    public function fileSpace(): FileSpace
    {
        /** @var FileSpace $fileSpace */
        $fileSpace = $this->attributes->get('file_space');

        return $fileSpace;
    }
}

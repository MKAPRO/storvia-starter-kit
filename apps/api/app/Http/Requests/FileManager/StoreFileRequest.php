<?php

namespace App\Http\Requests\FileManager;

use App\Models\FileSpace;
use App\Models\User;
use App\Services\FileManager\FileManagerRouteResolver;
use App\Services\FileManager\FileSpaceAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use LogicException;

final class StoreFileRequest extends FormRequest
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

        if (! $access->canUploadFile($actor, $fileSpace)) {
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
            'file' => [
                'required',
                'file',
                'max:'.$this->maxUploadKilobytes(),
            ],
            'parent_id' => ['nullable', 'uuid'],
        ];
    }

    public function fileSpace(): FileSpace
    {
        /** @var FileSpace $fileSpace */
        $fileSpace = $this->attributes->get('file_space');

        return $fileSpace;
    }

    public function uploadedFile(): UploadedFile
    {
        $file = $this->file('file');

        if (! $file instanceof UploadedFile) {
            throw new LogicException('Validated upload file is unavailable.');
        }

        return $file;
    }

    public function safeFileName(): string
    {
        $uploadedFile = $this->uploadedFile();
        $originalPath = trim($uploadedFile->getClientOriginalPath());

        if (
            $originalPath === ''
            || preg_match('/[\x00-\x1F\x7F]/', $originalPath) === 1
            || str_contains($originalPath, '/')
            || str_contains($originalPath, '\\')
        ) {
            $this->throwUnsafeFileName();
        }

        $original = trim($uploadedFile->getClientOriginalName());

        if (
            $original === ''
            || preg_match('/[\x00-\x1F\x7F]/', $original) === 1
            || str_contains($original, '/')
            || str_contains($original, '\\')
        ) {
            $this->throwUnsafeFileName();
        }

        $name = trim($original);

        if ($name === '' || $name === '.' || $name === '..' || mb_strlen($name) > 255) {
            $this->throwUnsafeFileName();
        }

        $extension = pathinfo($name, PATHINFO_EXTENSION);

        if ($extension !== '' && strlen($extension) > 32) {
            throw ValidationException::withMessages([
                'file' => ['The file name extension may not be greater than 32 characters.'],
            ]);
        }

        return $name;
    }

    private function maxUploadKilobytes(): int
    {
        return max(1, (int) config('file-manager.upload.max_kilobytes', 102400));
    }

    private function throwUnsafeFileName(): never
    {
        throw ValidationException::withMessages([
            'file' => ['The uploaded file name is invalid.'],
        ]);
    }
}

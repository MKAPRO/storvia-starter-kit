<?php

namespace App\Services\FileManager;

use App\Models\DepartmentFileTypePolicy;
use App\Models\FileSpace;
use App\Models\FileType;
use App\Models\Node;
use App\Models\User;
use App\Models\UserFileTypePolicy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

final class FileTypeRegistryService
{
    public function __construct(
        private readonly SvgIconSanitizer $svg,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): FileType
    {
        return FileType::query()->create($this->normalizeAttributes($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(FileType $fileType, array $attributes): FileType
    {
        $fileType->fill($this->normalizeAttributes($attributes));
        $fileType->save();

        return $fileType->refresh();
    }

    /**
     * @return Collection<int, FileType>
     */
    public function effectiveTypesFor(FileSpace $fileSpace, User $actor): Collection
    {
        $query = FileType::query()
            ->where('is_enabled', true)
            ->orderBy('category')
            ->orderBy('extension');

        if ($fileSpace->isDepartment() && $fileSpace->department_id !== null) {
            $departmentId = (int) $fileSpace->department_id;

            $query->whereDoesntHave(
                'departmentPolicies',
                fn ($policy) => $policy
                    ->where('department_id', $departmentId)
                    ->where('is_allowed', false),
            );
        }

        $actorId = (int) $actor->getKey();

        $query->whereDoesntHave(
            'userPolicies',
            fn ($policy) => $policy
                ->where('user_id', $actorId)
                ->where('is_allowed', false),
        );

        return $query->get();
    }

    /**
     * @return list<array{
     *     id: string,
     *     extension: string,
     *     label: string,
     *     category: string,
     *     preview_mode: string,
     *     icon_svg: string|null,
     *     upload_allowed: bool
     * }>
     */
    public function descriptorsForSpace(FileSpace $fileSpace, User $actor): array
    {
        $blockedTypeIds = [];

        if ($fileSpace->isDepartment() && $fileSpace->department_id !== null) {
            $blockedTypeIds = DepartmentFileTypePolicy::query()
                ->where('department_id', $fileSpace->department_id)
                ->where('is_allowed', false)
                ->pluck('file_type_id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        $userBlockedTypeIds = UserFileTypePolicy::query()
            ->where('user_id', $actor->getKey())
            ->where('is_allowed', false)
            ->pluck('file_type_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return FileType::query()
            ->orderBy('category')
            ->orderBy('extension')
            ->get()
            ->map(static fn (FileType $type): array => [
                'id' => (string) $type->uuid,
                'extension' => (string) $type->extension,
                'label' => (string) $type->label,
                'category' => (string) $type->category,
                'preview_mode' => (string) $type->preview_mode,
                'icon_svg' => $type->icon_svg,
                'upload_allowed' => (bool) $type->is_enabled
                    && ! in_array((int) $type->getKey(), $blockedTypeIds, true)
                    && ! in_array((int) $type->getKey(), $userBlockedTypeIds, true),
            ])
            ->values()
            ->all();
    }

    public function assertUploadNameAllowed(FileSpace $fileSpace, User $actor, string $name): void
    {
        $extension = $this->extensionFromName($name, 'file');
        $fileType = FileType::query()
            ->where('extension', $extension)
            ->where('is_enabled', true)
            ->first();

        if (
            ! $fileType instanceof FileType
            || $this->departmentBlocks($fileSpace, $fileType)
            || $this->userBlocks($actor, $fileType)
        ) {
            $this->rejectUpload('This file type is not allowed for the selected user and file space.');
        }
    }

    public function assertStoredUploadAllowed(
        FileSpace $fileSpace,
        User $actor,
        string $name,
        StoredFileObject $stored,
    ): void {
        $extension = $this->extensionFromName($name, 'file');
        $storedExtension = FileType::normalizeExtension($stored->extension);

        if ($storedExtension === null || $storedExtension !== $extension) {
            $this->rejectUpload('The uploaded file extension is inconsistent with its stored metadata.');
        }

        $fileType = FileType::query()
            ->where('extension', $extension)
            ->lockForUpdate()
            ->first();

        if (! $fileType instanceof FileType || ! $fileType->is_enabled) {
            $this->rejectUpload('This file type is disabled or unknown.');
        }

        $detectedMime = strtolower(trim($stored->mimeType));
        $allowedMimes = FileType::normalizeMimeTypes($fileType->mime_types);

        if (! in_array($detectedMime, $allowedMimes, true)) {
            $this->rejectUpload('The uploaded file content does not match the registered file type.');
        }

        if ($this->departmentBlocks($fileSpace, $fileType, lock: true)) {
            $this->rejectUpload('This file type is disabled for the selected department.');
        }

        if ($this->userBlocks($actor, $fileType, lock: true)) {
            $this->rejectUpload('This file type is disabled for the uploading user.');
        }
    }

    public function assertRenamePreservesExtension(Node $node, string $targetName): void
    {
        if (! $node->isFile()) {
            return;
        }

        $persisted = FileType::normalizeExtension($node->extension);
        $currentName = $this->nullableExtensionFromName((string) $node->name);
        $target = $this->nullableExtensionFromName($targetName);

        $current = $persisted;

        if (
            $persisted === null
            || ! FileType::query()->where('extension', $persisted)->exists()
        ) {
            $current = $currentName;
        }

        if ($current !== $target) {
            throw ValidationException::withMessages([
                'name' => ['A file rename cannot change its extension.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalizeAttributes(array $attributes): array
    {
        $normalized = $attributes;

        if (array_key_exists('extension', $normalized)) {
            $extension = FileType::normalizeExtension((string) $normalized['extension']);

            if ($extension === null) {
                throw ValidationException::withMessages([
                    'extension' => ['The extension format is invalid.'],
                ]);
            }

            $normalized['extension'] = $extension;
        }

        if (array_key_exists('label', $normalized)) {
            $normalized['label'] = trim((string) $normalized['label']);
        }

        if (array_key_exists('category', $normalized)) {
            $normalized['category'] = strtolower(trim((string) $normalized['category']));
        }

        if (array_key_exists('preview_mode', $normalized)) {
            $normalized['preview_mode'] = strtolower(trim((string) $normalized['preview_mode']));
        }

        if (array_key_exists('mime_types', $normalized)) {
            $normalized['mime_types'] = FileType::normalizeMimeTypes($normalized['mime_types']);
        }

        if (array_key_exists('icon_svg', $normalized)) {
            try {
                $normalized['icon_svg'] = $this->svg->sanitize(
                    is_string($normalized['icon_svg']) ? $normalized['icon_svg'] : null,
                );
            } catch (\InvalidArgumentException $exception) {
                throw ValidationException::withMessages([
                    'icon_svg' => [$exception->getMessage()],
                ]);
            }
        }

        return $normalized;
    }

    private function departmentBlocks(
        FileSpace $fileSpace,
        FileType $fileType,
        bool $lock = false,
    ): bool {
        if (! $fileSpace->isDepartment() || $fileSpace->department_id === null) {
            return false;
        }

        $query = DepartmentFileTypePolicy::query()
            ->where('department_id', $fileSpace->department_id)
            ->where('file_type_id', $fileType->getKey());

        if ($lock) {
            $query->lockForUpdate();
        }

        $policy = $query->first();

        return $policy instanceof DepartmentFileTypePolicy && ! $policy->is_allowed;
    }

    private function userBlocks(
        User $actor,
        FileType $fileType,
        bool $lock = false,
    ): bool {
        $query = UserFileTypePolicy::query()
            ->where('user_id', $actor->getKey())
            ->where('file_type_id', $fileType->getKey());

        if ($lock) {
            $query->lockForUpdate();
        }

        $policy = $query->first();

        return $policy instanceof UserFileTypePolicy && ! $policy->is_allowed;
    }

    private function extensionFromName(string $name, string $field): string
    {
        $extension = $this->nullableExtensionFromName($name);

        if ($extension === null) {
            throw ValidationException::withMessages([
                $field => ['The uploaded file must have a supported filename extension.'],
            ]);
        }

        return $extension;
    }

    private function nullableExtensionFromName(string $name): ?string
    {
        $extension = pathinfo(trim($name), PATHINFO_EXTENSION);

        if (! is_string($extension) || $extension === '') {
            return null;
        }

        return FileType::normalizeExtension($extension);
    }

    private function rejectUpload(string $message): never
    {
        throw ValidationException::withMessages([
            'file' => [$message],
        ]);
    }
}

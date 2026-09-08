<?php

namespace App\Services\Administration;

use App\Models\FileType;
use App\Models\User;
use App\Models\UserFileTypePolicy;
use Illuminate\Support\Facades\DB;

final class UserFileTypePolicyService
{
    /**
     * @param  list<string>  $disabledFileTypeUuids
     */
    public function replaceDisabled(User $user, array $disabledFileTypeUuids): int
    {
        $fileTypeIds = FileType::query()
            ->whereIn('uuid', $disabledFileTypeUuids)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        UserFileTypePolicy::query()
            ->where('user_id', $user->getKey())
            ->delete();

        if ($fileTypeIds === []) {
            return 0;
        }

        $now = now();

        DB::table('user_file_type_policies')->insert(array_map(
            static fn (int $fileTypeId): array => [
                'user_id' => $user->getKey(),
                'file_type_id' => $fileTypeId,
                'is_allowed' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $fileTypeIds,
        ));

        return count($fileTypeIds);
    }

    /**
     * @return array{
     *     user: array{id:string,name:string},
     *     disabled_file_type_ids: list<string>,
     *     file_types: list<array<string,mixed>>
     * }
     */
    public function snapshot(User $user): array
    {
        $types = FileType::query()
            ->orderBy('category')
            ->orderBy('extension')
            ->get();
        $policies = UserFileTypePolicy::query()
            ->where('user_id', $user->getKey())
            ->where('is_allowed', false)
            ->get(['file_type_id'])
            ->keyBy('file_type_id');

        return [
            'user' => [
                'id' => (string) $user->uuid,
                'name' => (string) $user->name,
            ],
            'disabled_file_type_ids' => $types
                ->filter(fn (FileType $type): bool => $policies->has($type->getKey()))
                ->pluck('uuid')
                ->values()
                ->all(),
            'file_types' => $types
                ->map(fn (FileType $type): array => [
                    'id' => (string) $type->uuid,
                    'extension' => (string) $type->extension,
                    'label' => (string) $type->label,
                    'category' => (string) $type->category,
                    'mime_types' => $type->mime_types,
                    'is_enabled' => (bool) $type->is_enabled,
                    'preview_mode' => (string) $type->preview_mode,
                    'icon_svg' => $type->icon_svg,
                    'user_allowed' => ! $policies->has($type->getKey()),
                    'effective_allowed' => (bool) $type->is_enabled
                        && ! $policies->has($type->getKey()),
                ])
                ->values()
                ->all(),
        ];
    }
}

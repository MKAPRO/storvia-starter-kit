<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\FileSpace;
use App\Models\User;
use App\Support\Audit\AuditAction;
use App\Support\Audit\AuditTargetType;
use LogicException;

final class AuditLogRecorder
{
    private const MAX_METADATA_BYTES = 8192;

    private const MAX_LIST_ITEMS = 250;

    private const MAX_STRING_LENGTH = 512;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        User $actor,
        string $action,
        string $targetType,
        ?string $targetUuid,
        ?string $targetLabel,
        ?FileSpace $fileSpace = null,
        ?Department $department = null,
        array $metadata = [],
    ): AuditLog {
        if (! in_array($targetType, AuditTargetType::all(), true)) {
            throw new LogicException("Unknown audit target type [{$targetType}].");
        }

        $normalizedMetadata = $this->normalizeMetadata($action, $metadata);
        $departmentUuid = $department?->uuid;

        if ($departmentUuid === null && $fileSpace?->department_id !== null) {
            $departmentUuid = $fileSpace->department()->value('uuid');
        }

        $auditLog = new AuditLog;
        $auditLog->forceFill([
            'actor_user_id' => $actor->getKey(),
            'actor_user_uuid' => (string) $actor->uuid,
            'actor_display_name' => $this->boundedString((string) $actor->name, 255),
            'action' => $action,
            'category' => AuditAction::categoryFor($action),
            'target_type' => $targetType,
            'target_uuid' => $targetUuid,
            'target_label' => $targetLabel === null
                ? null
                : $this->boundedString($targetLabel, 255),
            'file_space_uuid' => $fileSpace?->uuid,
            'department_uuid' => $departmentUuid,
            'metadata' => $normalizedMetadata === [] ? null : $normalizedMetadata,
        ]);
        $auditLog->save();

        return $auditLog;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function normalizeMetadata(string $action, array $metadata): array
    {
        $allowedKeys = AuditAction::allowedMetadataKeys($action);

        foreach (array_keys($metadata) as $key) {
            if (! is_string($key) || ! in_array($key, $allowedKeys, true)) {
                throw new LogicException("Audit metadata key [{$key}] is not allowed for [{$action}].");
            }
        }

        $normalized = [];

        foreach ($metadata as $key => $value) {
            $normalized[$key] = $this->normalizeValue($value);
        }

        $encoded = json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        if (strlen($encoded) > self::MAX_METADATA_BYTES) {
            throw new LogicException('Audit metadata exceeds the supported size.');
        }

        return $normalized;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value === null || is_bool($value) || is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            if (mb_strlen($value) > self::MAX_STRING_LENGTH) {
                throw new LogicException('Audit metadata string exceeds the supported length.');
            }

            return $value;
        }

        if (! is_array($value) || ! array_is_list($value)) {
            throw new LogicException('Audit metadata values must be bounded scalars or lists.');
        }

        if (count($value) > self::MAX_LIST_ITEMS) {
            throw new LogicException('Audit metadata list exceeds the supported size.');
        }

        return array_map(function (mixed $item): mixed {
            if (is_array($item)) {
                throw new LogicException('Nested audit metadata lists are not supported.');
            }

            return $this->normalizeValue($item);
        }, $value);
    }

    private function boundedString(string $value, int $maxLength): string
    {
        return mb_substr($value, 0, $maxLength);
    }
}

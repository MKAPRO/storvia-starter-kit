<?php

namespace App\Services\FileManager;

use App\Exceptions\StorageQuotaExceededException;
use App\Models\Department;
use App\Models\FileSpace;
use App\Support\FileManager\StorageQuotaSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;

final class StorageQuotaService
{
    public function snapshot(FileSpace $fileSpace): StorageQuotaSnapshot
    {
        return StorageQuotaSnapshot::fromFileSpace($fileSpace);
    }

    public function assertCanConsume(FileSpace $fileSpace, int $incomingBytes): void
    {
        $snapshot = $this->snapshot($fileSpace);
        $this->nextUsedBytesOrFail($snapshot, $incomingBytes);
    }

    /**
     * Apply a quota ledger increment to a FileSpace row already locked by the
     * caller inside the same transaction that persists the corresponding Node.
     */
    public function consumeLocked(FileSpace $fileSpace, int $incomingBytes): StorageQuotaSnapshot
    {
        $snapshot = $this->snapshot($fileSpace);
        $nextUsedBytes = $this->nextUsedBytesOrFail($snapshot, $incomingBytes);

        if ($nextUsedBytes !== $snapshot->usedBytes) {
            $fileSpace->used_bytes = $nextUsedBytes;
            $fileSpace->save();
        }

        return $this->snapshot($fileSpace);
    }

    /**
     * Release bytes for a permanent purge from a FileSpace row already locked
     * by the caller in the same transaction that deletes the corresponding Nodes.
     */
    public function releaseLocked(FileSpace $fileSpace, int $releasedBytes): StorageQuotaSnapshot
    {
        if ($releasedBytes < 0 || $releasedBytes > StorageQuotaSnapshot::MAX_BYTES) {
            throw new LogicException('Released storage bytes are outside the supported integer domain.');
        }

        $snapshot = $this->snapshot($fileSpace);

        if ($releasedBytes > $snapshot->usedBytes) {
            throw new LogicException('Permanent purge release exceeds the FileSpace quota ledger.');
        }

        $nextUsedBytes = $snapshot->usedBytes - $releasedBytes;

        if ($nextUsedBytes !== $snapshot->usedBytes) {
            $fileSpace->used_bytes = $nextUsedBytes;
            $fileSpace->save();
        }

        return $this->snapshot($fileSpace);
    }

    public function setLimit(FileSpace $fileSpace, ?int $limitBytes): StorageQuotaSnapshot
    {
        if (
            $limitBytes !== null
            && ($limitBytes < 0 || $limitBytes > StorageQuotaSnapshot::MAX_BYTES)
        ) {
            throw new InvalidArgumentException('Storage quota limit is outside the supported integer domain.');
        }

        return DB::transaction(function () use ($fileSpace, $limitBytes): StorageQuotaSnapshot {
            $lockedSpace = $fileSpace->isDepartment()
                ? $this->lockAndValidateDepartmentLimit($fileSpace, $limitBytes)
                : FileSpace::query()
                    ->whereKey($fileSpace->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

            // Deliberately allow a protected administrator to lower the limit
            // below current usage. Existing bytes remain intact and the space
            // simply becomes over-limit until capacity is increased/removed.
            $lockedSpace->limit_bytes = $limitBytes;
            $lockedSpace->save();

            return $this->snapshot($lockedSpace->refresh());
        }, 3);
    }

    public function assertCanReparentDepartment(
        Department $department,
        ?Department $newParent,
    ): void {
        if (! $newParent instanceof Department) {
            return;
        }

        $movedSpace = FileSpace::query()
            ->where('type', FileSpace::TYPE_DEPARTMENT)
            ->where('department_id', $department->getKey())
            ->first();
        $parentSpace = FileSpace::query()
            ->where('type', FileSpace::TYPE_DEPARTMENT)
            ->where('department_id', $newParent->getKey())
            ->first();

        if (! $movedSpace instanceof FileSpace || ! $parentSpace instanceof FileSpace) {
            return;
        }

        /** @var list<int> $siblingDepartmentIds */
        $siblingDepartmentIds = Department::query()
            ->where('parent_id', $newParent->getKey())
            ->where('id', '!=', $department->getKey())
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $spaces = FileSpace::query()
            ->whereIn('department_id', array_merge(
                [(int) $department->getKey(), (int) $newParent->getKey()],
                $siblingDepartmentIds,
            ))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('department_id');

        /** @var FileSpace $lockedParent */
        $lockedParent = $spaces->get((int) $newParent->getKey(), $parentSpace);
        if ($lockedParent->limit_bytes === null) {
            return;
        }

        $allocated = (int) $lockedParent->used_bytes;
        foreach ($siblingDepartmentIds as $siblingDepartmentId) {
            $siblingLimit = $spaces->get($siblingDepartmentId)?->limit_bytes;
            if ($siblingLimit === null) {
                $this->throwHierarchyValidation(
                    'An unlimited department cannot be placed beneath a finite administration.',
                );
            }
            $allocated = $this->safeAllocationSum($allocated, (int) $siblingLimit);
        }

        $movedLimit = $spaces->get((int) $department->getKey(), $movedSpace)->limit_bytes;
        if ($movedLimit === null) {
            $this->throwHierarchyValidation(
                'An unlimited department cannot be placed beneath a finite administration.',
            );
        }

        $allocated = $this->safeAllocationSum($allocated, (int) $movedLimit);
        if ($allocated > (int) $lockedParent->limit_bytes) {
            $this->throwHierarchyValidation(
                'The destination administration does not have enough unallocated storage.',
            );
        }
    }

    private function lockAndValidateDepartmentLimit(
        FileSpace $fileSpace,
        ?int $limitBytes,
    ): FileSpace {
        /** @var Department $department */
        $department = Department::query()
            ->whereKey($fileSpace->department_id)
            ->lockForUpdate()
            ->firstOrFail();

        $childIds = Department::query()
            ->where('parent_id', $department->getKey())
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
        $siblingIds = $department->parent_id === null
            ? []
            : Department::query()
                ->where('parent_id', $department->parent_id)
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

        $departmentIds = array_values(array_unique(array_merge(
            [(int) $department->getKey()],
            $department->parent_id === null ? [] : [(int) $department->parent_id],
            $childIds,
            $siblingIds,
        )));

        $spaces = FileSpace::query()
            ->where('type', FileSpace::TYPE_DEPARTMENT)
            ->whereIn('department_id', $departmentIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('department_id');

        /** @var FileSpace $lockedSpace */
        $lockedSpace = $spaces->get((int) $department->getKey())
            ?? throw new LogicException('The department FileSpace disappeared during quota allocation.');

        $this->assertOwnDepartmentBudget($lockedSpace, $limitBytes, $childIds, $spaces);

        if ($department->parent_id !== null) {
            $this->assertParentDepartmentBudget(
                (int) $department->parent_id,
                (int) $department->getKey(),
                $limitBytes,
                $siblingIds,
                $spaces,
            );
        }

        return $lockedSpace;
    }

    private function assertOwnDepartmentBudget(
        FileSpace $space,
        ?int $proposedLimit,
        array $childIds,
        $spaces,
    ): void {
        if ($proposedLimit === null || $childIds === []) {
            return;
        }

        $required = (int) $space->used_bytes;
        foreach ($childIds as $childId) {
            $childLimit = $spaces->get($childId)?->limit_bytes;
            if ($childLimit === null) {
                $this->throwHierarchyValidation(
                    'A finite department cannot contain an unlimited direct child.',
                );
            }
            $required = $this->safeAllocationSum($required, (int) $childLimit);
        }

        if ($required > $proposedLimit) {
            $this->throwHierarchyValidation(
                'The quota is below the department direct usage and child allocations.',
            );
        }
    }

    private function assertParentDepartmentBudget(
        int $parentDepartmentId,
        int $targetDepartmentId,
        ?int $proposedLimit,
        array $siblingIds,
        $spaces,
    ): void {
        $parent = $spaces->get($parentDepartmentId);
        if (! $parent instanceof FileSpace || $parent->limit_bytes === null) {
            return;
        }

        if ($proposedLimit === null) {
            $this->throwHierarchyValidation(
                'An unlimited department cannot be placed beneath a finite administration.',
            );
        }

        $currentTargetLimit = $spaces->get($targetDepartmentId)?->limit_bytes;
        $repairingLegacyUnlimitedChild = $currentTargetLimit === null && $proposedLimit !== null;

        $required = (int) $parent->used_bytes;
        foreach ($siblingIds as $siblingId) {
            $childSpace = $spaces->get($siblingId);
            $childLimit = $siblingId === $targetDepartmentId
                ? $proposedLimit
                : $childSpace?->limit_bytes;

            if ($childLimit === null) {
                if (! $repairingLegacyUnlimitedChild || ! $childSpace instanceof FileSpace) {
                    $this->throwHierarchyValidation(
                        'A finite administration cannot contain an unlimited direct child.',
                    );
                }

                // A pre-POST-28B database may already contain a finite parent
                // with multiple unlimited children. Permit only a monotonic
                // repair (the target changes from unlimited to finite), while
                // reserving every unresolved sibling's current usage so the
                // repair cannot over-allocate the finite parent.
                $required = $this->safeAllocationSum($required, (int) $childSpace->used_bytes);

                continue;
            }

            $required = $this->safeAllocationSum($required, (int) $childLimit);
        }

        if ($required > (int) $parent->limit_bytes) {
            $this->throwHierarchyValidation(
                'The parent administration does not have enough unallocated storage.',
            );
        }
    }

    private function safeAllocationSum(int $current, int $amount): int
    {
        if ($amount > StorageQuotaSnapshot::MAX_BYTES - $current) {
            $this->throwHierarchyValidation('The storage allocation exceeds the supported range.');
        }

        return $current + $amount;
    }

    private function throwHierarchyValidation(string $message): never
    {
        throw ValidationException::withMessages(['limit_bytes' => [$message]]);
    }

    private function nextUsedBytesOrFail(
        StorageQuotaSnapshot $snapshot,
        int $incomingBytes,
    ): int {
        if ($incomingBytes < 0 || $incomingBytes > StorageQuotaSnapshot::MAX_BYTES) {
            throw new LogicException('Incoming storage bytes are outside the supported integer domain.');
        }

        if ($incomingBytes > StorageQuotaSnapshot::MAX_BYTES - $snapshot->usedBytes) {
            $this->throwExceeded($snapshot, $incomingBytes);
        }

        if (
            $snapshot->limitBytes !== null
            && $incomingBytes > $snapshot->remainingBytes()
        ) {
            $this->throwExceeded($snapshot, $incomingBytes);
        }

        return $snapshot->usedBytes + $incomingBytes;
    }

    private function throwExceeded(
        StorageQuotaSnapshot $snapshot,
        int $incomingBytes,
    ): never {
        throw new StorageQuotaExceededException(
            usedBytes: $snapshot->usedBytes,
            limitBytes: $snapshot->limitBytes,
            remainingBytes: $snapshot->remainingBytes(),
            requiredBytes: $incomingBytes,
        );
    }
}

<?php

namespace App\Services\FileManager;

use App\Models\FileSpace;
use App\Support\FileManager\StorageQuotaSnapshot;
use Illuminate\Support\Facades\DB;
use LogicException;

final class StorageQuotaReconciler
{
    /**
     * @return array{file_space_uuid:string,ledger_bytes:int,actual_bytes:int,drift_bytes:int,repaired:bool}
     */
    public function inspect(FileSpace $fileSpace, bool $repair = false): array
    {
        return DB::transaction(function () use ($fileSpace, $repair): array {
            /** @var FileSpace $lockedSpace */
            $lockedSpace = FileSpace::query()
                ->whereKey($fileSpace->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $actualBytes = $this->databaseAggregate((int) $lockedSpace->getKey());
            $ledgerBytes = (int) $lockedSpace->used_bytes;
            $driftBytes = $actualBytes - $ledgerBytes;
            $repaired = false;

            if ($repair && $driftBytes !== 0) {
                $lockedSpace->used_bytes = $actualBytes;
                $lockedSpace->save();
                $repaired = true;
            }

            return [
                'file_space_uuid' => (string) $lockedSpace->uuid,
                'ledger_bytes' => $ledgerBytes,
                'actual_bytes' => $actualBytes,
                'drift_bytes' => $driftBytes,
                'repaired' => $repaired,
            ];
        }, 3);
    }

    private function databaseAggregate(int $fileSpaceId): int
    {
        $row = DB::table('nodes')
            ->where('file_space_id', $fileSpaceId)
            ->where('type', 'file')
            // Intentionally no trashed_at predicate: Trash remains quota-accounted.
            ->selectRaw('COALESCE(SUM(size), 0) AS aggregate')
            ->first();

        return $this->normalizeExactAggregate($row->aggregate ?? 0);
    }

    private function normalizeExactAggregate(mixed $value): int
    {
        if (is_int($value)) {
            if ($value < 0 || $value > StorageQuotaSnapshot::MAX_BYTES) {
                throw new LogicException('Storage quota reconciliation aggregate is outside the supported integer domain.');
            }

            return $value;
        }

        if (! is_string($value) || preg_match('/\A[0-9]+\z/', $value) !== 1) {
            throw new LogicException('Storage quota reconciliation requires an exact database aggregate.');
        }

        $normalized = ltrim($value, '0');
        $normalized = $normalized === '' ? '0' : $normalized;
        $maximum = (string) StorageQuotaSnapshot::MAX_BYTES;

        if (
            strlen($normalized) > strlen($maximum)
            || (strlen($normalized) === strlen($maximum) && strcmp($normalized, $maximum) > 0)
        ) {
            throw new LogicException('Storage quota reconciliation aggregate exceeds the supported integer domain.');
        }

        return (int) $normalized;
    }
}

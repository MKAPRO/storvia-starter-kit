<?php

namespace App\Console\Commands;

use App\Models\FileSpace;
use App\Services\FileManager\StorageQuotaReconciler;
use Illuminate\Console\Command;

final class ReconcileStorageQuotas extends Command
{
    protected $signature = 'storvia:storage-quota-reconcile
        {--repair : Replace drifted used_bytes ledgers with the database-derived aggregate}';

    protected $description = 'Check or repair STORVIA FileSpace storage quota ledger drift';

    public function handle(StorageQuotaReconciler $reconciler): int
    {
        $repair = (bool) $this->option('repair');
        $driftCount = 0;
        $repairCount = 0;

        FileSpace::query()
            ->select(['id', 'uuid'])
            ->orderBy('id')
            ->chunkById(100, function ($spaces) use (
                $reconciler,
                $repair,
                &$driftCount,
                &$repairCount,
            ): void {
                foreach ($spaces as $space) {
                    $result = $reconciler->inspect($space, $repair);

                    if ($result['drift_bytes'] === 0) {
                        continue;
                    }

                    $driftCount++;
                    if ($result['repaired']) {
                        $repairCount++;
                    }

                    $this->line(sprintf(
                        '%s ledger=%d actual=%d drift=%+d%s',
                        $result['file_space_uuid'],
                        $result['ledger_bytes'],
                        $result['actual_bytes'],
                        $result['drift_bytes'],
                        $result['repaired'] ? ' repaired' : '',
                    ));
                }
            }, 'id');

        if ($driftCount === 0) {
            $this->components->info('Storage quota ledgers are consistent.');

            return self::SUCCESS;
        }

        if ($repair) {
            $this->components->info("Repaired {$repairCount} storage quota ledger(s).");

            return $repairCount === $driftCount ? self::SUCCESS : self::FAILURE;
        }

        $this->components->error(
            "Detected {$driftCount} storage quota ledger drift(s). Run again with --repair to fix them explicitly.",
        );

        return self::FAILURE;
    }
}

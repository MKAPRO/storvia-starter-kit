<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\FileSpace;
use App\Services\FileManager\FileSpaceProvisioner;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

final class ReconcileDepartmentFileSpaces extends Command
{
    private const CHUNK_SIZE = 100;

    protected $signature = 'storvia:department-file-spaces-reconcile
        {--repair : Create missing department FileSpaces with zero-byte limits}';

    protected $description = 'Check or repair missing department FileSpaces outside File Manager HTTP request hot paths';

    public function handle(FileSpaceProvisioner $provisioner): int
    {
        $repair = (bool) $this->option('repair');
        $missingCount = 0;
        $repairCount = 0;

        $this->missingDepartmentQuery()
            ->chunkById(
                self::CHUNK_SIZE,
                function ($departments) use ($provisioner, $repair, &$missingCount, &$repairCount): void {
                    foreach ($departments as $department) {
                        $missingCount++;

                        if (! $repair) {
                            continue;
                        }

                        $fileSpace = $provisioner->departmentFor($department);

                        if (! $fileSpace->wasRecentlyCreated) {
                            continue;
                        }

                        $fileSpace->limit_bytes = 0;
                        $fileSpace->save();
                        $repairCount++;
                    }
                },
                'departments.id',
                'id',
            );

        if ($missingCount === 0) {
            $this->components->info('Department FileSpaces are consistent.');

            return self::SUCCESS;
        }

        if (! $repair) {
            $this->components->error(
                "Detected {$missingCount} department(s) without a FileSpace. Run again with --repair to fix them explicitly.",
            );

            return self::FAILURE;
        }

        if ($this->missingDepartmentQuery()->exists()) {
            $this->components->error('Some department FileSpaces are still missing after the repair attempt.');

            return self::FAILURE;
        }

        $this->components->info("Repaired {$repairCount} missing department FileSpace(s).");

        return self::SUCCESS;
    }

    /**
     * @return Builder<Department>
     */
    private function missingDepartmentQuery(): Builder
    {
        return Department::query()
            ->select('departments.id')
            ->whereDoesntHave(
                'fileSpaces',
                fn ($query) => $query->where('type', FileSpace::TYPE_DEPARTMENT),
            )
            ->orderBy('departments.id');
    }
}

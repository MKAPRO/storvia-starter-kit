<?php

use App\Support\FileManager\StorageQuotaSnapshot;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! app()->environment('testing') && ! app()->isDownForMaintenance()) {
            throw new RuntimeException(
                'STAGE 23B quota backfill must run while Laravel is in maintenance mode.',
            );
        }

        // Validate historical aggregates before changing the schema. The runner
        // executes this migration under Laravel maintenance mode so this
        // preflight and the subsequent backfill see a stable upload boundary.
        $aggregates = $this->historicalAggregates();

        Schema::table('file_spaces', function (Blueprint $table): void {
            $table->unsignedBigInteger('used_bytes')
                ->default(0)
                ->after('department_id');
            $table->unsignedBigInteger('limit_bytes')
                ->nullable()
                ->after('used_bytes');
        });

        foreach ($aggregates as $fileSpaceId => $usedBytes) {
            DB::table('file_spaces')
                ->where('id', $fileSpaceId)
                ->update(['used_bytes' => $usedBytes]);
        }
    }

    public function down(): void
    {
        Schema::table('file_spaces', function (Blueprint $table): void {
            $table->dropColumn(['used_bytes', 'limit_bytes']);
        });
    }

    /**
     * @return array<int, string>
     */
    private function historicalAggregates(): array
    {
        $aggregates = [];

        DB::table('file_spaces')
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($spaces) use (&$aggregates): void {
                foreach ($spaces as $space) {
                    $row = DB::table('nodes')
                        ->where('file_space_id', $space->id)
                        ->where('type', 'file')
                        // Intentionally no trashed_at predicate: Trash keeps
                        // its physical bytes and remains quota-accounted.
                        ->selectRaw('COALESCE(SUM(size), 0) AS aggregate')
                        ->first();

                    $usedBytes = $this->normalizeUnsignedDecimal($row->aggregate ?? 0);

                    if ($this->compareUnsignedDecimal(
                        $usedBytes,
                        (string) StorageQuotaSnapshot::MAX_BYTES,
                    ) > 0) {
                        throw new RuntimeException(
                            "STAGE 23B cannot backfill FileSpace {$space->id}: used bytes exceed the supported exact integer domain.",
                        );
                    }

                    $aggregates[(int) $space->id] = $usedBytes;
                }
            }, 'id');

        return $aggregates;
    }

    private function normalizeUnsignedDecimal(mixed $value): string
    {
        if (is_int($value)) {
            if ($value < 0) {
                throw new RuntimeException('STAGE 23B encountered a negative historical storage aggregate.');
            }

            return (string) $value;
        }

        if (! is_string($value) || preg_match('/\\A[0-9]+\\z/', $value) !== 1) {
            throw new RuntimeException('STAGE 23B could not read an exact historical storage aggregate.');
        }

        $normalized = ltrim($value, '0');

        return $normalized === '' ? '0' : $normalized;
    }

    private function compareUnsignedDecimal(string $left, string $right): int
    {
        $lengthComparison = strlen($left) <=> strlen($right);

        if ($lengthComparison !== 0) {
            return $lengthComparison;
        }

        return strcmp($left, $right) <=> 0;
    }
};

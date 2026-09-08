<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const NODE_FILE_STORAGE_CHECK = 'nodes_file_storage_chk';

    private const NODE_STORAGE_IDENTITY_UNIQUE = 'nodes_storage_identity_unique';

    public function up(): void
    {
        $this->assertExistingRowsMatchContract();

        match (DB::connection()->getDriverName()) {
            'sqlite' => $this->addSqliteGuards(),
            'mysql', 'mariadb' => $this->addMysqlFamilyCheck(),
            default => throw new RuntimeException(
                'STORVIA storage metadata hardening currently supports MariaDB/MySQL and SQLite only.',
            ),
        };

        $this->addStorageIdentityUniqueIndex();
    }

    public function down(): void
    {
        $this->dropStorageIdentityUniqueIndex();

        match (DB::connection()->getDriverName()) {
            'sqlite' => $this->dropSqliteGuards(),
            'mysql', 'mariadb' => $this->dropMysqlFamilyCheck(),
            default => null,
        };
    }

    private function assertExistingRowsMatchContract(): void
    {
        $invalidFile = DB::table('nodes')
            ->where('type', 'file')
            ->where(function ($query): void {
                $query
                    ->whereNull('storage_disk')
                    ->orWhereRaw("TRIM(storage_disk) = ''")
                    ->orWhereNull('storage_key')
                    ->orWhereRaw("TRIM(storage_key) = ''")
                    ->orWhere('storage_key', 'not like', 'objects/%')
                    ->orWhere('storage_key', 'like', '%..%')
                    ->orWhereNull('mime_type')
                    ->orWhereRaw("TRIM(mime_type) = ''")
                    ->orWhereNull('size')
                    ->orWhere('size', '<', 0)
                    ->orWhereNull('checksum')
                    ->orWhereRaw('LENGTH(checksum) <> 64');
            })
            ->exists();

        if ($invalidFile) {
            throw new RuntimeException(
                'STAGE 12C cannot harden nodes because existing file rows have incomplete or unsafe storage metadata.',
            );
        }

        $duplicateIdentity = DB::table('nodes')
            ->where('type', 'file')
            ->select(['storage_disk', 'storage_key'])
            ->groupBy(['storage_disk', 'storage_key'])
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicateIdentity) {
            throw new RuntimeException(
                'STAGE 12C cannot harden nodes because multiple file nodes reference the same storage identity.',
            );
        }
    }

    private function addMysqlFamilyCheck(): void
    {
        if ($this->mysqlFamilyConstraintExists(self::NODE_FILE_STORAGE_CHECK)) {
            return;
        }

        DB::statement(sprintf(
            "ALTER TABLE `nodes` ADD CONSTRAINT `%s` CHECK ((`type` <> 'file') OR ((`storage_disk` IS NOT NULL) AND (LENGTH(TRIM(`storage_disk`)) > 0) AND (`storage_key` IS NOT NULL) AND (LENGTH(TRIM(`storage_key`)) > 0) AND (`storage_key` LIKE 'objects/%%') AND (`storage_key` NOT LIKE '%%..%%') AND (`mime_type` IS NOT NULL) AND (LENGTH(TRIM(`mime_type`)) > 0) AND (`size` IS NOT NULL) AND (`size` >= 0) AND (`checksum` IS NOT NULL) AND (LENGTH(`checksum`) = 64)))",
            self::NODE_FILE_STORAGE_CHECK,
        ));
    }

    private function dropMysqlFamilyCheck(): void
    {
        if (! $this->mysqlFamilyConstraintExists(self::NODE_FILE_STORAGE_CHECK)) {
            return;
        }

        $driver = DB::connection()->getDriverName();
        $version = strtolower((string) (DB::selectOne('SELECT VERSION() AS version')->version ?? ''));
        $isMariaDb = $driver === 'mariadb' || str_contains($version, 'mariadb');
        $dropKeyword = $isMariaDb ? 'CONSTRAINT' : 'CHECK';

        DB::statement(sprintf(
            'ALTER TABLE `nodes` DROP %s `%s`',
            $dropKeyword,
            self::NODE_FILE_STORAGE_CHECK,
        ));
    }

    private function mysqlFamilyConstraintExists(string $constraint): bool
    {
        $result = DB::selectOne(
            'SELECT COUNT(*) AS aggregate FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            ['nodes', $constraint],
        );

        return (int) ($result->aggregate ?? 0) > 0;
    }

    private function addSqliteGuards(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TRIGGER IF NOT EXISTS storvia_nodes_file_storage_insert
BEFORE INSERT ON nodes
FOR EACH ROW
WHEN NEW.type = 'file' AND NOT (
    NEW.storage_disk IS NOT NULL
    AND LENGTH(TRIM(NEW.storage_disk)) > 0
    AND NEW.storage_key IS NOT NULL
    AND LENGTH(TRIM(NEW.storage_key)) > 0
    AND NEW.storage_key LIKE 'objects/%'
    AND NEW.storage_key NOT LIKE '%..%'
    AND NEW.mime_type IS NOT NULL
    AND LENGTH(TRIM(NEW.mime_type)) > 0
    AND NEW.size IS NOT NULL
    AND NEW.size >= 0
    AND NEW.checksum IS NOT NULL
    AND LENGTH(NEW.checksum) = 64
)
BEGIN
    SELECT RAISE(ABORT, 'STORVIA file storage metadata contract violation');
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER IF NOT EXISTS storvia_nodes_file_storage_update
BEFORE UPDATE ON nodes
FOR EACH ROW
WHEN NEW.type = 'file' AND NOT (
    NEW.storage_disk IS NOT NULL
    AND LENGTH(TRIM(NEW.storage_disk)) > 0
    AND NEW.storage_key IS NOT NULL
    AND LENGTH(TRIM(NEW.storage_key)) > 0
    AND NEW.storage_key LIKE 'objects/%'
    AND NEW.storage_key NOT LIKE '%..%'
    AND NEW.mime_type IS NOT NULL
    AND LENGTH(TRIM(NEW.mime_type)) > 0
    AND NEW.size IS NOT NULL
    AND NEW.size >= 0
    AND NEW.checksum IS NOT NULL
    AND LENGTH(NEW.checksum) = 64
)
BEGIN
    SELECT RAISE(ABORT, 'STORVIA file storage metadata contract violation');
END
SQL);
    }

    private function dropSqliteGuards(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS storvia_nodes_file_storage_update');
        DB::unprepared('DROP TRIGGER IF EXISTS storvia_nodes_file_storage_insert');
    }

    private function addStorageIdentityUniqueIndex(): void
    {
        if ($this->storageIdentityIndexExists()) {
            return;
        }

        DB::statement(
            'CREATE UNIQUE INDEX '.self::NODE_STORAGE_IDENTITY_UNIQUE.' ON nodes (storage_disk, storage_key)',
        );
    }

    private function dropStorageIdentityUniqueIndex(): void
    {
        if (! $this->storageIdentityIndexExists()) {
            return;
        }

        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX '.self::NODE_STORAGE_IDENTITY_UNIQUE);

            return;
        }

        DB::statement('DROP INDEX '.self::NODE_STORAGE_IDENTITY_UNIQUE.' ON nodes');
    }

    private function storageIdentityIndexExists(): bool
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('nodes')");

            foreach ($indexes as $index) {
                if (($index->name ?? null) === self::NODE_STORAGE_IDENTITY_UNIQUE) {
                    return true;
                }
            }

            return false;
        }

        $result = DB::selectOne(
            'SELECT COUNT(*) AS aggregate FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            ['nodes', self::NODE_STORAGE_IDENTITY_UNIQUE],
        );

        return (int) ($result->aggregate ?? 0) > 0;
    }
};

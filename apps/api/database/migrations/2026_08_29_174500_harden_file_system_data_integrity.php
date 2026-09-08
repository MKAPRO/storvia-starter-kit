<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const FILE_SPACE_TYPE_CHECK = 'file_spaces_type_chk';

    private const FILE_SPACE_TARGET_CHECK = 'file_spaces_target_chk';

    private const NODE_TYPE_CHECK = 'nodes_type_chk';

    private const NODE_FOLDER_STORAGE_CHECK = 'nodes_folder_storage_chk';

    public function up(): void
    {
        $this->assertExistingRowsMatchContract();

        match (DB::connection()->getDriverName()) {
            'sqlite' => $this->addSqliteGuards(),
            'mysql', 'mariadb' => $this->addMysqlFamilyChecks(),
            default => throw new RuntimeException(
                'STORVIA file-system schema hardening currently supports MariaDB/MySQL and SQLite only.',
            ),
        };
    }

    public function down(): void
    {
        match (DB::connection()->getDriverName()) {
            'sqlite' => $this->dropSqliteGuards(),
            'mysql', 'mariadb' => $this->dropMysqlFamilyChecks(),
            default => null,
        };
    }

    private function assertExistingRowsMatchContract(): void
    {
        $invalidFileSpace = DB::table('file_spaces')
            ->where(function ($query): void {
                $query
                    ->whereNotIn('type', ['personal', 'department'])
                    ->orWhereRaw(
                        'NOT ((type = ? AND owner_user_id IS NOT NULL AND department_id IS NULL) OR (type = ? AND owner_user_id IS NULL AND department_id IS NOT NULL))',
                        ['personal', 'department'],
                    );
            })
            ->exists();

        if ($invalidFileSpace) {
            throw new RuntimeException(
                'STAGE 11B cannot harden file_spaces because existing rows violate the STAGE 11A namespace contract.',
            );
        }

        $invalidNode = DB::table('nodes')
            ->where(function ($query): void {
                $query
                    ->whereNotIn('type', ['folder', 'file'])
                    ->orWhere(function ($query): void {
                        $query
                            ->where('type', 'folder')
                            ->where(function ($query): void {
                                $query
                                    ->whereNotNull('storage_disk')
                                    ->orWhereNotNull('storage_key')
                                    ->orWhereNotNull('mime_type')
                                    ->orWhereNotNull('extension')
                                    ->orWhereNotNull('size')
                                    ->orWhereNotNull('checksum');
                            });
                    });
            })
            ->exists();

        if ($invalidNode) {
            throw new RuntimeException(
                'STAGE 11B cannot harden nodes because existing rows violate the STAGE 11A node/storage contract.',
            );
        }
    }

    private function addMysqlFamilyChecks(): void
    {
        if (! $this->mysqlFamilyConstraintExists('file_spaces', self::FILE_SPACE_TYPE_CHECK)) {
            DB::statement(sprintf(
                "ALTER TABLE `file_spaces` ADD CONSTRAINT `%s` CHECK (`type` IN ('personal', 'department'))",
                self::FILE_SPACE_TYPE_CHECK,
            ));
        }

        if (! $this->mysqlFamilyConstraintExists('file_spaces', self::FILE_SPACE_TARGET_CHECK)) {
            DB::statement(sprintf(
                "ALTER TABLE `file_spaces` ADD CONSTRAINT `%s` CHECK (((`type` = 'personal') AND (`owner_user_id` IS NOT NULL) AND (`department_id` IS NULL)) OR ((`type` = 'department') AND (`owner_user_id` IS NULL) AND (`department_id` IS NOT NULL)))",
                self::FILE_SPACE_TARGET_CHECK,
            ));
        }

        if (! $this->mysqlFamilyConstraintExists('nodes', self::NODE_TYPE_CHECK)) {
            DB::statement(sprintf(
                "ALTER TABLE `nodes` ADD CONSTRAINT `%s` CHECK (`type` IN ('folder', 'file'))",
                self::NODE_TYPE_CHECK,
            ));
        }

        if (! $this->mysqlFamilyConstraintExists('nodes', self::NODE_FOLDER_STORAGE_CHECK)) {
            DB::statement(sprintf(
                "ALTER TABLE `nodes` ADD CONSTRAINT `%s` CHECK ((`type` <> 'folder') OR ((`storage_disk` IS NULL) AND (`storage_key` IS NULL) AND (`mime_type` IS NULL) AND (`extension` IS NULL) AND (`size` IS NULL) AND (`checksum` IS NULL)))",
                self::NODE_FOLDER_STORAGE_CHECK,
            ));
        }
    }

    private function dropMysqlFamilyChecks(): void
    {
        $driver = DB::connection()->getDriverName();
        $version = strtolower((string) (DB::selectOne('SELECT VERSION() AS version')->version ?? ''));
        $isMariaDb = $driver === 'mariadb' || str_contains($version, 'mariadb');
        $dropKeyword = $isMariaDb ? 'CONSTRAINT' : 'CHECK';

        foreach ([
            ['nodes', self::NODE_FOLDER_STORAGE_CHECK],
            ['nodes', self::NODE_TYPE_CHECK],
            ['file_spaces', self::FILE_SPACE_TARGET_CHECK],
            ['file_spaces', self::FILE_SPACE_TYPE_CHECK],
        ] as [$table, $constraint]) {
            if (! $this->mysqlFamilyConstraintExists($table, $constraint)) {
                continue;
            }

            DB::statement(sprintf(
                'ALTER TABLE `%s` DROP %s `%s`',
                $table,
                $dropKeyword,
                $constraint,
            ));
        }
    }

    private function mysqlFamilyConstraintExists(string $table, string $constraint): bool
    {
        $result = DB::selectOne(
            'SELECT COUNT(*) AS aggregate FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            [$table, $constraint],
        );

        return (int) ($result->aggregate ?? 0) > 0;
    }

    private function addSqliteGuards(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TRIGGER IF NOT EXISTS storvia_file_spaces_contract_insert
BEFORE INSERT ON file_spaces
FOR EACH ROW
WHEN NOT (
    NEW.type IN ('personal', 'department')
    AND (
        (NEW.type = 'personal' AND NEW.owner_user_id IS NOT NULL AND NEW.department_id IS NULL)
        OR
        (NEW.type = 'department' AND NEW.owner_user_id IS NULL AND NEW.department_id IS NOT NULL)
    )
)
BEGIN
    SELECT RAISE(ABORT, 'STORVIA file_spaces data contract violation');
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER IF NOT EXISTS storvia_file_spaces_contract_update
BEFORE UPDATE ON file_spaces
FOR EACH ROW
WHEN NOT (
    NEW.type IN ('personal', 'department')
    AND (
        (NEW.type = 'personal' AND NEW.owner_user_id IS NOT NULL AND NEW.department_id IS NULL)
        OR
        (NEW.type = 'department' AND NEW.owner_user_id IS NULL AND NEW.department_id IS NOT NULL)
    )
)
BEGIN
    SELECT RAISE(ABORT, 'STORVIA file_spaces data contract violation');
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER IF NOT EXISTS storvia_nodes_contract_insert
BEFORE INSERT ON nodes
FOR EACH ROW
WHEN NOT (
    NEW.type IN ('folder', 'file')
    AND (
        NEW.type <> 'folder'
        OR (
            NEW.storage_disk IS NULL
            AND NEW.storage_key IS NULL
            AND NEW.mime_type IS NULL
            AND NEW.extension IS NULL
            AND NEW.size IS NULL
            AND NEW.checksum IS NULL
        )
    )
)
BEGIN
    SELECT RAISE(ABORT, 'STORVIA nodes data contract violation');
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER IF NOT EXISTS storvia_nodes_contract_update
BEFORE UPDATE ON nodes
FOR EACH ROW
WHEN NOT (
    NEW.type IN ('folder', 'file')
    AND (
        NEW.type <> 'folder'
        OR (
            NEW.storage_disk IS NULL
            AND NEW.storage_key IS NULL
            AND NEW.mime_type IS NULL
            AND NEW.extension IS NULL
            AND NEW.size IS NULL
            AND NEW.checksum IS NULL
        )
    )
)
BEGIN
    SELECT RAISE(ABORT, 'STORVIA nodes data contract violation');
END
SQL);
    }

    private function dropSqliteGuards(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS storvia_nodes_contract_update');
        DB::unprepared('DROP TRIGGER IF EXISTS storvia_nodes_contract_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS storvia_file_spaces_contract_update');
        DB::unprepared('DROP TRIGGER IF EXISTS storvia_file_spaces_contract_insert');
    }
};

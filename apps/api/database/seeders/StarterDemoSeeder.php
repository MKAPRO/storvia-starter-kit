<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\InstallationSetting;
use App\Models\Node;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessControl\AccessControlProvisioner;
use App\Services\FileManager\FileSpaceProvisioner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use LogicException;

final class StarterDemoSeeder extends Seeder
{
    public const ADMIN_EMAIL = 'demo.admin@storvia.test';

    public const ADMIN_USERNAME = 'demo.admin';

    public function run(
        AccessControlProvisioner $accessControl,
        FileSpaceProvisioner $fileSpaces,
    ): void {
        if (app()->environment('production')) {
            throw new LogicException('Starter demo seeding is disabled in production.');
        }

        $password = (string) config('storvia.starter_demo.admin_password', '');

        $validator = Validator::make(
            ['password' => $password],
            ['password' => ['required', Password::min(12)->mixedCase()->numbers()->symbols()]],
        );

        if ($validator->fails()) {
            throw new LogicException(
                'Set STORVIA_STARTER_DEMO_PASSWORD to a strong non-default password before demo seeding.',
            );
        }

        DB::transaction(function () use ($accessControl, $fileSpaces, $password): void {
            $accessControl->syncCatalog();

            $superAdminRole = Role::query()
                ->where('name', Role::SUPER_ADMIN)
                ->firstOrFail();

            $admin = User::query()->updateOrCreate(
                ['email' => self::ADMIN_EMAIL],
                [
                    'name' => 'STORVIA Demo Admin',
                    'username' => self::ADMIN_USERNAME,
                    'password' => Hash::make($password),
                    'locale' => 'en',
                    'is_active' => true,
                    'personal_space_enabled' => true,
                ],
            );

            // The one-role contract remains authoritative in the demo dataset.
            $admin->roles()->sync([$superAdminRole->getKey()]);

            $operations = Department::query()->firstOrCreate([
                'name' => 'Operations',
                'parent_id' => null,
            ], [
                'is_active' => true,
            ]);

            $finance = Department::query()->firstOrCreate([
                'name' => 'Finance',
                'parent_id' => null,
            ], [
                'is_active' => true,
            ]);

            // Keep membership valid under the one-root membership contract.
            $admin->departments()->sync([$operations->getKey()]);

            $personalSpace = $fileSpaces->personalFor($admin);
            $operationsSpace = $fileSpaces->departmentFor($operations);
            $financeSpace = $fileSpaces->departmentFor($finance);

            $this->folder($personalSpace->getKey(), $admin->getKey(), 'Getting Started');
            $this->folder($operationsSpace->getKey(), $admin->getKey(), 'Policies');
            $this->folder($operationsSpace->getKey(), $admin->getKey(), 'Reports');
            $this->folder($financeSpace->getKey(), $admin->getKey(), 'Budgets');

            InstallationSetting::query()->updateOrCreate(
                ['key' => InstallationSetting::PRIMARY_KEY],
                [
                    'company_name' => 'STORVIA Demo',
                    'default_locale' => 'en',
                    'storage_disk' => 'local',
                    'completed_at' => now(),
                    'completed_by' => $admin->getKey(),
                ],
            );
        });
    }

    private function folder(int $fileSpaceId, int $ownerId, string $name): void
    {
        Node::query()->firstOrCreate(
            [
                'file_space_id' => $fileSpaceId,
                'parent_id' => null,
                'type' => Node::TYPE_FOLDER,
                'name' => $name,
            ],
            [
                'owner_id' => $ownerId,
            ],
        );
    }
}

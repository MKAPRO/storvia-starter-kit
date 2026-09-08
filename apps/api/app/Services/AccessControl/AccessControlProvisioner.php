<?php

namespace App\Services\AccessControl;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class AccessControlProvisioner
{
    public function syncCatalog(): void
    {
        /** @var array<string, string> $permissionDefinitions */
        $permissionDefinitions = config('access-control.permissions', []);

        /** @var array<string, array{label: string, system: bool, permissions: array<int, string>|string}> $roleDefinitions */
        $roleDefinitions = config('access-control.roles', []);

        DB::transaction(function () use ($permissionDefinitions, $roleDefinitions): void {
            foreach ($permissionDefinitions as $name => $description) {
                Permission::query()->updateOrCreate(
                    ['name' => $name],
                    ['description' => $description],
                );
            }

            foreach ($roleDefinitions as $name => $definition) {
                $role = Role::query()->updateOrCreate(
                    ['name' => $name],
                    [
                        'label' => $definition['label'],
                        'is_system' => $definition['system'],
                    ],
                );

                $permissionNames = $definition['permissions'] === '*'
                    ? array_keys($permissionDefinitions)
                    : $definition['permissions'];

                $permissionIds = Permission::query()
                    ->whereIn('name', $permissionNames)
                    ->pluck('id')
                    ->all();

                if (count($permissionIds) !== count($permissionNames)) {
                    throw new InvalidArgumentException("Role [{$name}] references an unknown permission.");
                }

                $role->permissions()->sync($permissionIds);
            }
        });
    }
}

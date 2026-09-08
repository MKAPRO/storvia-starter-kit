<?php

namespace App\Providers;

use App\Models\Department;
use App\Models\FileSpace;
use App\Models\Node;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Policies\DepartmentPolicy;
use App\Policies\FileSpacePolicy;
use App\Policies\NodePolicy;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class AuthorizationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::before(function (User $user): ?bool {
            return $user->isActiveSuperAdmin() ? true : null;
        });

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(FileSpace::class, FileSpacePolicy::class);
        Gate::policy(Node::class, NodePolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);

        foreach (array_keys(config('access-control.permissions', [])) as $ability) {
            Gate::define(
                $ability,
                fn (User $user): bool => $user->hasPermission($ability),
            );
        }
    }
}

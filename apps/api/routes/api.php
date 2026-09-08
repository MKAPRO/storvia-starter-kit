<?php

use App\Http\Controllers\Api\V1\Administration\AdminDashboardController;
use App\Http\Controllers\Api\V1\Administration\DepartmentController;
use App\Http\Controllers\Api\V1\Administration\DepartmentFileTypePolicyController;
use App\Http\Controllers\Api\V1\Administration\DepartmentMembershipController;
use App\Http\Controllers\Api\V1\Administration\FileTypeController;
use App\Http\Controllers\Api\V1\Administration\PermissionController;
use App\Http\Controllers\Api\V1\Administration\RoleController;
use App\Http\Controllers\Api\V1\Administration\RolePermissionController;
use App\Http\Controllers\Api\V1\Administration\StorageQuotaController;
use App\Http\Controllers\Api\V1\Administration\UserController;
use App\Http\Controllers\Api\V1\Administration\UserFileTypePolicyController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Dashboard\UserDashboardController;
use App\Http\Controllers\Api\V1\FileManager\FileController;
use App\Http\Controllers\Api\V1\FileManager\FileSpaceController;
use App\Http\Controllers\Api\V1\FileManager\FolderController;
use App\Http\Controllers\Api\V1\FileManager\NodeAccessPolicyController;
use App\Http\Controllers\Api\V1\FileManager\NodeAccessUnlockController;
use App\Http\Controllers\Api\V1\FileManager\NodeController;
use App\Http\Controllers\Api\V1\FileManager\NodeFavoriteController;
use App\Http\Controllers\Api\V1\FileManager\NodeTrashController;
use App\Http\Controllers\Api\V1\FileManager\UploadPolicyController;
use App\Http\Controllers\Api\V1\Setup\SetupController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth')
    ->name('api.v1.auth.')
    ->group(function (): void {
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:login')
            ->name('login');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('/me', [AuthController::class, 'me'])
                ->middleware('active.user')
                ->name('me');

            Route::get('/freshness', [AuthController::class, 'freshness'])
                ->middleware('active.user')
                ->name('freshness');

            Route::put('/locale', [AuthController::class, 'updateLocale'])
                ->middleware('active.user')
                ->name('locale.update');

            // Logout intentionally stays available to an authenticated session
            // even if the account has just been disabled, so the client can
            // clear that session cleanly.
            Route::post('/logout', [AuthController::class, 'logout'])
                ->name('logout');
        });
    });

Route::get('/v1/dashboard', UserDashboardController::class)
    ->middleware(['auth:sanctum', 'active.user'])
    ->name('api.v1.dashboard.show');

Route::prefix('v1/administration')
    ->name('api.v1.administration.')
    ->middleware(['auth:sanctum', 'active.user'])
    ->group(function (): void {
        Route::get('/dashboard', AdminDashboardController::class)
            ->name('dashboard.show');
        Route::get('/file-types', [FileTypeController::class, 'index'])
            ->name('file-types.index');
        Route::post('/file-types', [FileTypeController::class, 'store'])
            ->name('file-types.store');
        Route::patch('/file-types/{fileType}', [FileTypeController::class, 'update'])
            ->name('file-types.update');

        Route::get(
            '/departments/{department}/file-type-policy',
            [DepartmentFileTypePolicyController::class, 'show'],
        )->name('departments.file-type-policy.show');
        Route::put(
            '/departments/{department}/file-type-policy',
            [DepartmentFileTypePolicyController::class, 'update'],
        )->name('departments.file-type-policy.update');

        Route::get('/departments/tree', [DepartmentController::class, 'tree'])
            ->name('departments.tree');

        Route::apiResource('departments', DepartmentController::class)
            ->only(['index', 'show', 'store', 'update', 'destroy']);

        Route::put(
            '/departments/{department}/members',
            [DepartmentMembershipController::class, 'update'],
        )->name('departments.members.update');

        // STORVIA_STAGE07B_USERS
        Route::get('/users', [UserController::class, 'index'])
            ->name('users.index');

        Route::post('/users', [UserController::class, 'store'])
            ->name('users.store');

        Route::get('/users/{user:uuid}', [UserController::class, 'show'])
            ->name('users.show');

        Route::patch('/users/{user:uuid}', [UserController::class, 'update'])
            ->name('users.update');

        Route::put('/users/{user:uuid}/status', [UserController::class, 'status'])
            ->name('users.status.update');

        Route::put('/users/{user:uuid}/password', [UserController::class, 'password'])
            ->name('users.password.update');

        Route::put('/users/{user:uuid}/roles', [UserController::class, 'roles'])
            ->name('users.roles.update');

        Route::put('/users/{user:uuid}/departments', [UserController::class, 'departments'])
            ->name('users.departments.update');

        Route::get(
            '/users/{user:uuid}/file-type-policy',
            [UserFileTypePolicyController::class, 'show'],
        )->name('users.file-type-policy.show');
        Route::put(
            '/users/{user:uuid}/file-type-policy',
            [UserFileTypePolicyController::class, 'update'],
        )->name('users.file-type-policy.update');

        Route::get('/roles', [RoleController::class, 'index'])
            ->name('roles.index');

        Route::post('/roles', [RoleController::class, 'store'])
            ->name('roles.store');

        Route::get('/roles/{role:uuid}', [RoleController::class, 'show'])
            ->name('roles.show');

        Route::patch('/roles/{role:uuid}', [RoleController::class, 'update'])
            ->name('roles.update');

        Route::put('/roles/{role:uuid}/permissions', [RolePermissionController::class, 'update'])
            ->name('roles.permissions.update');

        Route::get('/permissions', [PermissionController::class, 'index'])
            ->name('permissions.index');

        Route::get('/storage-quotas', [StorageQuotaController::class, 'index'])
            ->name('storage-quotas.index');

        Route::get('/storage-quotas/filter-options', [StorageQuotaController::class, 'filterOptions'])
            ->name('storage-quotas.filter-options');

        Route::put('/storage-quotas/{fileSpace}', [StorageQuotaController::class, 'update'])
            ->name('storage-quotas.update');
    });

Route::prefix('v1/file-manager')
    ->name('api.v1.file-manager.')
    ->middleware(['auth:sanctum', 'active.user'])
    ->group(function (): void {
        Route::get('/spaces/{fileSpace}/nodes/{node}/access-policy', [NodeAccessPolicyController::class, 'show'])
            ->name('nodes.access-policy.show');
        Route::get('/spaces/{fileSpace}/nodes/{node}/access-policy/recipients', [NodeAccessPolicyController::class, 'recipients'])
            ->name('nodes.access-policy.recipients');
        Route::put('/spaces/{fileSpace}/nodes/{node}/access-policy', [NodeAccessPolicyController::class, 'update'])
            ->name('nodes.access-policy.update');
        Route::put('/spaces/{fileSpace}/nodes/{node}/access-policy/password', [NodeAccessPolicyController::class, 'setPassword'])
            ->name('nodes.access-policy.password.update');
        Route::delete('/spaces/{fileSpace}/nodes/{node}/access-policy/password', [NodeAccessPolicyController::class, 'removePassword'])
            ->name('nodes.access-policy.password.destroy');
        Route::post('/spaces/{fileSpace}/nodes/{node}/access-policy/grants', [NodeAccessPolicyController::class, 'storeGrant'])
            ->name('nodes.access-policy.grants.store');
        Route::delete('/spaces/{fileSpace}/nodes/{node}/access-policy/grants/{grant}', [NodeAccessPolicyController::class, 'destroyGrant'])
            ->name('nodes.access-policy.grants.destroy');
        Route::post('/spaces/{fileSpace}/nodes/{node}/unlock', [NodeAccessUnlockController::class, 'normal'])
            ->middleware('throttle:resource-unlock')
            ->name('nodes.unlock');

        Route::get('/upload-policy', [UploadPolicyController::class, 'show'])
            ->name('upload-policy.show');

        Route::get('/spaces/{fileSpace}/upload-policy', [UploadPolicyController::class, 'showForSpace'])
            ->name('spaces.upload-policy.show');

        Route::get('/spaces', [FileSpaceController::class, 'index'])
            ->name('spaces.index');

        Route::get('/spaces/{fileSpace}/nodes', [NodeController::class, 'index'])
            ->name('nodes.index');

        Route::get('/spaces/{fileSpace}/nodes/{node}', [NodeController::class, 'show'])
            ->name('nodes.show');

        Route::post('/spaces/{fileSpace}/folders', [FolderController::class, 'store'])
            ->name('folders.store');

        Route::post('/spaces/{fileSpace}/files', [FileController::class, 'store'])
            ->name('files.store');
        Route::get('/spaces/{fileSpace}/files/{node}/download', [FileController::class, 'download'])
            ->name('files.download');

        Route::patch('/spaces/{fileSpace}/nodes/{node}', [NodeController::class, 'update'])
            ->name('nodes.update');

        Route::get('/spaces/{fileSpace}/trash', [NodeTrashController::class, 'index'])
            ->name('trash.index');

        Route::delete('/spaces/{fileSpace}/trash', [NodeTrashController::class, 'empty'])
            ->name('trash.empty');

        Route::delete('/spaces/{fileSpace}/nodes/{node}', [NodeTrashController::class, 'destroy'])
            ->name('nodes.trash');

        Route::post('/spaces/{fileSpace}/trash/{node}/restore', [NodeTrashController::class, 'restore'])
            ->name('trash.restore');

        Route::get('/spaces/{fileSpace}/favorites', [NodeFavoriteController::class, 'index'])
            ->name('favorites.index');

        Route::put('/spaces/{fileSpace}/nodes/{node}/favorite', [NodeFavoriteController::class, 'store'])
            ->name('nodes.favorite.store');

        Route::delete('/spaces/{fileSpace}/nodes/{node}/favorite', [NodeFavoriteController::class, 'destroy'])
            ->name('nodes.favorite.destroy');
    });

// STORVIA STAGE 05B â€” Initial Setup API Contract
Route::prefix('v1/setup')
    ->name('api.v1.setup.')
    ->group(function (): void {
        Route::get('/status', [SetupController::class, 'status'])
            ->name('status');

        Route::put('/company', [SetupController::class, 'company'])
            ->middleware('setup.token')
            ->name('company');

        Route::put('/preferences', [SetupController::class, 'preferences'])
            ->middleware('setup.token')
            ->name('preferences');

        Route::post('/administrator', [SetupController::class, 'administrator'])
            ->middleware('setup.token')
            ->name('administrator');

        Route::middleware(['auth:sanctum', 'active.user'])->group(function (): void {
            Route::post('/finish', [SetupController::class, 'finish'])
                ->name('finish');
        });
    });
// END STORVIA STAGE 05B

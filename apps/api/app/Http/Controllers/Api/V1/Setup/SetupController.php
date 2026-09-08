<?php

namespace App\Http\Controllers\Api\V1\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\CreateSetupAdministratorRequest;
use App\Http\Requests\Setup\StoreCompanySetupRequest;
use App\Http\Requests\Setup\StoreSetupPreferencesRequest;
use App\Models\Role;
use App\Services\Setup\InitialSetupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SetupController extends Controller
{
    public function status(InitialSetupService $setup): JsonResponse
    {
        return response()->json([
            'data' => $setup->snapshot(),
        ]);
    }

    public function company(
        StoreCompanySetupRequest $request,
        InitialSetupService $setup,
    ): JsonResponse {
        if ($response = $this->completedResponse($setup)) {
            return $response;
        }

        $setup->persistConfiguration($request->validated());

        return response()->json([
            'data' => $setup->snapshot(),
        ]);
    }

    public function preferences(
        StoreSetupPreferencesRequest $request,
        InitialSetupService $setup,
    ): JsonResponse {
        if ($response = $this->completedResponse($setup)) {
            return $response;
        }

        $setup->persistConfiguration($request->validated());

        return response()->json([
            'data' => $setup->snapshot(),
        ]);
    }

    public function administrator(
        CreateSetupAdministratorRequest $request,
        InitialSetupService $setup,
    ): JsonResponse {
        if ($response = $this->completedResponse($setup)) {
            return $response;
        }

        if ($setup->administratorConfigured()) {
            return response()->json([
                'message' => 'A Super Admin has already been configured for this installation.',
                'code' => 'setup_administrator_exists',
            ], 409);
        }

        try {
            $user = $setup->createAdministrator($request->validated());
        } catch (ValidationException $exception) {
            if ($setup->administratorConfigured()) {
                return response()->json([
                    'message' => 'A Super Admin has already been configured for this installation.',
                    'code' => 'setup_administrator_exists',
                ], 409);
            }

            throw $exception;
        }

        return response()->json([
            'data' => [
                'user' => [
                    'uuid' => $user->uuid,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'locale' => $user->locale,
                ],
                'setup' => $setup->snapshot(),
            ],
        ], 201);
    }

    public function finish(Request $request, InitialSetupService $setup): JsonResponse
    {
        if ($response = $this->completedResponse($setup)) {
            return $response;
        }

        $user = $request->user();

        if ($user === null || ! $user->hasRole(Role::SUPER_ADMIN)) {
            return response()->json([
                'message' => 'Only a Super Admin can complete initial setup.',
                'code' => 'setup_super_admin_required',
            ], 403);
        }

        $setup->finish($user);

        return response()->json([
            'data' => $setup->snapshot(),
        ]);
    }

    private function completedResponse(InitialSetupService $setup): ?JsonResponse
    {
        if (! $setup->isCompleted()) {
            return null;
        }

        return response()->json([
            'message' => 'Initial setup has already been completed.',
            'code' => 'setup_completed',
        ], 409);
    }
}

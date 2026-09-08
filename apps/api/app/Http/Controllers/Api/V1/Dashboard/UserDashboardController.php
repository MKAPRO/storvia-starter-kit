<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\Dashboard\UserDashboardResource;
use App\Models\User;
use App\Services\Dashboard\UserDashboardService;
use Illuminate\Http\Request;

final class UserDashboardController extends Controller
{
    public function __invoke(
        Request $request,
        UserDashboardService $dashboard,
    ): UserDashboardResource {
        /** @var User $actor */
        $actor = $request->user();

        return new UserDashboardResource(
            $dashboard->snapshot($actor),
        );
    }
}

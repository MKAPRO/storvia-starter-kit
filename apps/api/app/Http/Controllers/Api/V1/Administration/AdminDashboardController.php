<?php

namespace App\Http\Controllers\Api\V1\Administration;

use App\Http\Controllers\Controller;
use App\Http\Resources\Administration\AdminDashboardResource;
use App\Services\Dashboard\AdminDashboardService;
use Illuminate\Support\Facades\Gate;

final class AdminDashboardController extends Controller
{
    public function __invoke(AdminDashboardService $dashboard): AdminDashboardResource
    {
        Gate::authorize('system.manage');

        return new AdminDashboardResource($dashboard->snapshot());
    }
}

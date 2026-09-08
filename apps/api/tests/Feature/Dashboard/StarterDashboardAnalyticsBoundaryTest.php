<?php

namespace Tests\Feature\Dashboard;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class StarterDashboardAnalyticsBoundaryTest extends TestCase
{
    public function test_starter_omits_removed_dashboard_analytics_route(): void
    {
        $this->assertNull(Route::getRoutes()->getByName('api.v1.dashboard.analytics.show'));
    }
}

<?php

namespace Tests\Feature\Setup;

use App\Models\InstallationSetting;
use App\Models\User;
use App\Services\Setup\InitialSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InitialSetupFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_is_incomplete_when_no_installation_settings_exist(): void
    {
        config()->set('app.locale', 'en');

        $snapshot = app(InitialSetupService::class)->snapshot();

        $this->assertFalse($snapshot['completed']);
        $this->assertFalse($snapshot['company_configured']);
        $this->assertNull($snapshot['company_name']);
        $this->assertSame('en', $snapshot['default_locale']);
        $this->assertSame('local', $snapshot['storage_disk']);
        $this->assertSame(['en', 'ar'], $snapshot['supported_locales']);
        $this->assertSame(['local'], $snapshot['supported_storage_disks']);
    }

    public function test_configuration_is_persisted_into_the_single_installation_record(): void
    {
        $service = app(InitialSetupService::class);

        $first = $service->persistConfiguration([
            'company_name' => 'STORVIA Demo',
            'default_locale' => 'ar',
            'storage_disk' => 'local',
        ]);

        $second = $service->persistConfiguration([
            'company_name' => 'STORVIA Cloud',
        ]);

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertDatabaseCount('installation_settings', 1);
        $this->assertDatabaseHas('installation_settings', [
            'key' => InstallationSetting::PRIMARY_KEY,
            'company_name' => 'STORVIA Cloud',
            'default_locale' => 'ar',
            'storage_disk' => 'local',
        ]);
    }

    public function test_completion_metadata_can_reference_a_user_and_survives_user_deletion(): void
    {
        $user = User::factory()->create();
        $completedAt = Carbon::parse('2026-08-28 18:00:00');

        InstallationSetting::factory()->create([
            'completed_at' => $completedAt,
            'completed_by' => $user->getKey(),
        ]);

        $user->delete();

        $this->assertDatabaseHas('installation_settings', [
            'key' => InstallationSetting::PRIMARY_KEY,
            'completed_by' => null,
        ]);
        $this->assertTrue(app(InitialSetupService::class)->snapshot()['completed']);
    }
}

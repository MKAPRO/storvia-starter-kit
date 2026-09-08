<?php

namespace Tests\Feature\Administration;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogRecorder;
use App\Support\Audit\AuditAction;
use App\Support\Audit\AuditTargetType;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

final class StarterAuditRecorderBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_starter_keeps_append_only_audit_recording_without_public_audit_browser_route(): void
    {
        $this->assertTrue(Schema::hasTable('audit_logs'));
        $this->assertNull(Route::getRoutes()->getByName('api.v1.administration.audit-logs.index'));

        foreach (Route::getRoutes() as $route) {
            $this->assertNotSame('api/v1/administration/audit-logs', $route->uri());
        }

        $actor = $this->userWithRole(Role::SUPER_ADMIN);
        $log = app(AuditLogRecorder::class)->record(
            $actor,
            AuditAction::USER_UPDATED,
            AuditTargetType::USER,
            (string) $actor->uuid,
            (string) $actor->name,
            metadata: ['changed_fields' => ['name']],
        );

        $persisted = AuditLog::query()->findOrFail($log->getKey());
        $this->assertSame(AuditAction::USER_UPDATED, $persisted->action);
        $this->assertSame((string) $actor->uuid, $persisted->actor_user_uuid);

        $this->expectException(LogicException::class);
        $persisted->forceFill(['target_label' => 'mutated'])->save();
    }

    public function test_starter_recorder_rejects_sensitive_metadata(): void
    {
        $actor = $this->userWithRole(Role::SUPER_ADMIN);

        $this->expectException(LogicException::class);

        app(AuditLogRecorder::class)->record(
            $actor,
            AuditAction::USER_UPDATED,
            AuditTargetType::USER,
            (string) $actor->uuid,
            (string) $actor->name,
            metadata: ['password' => 'must-not-persist'],
        );
    }

    private function userWithRole(string $roleName)
    {
        $user = User::factory()->create();
        $user->roles()->sync([
            Role::query()->where('name', $roleName)->firstOrFail()->getKey(),
        ]);

        return $user->refresh();
    }
}

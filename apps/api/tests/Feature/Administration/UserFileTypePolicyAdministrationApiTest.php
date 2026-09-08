<?php

namespace Tests\Feature\Administration;

use App\Models\AuditLog;
use App\Models\FileType;
use App\Models\Role;
use App\Models\User;
use App\Models\UserFileTypePolicy;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UserFileTypePolicyAdministrationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_admin_can_toggle_personal_space_through_existing_user_update_authority(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $target = $this->userWithRole(Role::MEMBER);

        $this->actingAs($admin, 'web')
            ->patchJson("/api/v1/administration/users/{$target->uuid}", [
                'personal_space_enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.personal_space_enabled', false);

        $this->assertFalse((bool) $target->refresh()->personal_space_enabled);

        $audit = AuditLog::query()
            ->where('action', 'user.updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($target->uuid, $audit->target_uuid);
        $this->assertSame(['changed_fields' => ['personal_space_enabled']], $audit->metadata);
    }

    public function test_system_manage_is_required_for_user_file_type_policy_administration(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $target = $this->userWithRole(Role::MEMBER);

        $this->actingAs($admin, 'web')
            ->getJson("/api/v1/administration/users/{$target->uuid}/file-type-policy")
            ->assertForbidden();

        $this->actingAs($admin, 'web')
            ->putJson("/api/v1/administration/users/{$target->uuid}/file-type-policy", [
                'disabled_file_type_ids' => [],
            ])
            ->assertForbidden();
    }

    public function test_super_admin_can_replace_restrictive_user_policy_and_safe_audit_is_recorded(): void
    {
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $target = $this->userWithRole(Role::MEMBER);
        $txt = FileType::query()->where('extension', 'txt')->sole();
        $pdf = FileType::query()->where('extension', 'pdf')->sole();

        $this->actingAs($superAdmin, 'web')
            ->putJson("/api/v1/administration/users/{$target->uuid}/file-type-policy", [
                'disabled_file_type_ids' => [$txt->uuid, $pdf->uuid],
            ])
            ->assertOk()
            ->assertJsonPath('data.user.id', $target->uuid)
            ->assertJsonCount(2, 'data.disabled_file_type_ids');

        $this->assertDatabaseHas('user_file_type_policies', [
            'user_id' => $target->getKey(),
            'file_type_id' => $txt->getKey(),
            'is_allowed' => false,
        ]);
        $this->assertDatabaseHas('user_file_type_policies', [
            'user_id' => $target->getKey(),
            'file_type_id' => $pdf->getKey(),
            'is_allowed' => false,
        ]);

        $this->actingAs($superAdmin, 'web')
            ->putJson("/api/v1/administration/users/{$target->uuid}/file-type-policy", [
                'disabled_file_type_ids' => [$txt->uuid],
            ])
            ->assertOk()
            ->assertJsonPath('data.disabled_file_type_ids.0', $txt->uuid);

        $this->assertSame(1, UserFileTypePolicy::query()->where('user_id', $target->getKey())->count());

        $audit = AuditLog::query()
            ->where('action', 'file_type.user_policy_updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($target->uuid, $audit->target_uuid);
        $this->assertSame(['disabled_count' => 1], $audit->metadata);
        foreach ($txt->mime_types as $mime) {
            $this->assertStringNotContainsString((string) $mime, $audit->toJson());
        }
    }

    public function test_user_policy_rows_are_isolated_per_target_user(): void
    {
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $first = $this->userWithRole(Role::MEMBER);
        $second = $this->userWithRole(Role::MEMBER);
        $txt = FileType::query()->where('extension', 'txt')->sole();

        $this->actingAs($superAdmin, 'web')
            ->putJson("/api/v1/administration/users/{$first->uuid}/file-type-policy", [
                'disabled_file_type_ids' => [$txt->uuid],
            ])
            ->assertOk();

        $this->actingAs($superAdmin, 'web')
            ->getJson("/api/v1/administration/users/{$second->uuid}/file-type-policy")
            ->assertOk()
            ->assertJsonPath('data.disabled_file_type_ids', []);

        $this->assertDatabaseMissing('user_file_type_policies', [
            'user_id' => $second->getKey(),
            'file_type_id' => $txt->getKey(),
        ]);
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('name', $roleName)->firstOrFail());

        return $user->refresh();
    }
}

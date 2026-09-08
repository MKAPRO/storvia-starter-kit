<?php

namespace Tests\Feature\Organization;

use App\Models\Department;
use App\Models\FileSpace;
use App\Models\Node;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DepartmentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    /**
     * @return array<string, string>
     */
    private function spaHeaders(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Accept' => 'application/json',
        ];
    }

    public function test_guest_receives_stable_401_for_department_administration(): void
    {
        $this->getJson('/api/v1/administration/departments', $this->spaHeaders())
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'AUTH_REQUIRED');
    }

    public function test_member_receives_stable_403_for_department_administration(): void
    {
        $member = $this->userWithRole(Role::MEMBER);
        $this->actingAs($member, 'web');

        $this->getJson('/api/v1/administration/departments', $this->spaHeaders())
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');
    }

    public function test_unauthorized_create_is_rejected_before_payload_validation(): void
    {
        $member = $this->userWithRole(Role::MEMBER);
        $this->actingAs($member, 'web');

        $this->postJson('/api/v1/administration/departments', [
            'name' => '',
            'parent_id' => 'not-a-uuid',
        ], $this->spaHeaders())
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');
    }

    public function test_admin_creates_child_department_using_public_uuid_contract(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $parent = Department::factory()->create(['name' => 'Technology']);
        $this->actingAs($admin, 'web');

        $response = $this->postJson('/api/v1/administration/departments', [
            'name' => 'Software',
            'parent_id' => $parent->uuid,
        ], $this->spaHeaders())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Software')
            ->assertJsonPath('data.parent_id', $parent->uuid)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonMissingPath('data.internal_id');

        $this->assertTrue(Str::isUuid((string) $response->json('data.id')));
        $this->assertDatabaseHas('departments', [
            'uuid' => $response->json('data.id'),
            'name' => 'Software',
            'parent_id' => $parent->getKey(),
            'is_active' => true,
        ]);
    }

    public function test_new_administration_and_child_department_start_with_zero_byte_storage_limits(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $this->actingAs($admin, 'web');

        $administrationResponse = $this->postJson('/api/v1/administration/departments', [
            'name' => 'Technology Directorate',
        ], $this->spaHeaders())
            ->assertCreated();

        $administration = Department::query()
            ->where('uuid', $administrationResponse->json('data.id'))
            ->firstOrFail();
        $administrationSpace = FileSpace::query()
            ->where('type', FileSpace::TYPE_DEPARTMENT)
            ->where('department_id', $administration->getKey())
            ->firstOrFail();

        $this->assertSame(0, $administrationSpace->limit_bytes);

        $departmentResponse = $this->postJson('/api/v1/administration/departments', [
            'name' => 'Software',
            'parent_id' => $administration->uuid,
        ], $this->spaHeaders())
            ->assertCreated();

        $department = Department::query()
            ->where('uuid', $departmentResponse->json('data.id'))
            ->firstOrFail();
        $departmentSpace = FileSpace::query()
            ->where('type', FileSpace::TYPE_DEPARTMENT)
            ->where('department_id', $department->getKey())
            ->firstOrFail();

        $this->assertSame(0, $departmentSpace->limit_bytes);
        $this->assertSame($administration->getKey(), $department->parent_id);
    }

    public function test_create_department_ignores_unexpected_internal_identity_fields(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $suppliedUuid = (string) Str::uuid();
        $this->actingAs($admin, 'web');

        $response = $this->postJson('/api/v1/administration/departments', [
            'id' => 999999,
            'uuid' => $suppliedUuid,
            'name' => 'Operations',
        ], $this->spaHeaders())
            ->assertCreated();

        $this->assertNotSame($suppliedUuid, $response->json('data.id'));
        $this->assertDatabaseMissing('departments', ['uuid' => $suppliedUuid]);
    }

    public function test_create_department_validation_uses_stable_error_contract(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $this->actingAs($admin, 'web');

        $this->postJson('/api/v1/administration/departments', [
            'name' => '',
            'parent_id' => (string) Str::uuid(),
        ], $this->spaHeaders())
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure([
                'error' => [
                    'details' => [
                        'fields' => ['name', 'parent_id'],
                    ],
                ],
            ]);
    }

    public function test_update_rejects_a_department_cycle_and_preserves_the_existing_parent(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $parent = Department::factory()->create(['name' => 'Technology']);
        $child = Department::factory()->create([
            'name' => 'Software',
            'parent_id' => $parent->getKey(),
        ]);
        $this->actingAs($admin, 'web');

        $this->patchJson("/api/v1/administration/departments/{$parent->uuid}", [
            'parent_id' => $child->uuid,
        ], $this->spaHeaders())
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure([
                'error' => [
                    'details' => [
                        'fields' => ['parent_id'],
                    ],
                ],
            ]);

        $this->assertNull($parent->refresh()->parent_id);
    }

    public function test_admin_can_sync_and_clear_department_members_using_user_uuids(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $department = Department::factory()->create(['name' => 'Technology']);
        $firstUser = User::factory()->create(['name' => 'First Member']);
        $secondUser = User::factory()->create(['name' => 'Second Member']);
        $this->actingAs($admin, 'web');

        $this->putJson("/api/v1/administration/departments/{$department->uuid}/members", [
            'user_ids' => [$firstUser->uuid, $secondUser->uuid],
        ], $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.members_count', 2)
            ->assertJsonCount(2, 'data.members');

        $this->assertDatabaseHas('department_user', [
            'department_id' => $department->getKey(),
            'user_id' => $firstUser->getKey(),
        ]);
        $this->assertDatabaseHas('department_user', [
            'department_id' => $department->getKey(),
            'user_id' => $secondUser->getKey(),
        ]);

        $this->putJson("/api/v1/administration/departments/{$department->uuid}/members", [
            'user_ids' => [],
        ], $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.members_count', 0)
            ->assertJsonCount(0, 'data.members');

        $this->assertDatabaseCount('department_user', 0);
    }

    public function test_admin_can_delete_an_empty_department_and_its_empty_file_namespace(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $department = Department::factory()->create(['name' => 'Temporary']);
        $fileSpace = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);
        $this->actingAs($admin, 'web');

        $this->deleteJson("/api/v1/administration/departments/{$department->uuid}", [], $this->spaHeaders())
            ->assertNoContent();

        $this->assertModelMissing($department);
        $this->assertModelMissing($fileSpace);
    }

    public function test_delete_rejects_a_department_that_has_child_departments(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $department = Department::factory()->create(['name' => 'Technology']);
        Department::factory()->create(['parent_id' => $department->getKey()]);
        $this->actingAs($admin, 'web');

        $this->deleteJson("/api/v1/administration/departments/{$department->uuid}", [], $this->spaHeaders())
            ->assertConflict()
            ->assertJsonPath('error.code', 'RESOURCE_CONFLICT')
            ->assertJsonPath('error.details.blockers.child_departments', 1);

        $this->assertModelExists($department);
    }

    public function test_delete_rejects_a_department_that_has_members(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $department = Department::factory()->create(['name' => 'Technology']);
        $department->users()->attach(User::factory()->create());
        $this->actingAs($admin, 'web');

        $this->deleteJson("/api/v1/administration/departments/{$department->uuid}", [], $this->spaHeaders())
            ->assertConflict()
            ->assertJsonPath('error.code', 'RESOURCE_CONFLICT')
            ->assertJsonPath('error.details.blockers.members', 1);

        $this->assertModelExists($department);
    }

    public function test_delete_rejects_a_department_that_has_file_content(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $department = Department::factory()->create(['name' => 'Technology']);
        $fileSpace = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);
        Node::factory()->file()->create(['file_space_id' => $fileSpace->getKey()]);
        $this->actingAs($admin, 'web');

        $this->deleteJson("/api/v1/administration/departments/{$department->uuid}", [], $this->spaHeaders())
            ->assertConflict()
            ->assertJsonPath('error.code', 'RESOURCE_CONFLICT')
            ->assertJsonPath('error.details.blockers.nodes', 1);

        $this->assertModelExists($department);
    }

    public function test_member_cannot_delete_a_department(): void
    {
        $member = $this->userWithRole(Role::MEMBER);
        $department = Department::factory()->create();
        $this->actingAs($member, 'web');

        $this->deleteJson("/api/v1/administration/departments/{$department->uuid}", [], $this->spaHeaders())
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');

        $this->assertModelExists($department);
    }

    public function test_member_cannot_sync_department_members(): void
    {
        $member = $this->userWithRole(Role::MEMBER);
        $department = Department::factory()->create();
        $this->actingAs($member, 'web');

        $this->putJson("/api/v1/administration/departments/{$department->uuid}/members", [
            'user_ids' => [],
        ], $this->spaHeaders())
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('name', $roleName)->firstOrFail());

        return $user;
    }
}

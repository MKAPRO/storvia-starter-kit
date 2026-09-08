<?php

namespace Tests\Feature\FileManager;

use App\Models\Department;
use App\Models\FileSpace;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FileSpaceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_guest_receives_401_for_file_space_listing(): void
    {
        $this->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'AUTH_REQUIRED');
    }

    public function test_member_sees_only_owned_personal_and_joined_department_spaces(): void
    {
        $member = $this->userWithRole(Role::MEMBER);
        $other = $this->userWithRole(Role::MEMBER);
        $joinedDepartment = Department::factory()->create(['name' => 'Technology']);
        $otherDepartment = Department::factory()->create(['name' => 'Finance']);
        $joinedDepartment->users()->attach($member);

        $personal = FileSpace::factory()->create(['owner_user_id' => $member->getKey()]);
        FileSpace::factory()->create(['owner_user_id' => $other->getKey()]);
        $department = FileSpace::factory()->department()->create([
            'department_id' => $joinedDepartment->getKey(),
        ]);
        FileSpace::factory()->department()->create([
            'department_id' => $otherDepartment->getKey(),
        ]);
        $this->actingAs($member, 'web');

        $response = $this->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonMissingPath('data.0.internal_id');

        $this->assertSame(
            collect([$personal->uuid, $department->uuid])->sort()->values()->all(),
            collect($response->json('data'))->pluck('id')->sort()->values()->all(),
        );
    }

    public function test_listing_does_not_repair_missing_visible_department_spaces(): void
    {
        $member = $this->userWithRole(Role::MEMBER);
        $departments = Department::factory()->count(25)->create();

        foreach ($departments as $department) {
            $department->users()->attach($member);
        }

        $this->actingAs($member, 'web');

        $this->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', FileSpace::TYPE_PERSONAL);

        $this->assertDatabaseMissing('file_spaces', [
            'type' => FileSpace::TYPE_DEPARTMENT,
        ]);
    }

    public function test_listing_lazily_provisions_missing_personal_space_for_active_user(): void
    {
        $member = $this->userWithRole(Role::MEMBER);
        $this->assertFalse(
            FileSpace::query()
                ->where('type', FileSpace::TYPE_PERSONAL)
                ->where('owner_user_id', $member->getKey())
                ->exists(),
        );

        $this->actingAs($member, 'web');

        $response = $this->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', FileSpace::TYPE_PERSONAL)
            ->assertJsonPath('data.0.owner_id', $member->uuid)
            ->assertJsonPath('data.0.allowed_actions', ['browse', 'create_folder', 'upload_file']);

        $personal = FileSpace::query()
            ->where('type', FileSpace::TYPE_PERSONAL)
            ->where('owner_user_id', $member->getKey())
            ->sole();

        $this->assertSame($personal->uuid, $response->json('data.0.id'));
    }

    public function test_personal_space_exposes_backend_authoritative_root_capabilities(): void
    {
        $member = $this->userWithRole(Role::MEMBER);
        $personal = FileSpace::factory()->create([
            'owner_user_id' => $member->getKey(),
        ]);
        $this->actingAs($member, 'web');

        $response = $this->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertSame($personal->uuid, $response->json('data.0.id'));
        $this->assertSame(
            ['browse', 'create_folder', 'upload_file'],
            $response->json('data.0.allowed_actions'),
        );
    }

    public function test_admin_sees_own_personal_and_department_spaces_but_not_another_users_personal_space(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $owner = $this->userWithRole(Role::MEMBER);
        $departmentSpace = FileSpace::factory()->department()->create();
        $foreignPersonal = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $this->actingAs($admin, 'web');

        $response = $this->getJson('/api/v1/file-manager/spaces', $this->spaHeaders())
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $adminPersonal = FileSpace::query()
            ->where('type', FileSpace::TYPE_PERSONAL)
            ->where('owner_user_id', $admin->getKey())
            ->sole();

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($adminPersonal->uuid));
        $this->assertTrue($ids->contains($departmentSpace->uuid));
        $this->assertFalse($ids->contains($foreignPersonal->uuid));
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

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('name', $roleName)->firstOrFail());

        return $user;
    }
}

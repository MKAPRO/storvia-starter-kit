<?php

namespace Tests\Feature\FileManager;

use App\Models\Department;
use App\Models\FileSpace;
use App\Models\User;
use App\Services\FileManager\FileSpaceProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class FileSpaceFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_personal_file_space_is_idempotent_and_owned_by_the_user(): void
    {
        $user = User::factory()->create();
        $provisioner = app(FileSpaceProvisioner::class);

        $first = $provisioner->personalFor($user);
        $second = $provisioner->personalFor($user);

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertTrue(Str::isUuid($first->uuid));
        $this->assertTrue($first->isPersonal());
        $this->assertSame($user->getKey(), $first->owner->getKey());
        $this->assertNull($first->department_id);
        $this->assertTrue($user->fresh()->ownedFileSpaces->contains($first));
        $this->assertDatabaseCount('file_spaces', 1);
    }

    public function test_department_file_space_is_idempotent_and_bound_to_the_department(): void
    {
        $department = Department::factory()->create(['name' => 'Technology']);
        $provisioner = app(FileSpaceProvisioner::class);

        $first = $provisioner->departmentFor($department);
        $second = $provisioner->departmentFor($department);

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertTrue($first->isDepartment());
        $this->assertSame($department->getKey(), $first->department->getKey());
        $this->assertNull($first->owner_user_id);
        $this->assertTrue($department->fresh()->fileSpaces->contains($first));
        $this->assertDatabaseCount('file_spaces', 1);
    }

    public function test_personal_and_department_spaces_are_separate_namespaces(): void
    {
        $user = User::factory()->create();
        $department = Department::factory()->create();
        $provisioner = app(FileSpaceProvisioner::class);

        $personal = $provisioner->personalFor($user);
        $departmentSpace = $provisioner->departmentFor($department);

        $this->assertNotSame($personal->getKey(), $departmentSpace->getKey());
        $this->assertSame(FileSpace::TYPE_PERSONAL, $personal->type);
        $this->assertSame(FileSpace::TYPE_DEPARTMENT, $departmentSpace->type);
        $this->assertDatabaseCount('file_spaces', 2);
    }

    public function test_file_space_rejects_an_ambiguous_namespace_target(): void
    {
        $user = User::factory()->create();
        $department = Department::factory()->create();

        $this->expectException(LogicException::class);

        FileSpace::query()->create([
            'type' => FileSpace::TYPE_PERSONAL,
            'owner_user_id' => $user->getKey(),
            'department_id' => $department->getKey(),
        ]);
    }
}

<?php

namespace Tests\Feature\Organization;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DepartmentFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_department_identity_and_hierarchy_relationships_are_available(): void
    {
        $parent = Department::factory()->create(['name' => 'Technology']);
        $child = Department::factory()->create([
            'name' => 'Software',
            'parent_id' => $parent->getKey(),
        ]);

        $this->assertTrue(Str::isUuid($parent->uuid));
        $this->assertSame($parent->getKey(), $child->parent->getKey());
        $this->assertTrue($parent->children->contains($child));
        $this->assertTrue($parent->is_active);
    }

    public function test_users_can_belong_to_multiple_departments(): void
    {
        $user = User::factory()->create();
        $technology = Department::factory()->create(['name' => 'Technology']);
        $operations = Department::factory()->create(['name' => 'Operations']);

        $user->departments()->attach([$technology->getKey(), $operations->getKey()]);

        $this->assertCount(2, $user->fresh()->departments);
        $this->assertTrue($technology->fresh()->users->contains($user));
        $this->assertTrue($operations->fresh()->users->contains($user));
    }
}

<?php

namespace Tests\Feature\FileManager;

use App\Actions\FileManager\CreateFolder;
use App\Actions\FileManager\UpdateNode;
use App\Models\Department;
use App\Models\FileSpace;
use App\Models\Node;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class NodeMutationHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_create_folder_action_rechecks_manage_authority_inside_the_mutation_boundary(): void
    {
        $actor = $this->userWithPermissions(['files.department.view_all']);
        $department = Department::factory()->create();
        $space = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);

        try {
            app(CreateFolder::class)->handle($space, $actor, [
                'name' => 'Blocked Direct Write',
            ]);

            $this->fail('Expected direct folder mutation to re-check manage authority.');
        } catch (AuthorizationException) {
            $this->assertDatabaseMissing('nodes', [
                'file_space_id' => $space->getKey(),
                'name' => 'Blocked Direct Write',
            ]);
        }
    }

    public function test_update_node_action_rechecks_manage_authority_inside_the_mutation_boundary(): void
    {
        $actor = $this->userWithPermissions(['files.department.view_all']);
        $department = Department::factory()->create();
        $space = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);
        $node = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => User::factory()->create()->getKey(),
            'name' => 'Read Only',
        ]);

        try {
            app(UpdateNode::class)->handle($node, $actor, [
                'name' => 'Blocked Direct Rename',
            ]);

            $this->fail('Expected direct node mutation to re-check manage authority.');
        } catch (AuthorizationException) {
            $this->assertSame('Read Only', $node->refresh()->name);
        }
    }

    public function test_department_member_can_create_and_update_inside_its_own_namespace(): void
    {
        $member = $this->userWithRole(Role::MEMBER);
        $department = Department::factory()->create();
        $department->users()->attach($member);
        $space = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);

        $folder = app(CreateFolder::class)->handle($space, $member, [
            'name' => ' Team ',
        ]);

        $updated = app(UpdateNode::class)->handle($folder, $member, [
            'name' => 'Team Documents',
        ]);

        $this->assertSame('Team Documents', $updated->name);
        $this->assertSame($member->getKey(), $updated->owner_id);
        $this->assertSame(Node::TYPE_FOLDER, $updated->type);
    }

    public function test_update_rejects_a_trashed_target_parent_and_preserves_the_original_parent(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create([
            'owner_user_id' => $owner->getKey(),
        ]);
        $source = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Source',
        ]);
        $trashedTarget = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Trashed Target',
            'trashed_at' => now(),
            'trashed_by' => $owner->getKey(),
            'trash_batch_uuid' => (string) Str::uuid(),
            'is_trash_root' => true,
        ]);
        $node = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'parent_id' => $source->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Child',
        ]);

        try {
            app(UpdateNode::class)->handle($node, $owner, [
                'parent_id' => $trashedTarget->uuid,
            ]);

            $this->fail('Expected moving beneath a trashed parent to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'The selected parent is invalid.',
                $exception->errors()['parent_id'][0] ?? null,
            );
            $this->assertSame($source->getKey(), $node->refresh()->parent_id);
        }
    }

    public function test_file_rename_and_move_keep_physical_storage_identity_immutable(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create([
            'owner_user_id' => $owner->getKey(),
        ]);
        $source = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Source',
        ]);
        $target = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Target',
        ]);
        $file = Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'parent_id' => $source->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'before.bin',
        ]);

        $disk = $file->storage_disk;
        $key = $file->storage_key;
        $checksum = $file->checksum;

        $updated = app(UpdateNode::class)->handle($file, $owner, [
            'name' => 'after.bin',
            'parent_id' => $target->uuid,
        ]);

        $this->assertSame('after.bin', $updated->name);
        $this->assertSame($target->getKey(), $updated->parent_id);
        $this->assertSame($disk, $updated->storage_disk);
        $this->assertSame($key, $updated->storage_key);
        $this->assertSame($checksum, $updated->checksum);
    }

    public function test_http_update_rejects_a_file_as_target_parent(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create([
            'owner_user_id' => $owner->getKey(),
        ]);
        $node = Node::factory()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'Folder',
        ]);
        $fileParent = Node::factory()->file()->create([
            'file_space_id' => $space->getKey(),
            'owner_id' => $owner->getKey(),
            'name' => 'not-a-folder.bin',
        ]);

        $this->actingAs($owner, 'web');

        $this->patchJson(
            "/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$node->uuid}",
            ['parent_id' => $fileParent->uuid],
            $this->spaHeaders(),
        )
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath(
                'error.details.fields.parent_id.0',
                'Only folders can contain child nodes.',
            );

        $this->assertNull($node->refresh()->parent_id);
    }

    /**
     * @param  list<string>  $permissionNames
     */
    private function userWithPermissions(array $permissionNames): User
    {
        $role = Role::query()->create([
            'name' => 'mutation_'.Str::lower(Str::random(12)),
            'label' => 'Mutation Hardening Role',
            'is_system' => false,
        ]);

        $permissionIds = Permission::query()
            ->whereIn('name', $permissionNames)
            ->pluck('id')
            ->all();

        $this->assertCount(count($permissionNames), $permissionIds);
        $role->permissions()->sync($permissionIds);

        $user = User::factory()->create();
        $user->roles()->sync([$role->getKey()]);

        return $user->refresh();
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([
            Role::query()->where('name', $roleName)->firstOrFail()->getKey(),
        ]);

        return $user->refresh();
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
}

<?php

namespace Tests\Feature\FileManager;

use App\Models\Department;
use App\Models\DepartmentFileTypePolicy;
use App\Models\FileSpace;
use App\Models\FileType;
use App\Models\Node;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class FileTypeUploadPolicySecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
        Storage::fake('local');
    }

    public function test_allowed_type_uses_server_detected_mime_and_normalizes_extension_case(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);

        $response = $this->actingAs($owner, 'web')
            ->post(
                "/api/v1/file-manager/spaces/{$space->uuid}/files",
                ['file' => UploadedFile::fake()->createWithContent('REPORT.TXT', 'plain text')],
                $this->spaHeaders(),
            )
            ->assertCreated()
            ->assertJsonPath('data.file.extension', 'txt');

        $node = Node::query()->where('uuid', $response->json('data.id'))->sole();
        $this->assertSame('text/plain', strtolower((string) $node->mime_type));
        Storage::disk('local')->assertExists($node->storage_key);
    }

    public function test_client_mime_cannot_override_server_finfo_detection(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $path = tempnam(sys_get_temp_dir(), 'storvia-mime-');
        $this->assertNotFalse($path);
        file_put_contents($path, 'server says text');
        $upload = new UploadedFile(
            $path,
            'client-lied.txt',
            'application/pdf',
            null,
            true,
        );

        try {
            $this->actingAs($owner, 'web')
                ->post(
                    "/api/v1/file-manager/spaces/{$space->uuid}/files",
                    ['file' => $upload],
                    $this->spaHeaders(),
                )
                ->assertCreated();
        } finally {
            @unlink($path);
        }

        $node = Node::query()->where('name', 'client-lied.txt')->sole();
        $this->assertSame('text/plain', strtolower((string) $node->mime_type));
    }

    public function test_ooxml_container_profile_blocks_plain_zip_spoofing_but_allows_matching_docx(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $validDocx = base64_decode(
            'UEsDBBQAAAAIAIdqI13LBSEovgAAAAUBAAATAAAAW0NvbnRlbnRfVHlwZXNdLnhtbF2PsU4EMQxEe74icot2vVAghC53BRIlUBwfYCXevYiNHSXhgL/HC9IVlNbMvBnvDl95dWeuLal4uBkncCxBY5LFw9vxabiHw/5qd/wu3Jx5pXk49V4eEFs4caY2amExZdaaqdtZFywU3mlhvJ2mOwwqnaUPfWOAwV6sr6bI7pVqf6bMHvBTa8So4SObdTQcuMe/3FbtgUpZU6BuM/Es8V/poPOcAl/yG61UDdyaPZLX8aJkSnK94dGG4O9b+x9QSwMEFAAAAAgAh2ojXYLZHNUSAAAAEAAAAAsAAABfcmVscy8ucmVsc7MJSs1JLMnMzyvOyCwo1rcDAFBLAwQUAAAACACHaiNdRCmNNQkAAAAHAAAAEQAAAHdvcmQvZG9jdW1lbnQueG1ss8lNzMzTtwMAUEsBAhQDFAAAAAgAh2ojXcsFISi+AAAABQEAABMAAAAAAAAAAAAAAIABAAAAAFtDb250ZW50X1R5cGVzXS54bWxQSwECFAMUAAAACACHaiNdgtkc1RIAAAAQAAAACwAAAAAAAAAAAAAAgAHvAAAAX3JlbHMvLnJlbHNQSwECFAMUAAAACACHaiNdRCmNNQkAAAAHAAAAEQAAAAAAAAAAAAAAgAEqAQAAd29yZC9kb2N1bWVudC54bWxQSwUGAAAAAAMAAwC5AAAAYgEAAAAA',
            true,
        );
        $plainZip = base64_decode(
            'UEsDBBQAAAAIAIdqI12GphA2BwAAAAUAAAAJAAAAaGVsbG8udHh0y0jNyckHAFBLAQIUAxQAAAAIAIdqI12GphA2BwAAAAUAAAAJAAAAAAAAAAAAAACAAQAAAABoZWxsby50eHRQSwUGAAAAAAEAAQA3AAAALgAAAAAA',
            true,
        );
        $this->assertIsString($validDocx);
        $this->assertIsString($plainZip);

        $this->actingAs($owner, 'web')
            ->post(
                "/api/v1/file-manager/spaces/{$space->uuid}/files",
                ['file' => UploadedFile::fake()->createWithContent('document.docx', $validDocx)],
                $this->spaHeaders(),
            )
            ->assertCreated();

        $this->actingAs($owner, 'web')
            ->post(
                "/api/v1/file-manager/spaces/{$space->uuid}/files",
                ['file' => UploadedFile::fake()->createWithContent('plain-zip.docx', $plainZip)],
                $this->spaHeaders(),
            )
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->assertDatabaseCount('nodes', 1);
    }

    public function test_extension_spoofing_unknown_types_and_double_extensions_are_rejected_without_objects(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
        $this->assertIsString($png);

        foreach ([
            ['readme.md', 'plain text'],
            ['report.pdf.exe', "%PDF-1.7\n"],
            ['payload.exe.pdf', $png],
            ['image.pdf', $png],
            ['no-extension', 'plain text'],
        ] as [$name, $contents]) {
            $this->actingAs($owner, 'web')
                ->post(
                    "/api/v1/file-manager/spaces/{$space->uuid}/files",
                    ['file' => UploadedFile::fake()->createWithContent($name, $contents)],
                    $this->spaHeaders(),
                )
                ->assertUnprocessable()
                ->assertJsonPath('error.code', 'VALIDATION_FAILED');
        }

        $this->assertDatabaseCount('nodes', 0);
        Storage::disk('local')->assertEmpty();
    }

    public function test_global_and_department_disabled_types_block_new_uploads_but_personal_space_ignores_department_deny(): void
    {
        $member = $this->userWithRole(Role::MEMBER);
        $department = Department::factory()->create();
        $department->users()->attach($member);
        $departmentSpace = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);
        $personalSpace = FileSpace::factory()->create(['owner_user_id' => $member->getKey()]);
        $txt = FileType::query()->where('extension', 'txt')->sole();

        DepartmentFileTypePolicy::query()->create([
            'department_id' => $department->getKey(),
            'file_type_id' => $txt->getKey(),
            'is_allowed' => false,
        ]);

        $this->actingAs($member, 'web')
            ->post(
                "/api/v1/file-manager/spaces/{$departmentSpace->uuid}/files",
                ['file' => UploadedFile::fake()->createWithContent('blocked.txt', 'blocked')],
                $this->spaHeaders(),
            )
            ->assertUnprocessable();

        $this->actingAs($member, 'web')
            ->post(
                "/api/v1/file-manager/spaces/{$personalSpace->uuid}/files",
                ['file' => UploadedFile::fake()->createWithContent('personal.txt', 'allowed')],
                $this->spaHeaders(),
            )
            ->assertCreated();

        $txt->forceFill(['is_enabled' => false])->save();

        $this->actingAs($member, 'web')
            ->post(
                "/api/v1/file-manager/spaces/{$personalSpace->uuid}/files",
                ['file' => UploadedFile::fake()->createWithContent('global-block.txt', 'blocked')],
                $this->spaHeaders(),
            )
            ->assertUnprocessable();

        $this->assertSame(1, Node::query()->where('file_space_id', $personalSpace->getKey())->count());
    }

    public function test_rename_cannot_change_extension_but_same_extension_remains_allowed(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);

        $upload = $this->actingAs($owner, 'web')
            ->post(
                "/api/v1/file-manager/spaces/{$space->uuid}/files",
                ['file' => UploadedFile::fake()->createWithContent('report.txt', 'report')],
                $this->spaHeaders(),
            )
            ->assertCreated();
        $uuid = (string) $upload->json('data.id');

        $this->actingAs($owner, 'web')
            ->patchJson("/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$uuid}", [
                'name' => 'report.pdf',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.fields.name.0', 'A file rename cannot change its extension.');

        $this->actingAs($owner, 'web')
            ->patchJson("/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$uuid}", [
                'name' => 'renamed.TXT',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'renamed.TXT')
            ->assertJsonPath('data.file.extension', 'txt');
    }

    public function test_existing_file_restore_is_not_retroactively_blocked_after_type_disable(): void
    {
        $owner = $this->userWithRole(Role::MEMBER);
        $space = FileSpace::factory()->create(['owner_user_id' => $owner->getKey()]);

        $upload = $this->actingAs($owner, 'web')
            ->post(
                "/api/v1/file-manager/spaces/{$space->uuid}/files",
                ['file' => UploadedFile::fake()->createWithContent('history.txt', 'history')],
                $this->spaHeaders(),
            )
            ->assertCreated();
        $uuid = (string) $upload->json('data.id');

        $this->actingAs($owner, 'web')
            ->deleteJson("/api/v1/file-manager/spaces/{$space->uuid}/nodes/{$uuid}")
            ->assertOk();

        $txt = FileType::query()->where('extension', 'txt')->sole();
        $txt->forceFill(['is_enabled' => false])->save();

        $this->actingAs($owner, 'web')
            ->postJson("/api/v1/file-manager/spaces/{$space->uuid}/trash/{$uuid}/restore")
            ->assertOk();

        $this->assertNull(Node::query()->where('uuid', $uuid)->sole()->trashed_at);
    }

    public function test_filespace_upload_policy_exposes_safe_registry_descriptors_and_effective_allowance(): void
    {
        $member = $this->userWithRole(Role::MEMBER);
        $department = Department::factory()->create();
        $department->users()->attach($member);
        $space = FileSpace::factory()->department()->create([
            'department_id' => $department->getKey(),
        ]);
        $pdf = FileType::query()->where('extension', 'pdf')->sole();
        DepartmentFileTypePolicy::query()->create([
            'department_id' => $department->getKey(),
            'file_type_id' => $pdf->getKey(),
            'is_allowed' => false,
        ]);

        $response = $this->actingAs($member, 'web')
            ->getJson("/api/v1/file-manager/spaces/{$space->uuid}/upload-policy")
            ->assertOk();

        $this->assertNotContains('pdf', $response->json('data.accepted_extensions'));
        $pdfDescriptor = collect($response->json('data.file_types'))
            ->firstWhere('extension', 'pdf');

        $this->assertIsArray($pdfDescriptor);
        $this->assertFalse($pdfDescriptor['upload_allowed']);
        $this->assertSame('pdf', $pdfDescriptor['preview_mode']);
        $this->assertArrayNotHasKey('mime_types', $pdfDescriptor);
    }

    /** @return array<string, string> */
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

        return $user->refresh();
    }
}

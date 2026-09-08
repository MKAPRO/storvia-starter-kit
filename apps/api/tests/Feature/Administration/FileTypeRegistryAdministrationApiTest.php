<?php

namespace Tests\Feature\Administration;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\FileSpace;
use App\Models\FileType;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FileTypeRegistryAdministrationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_standard_registry_is_seeded_with_small_bounded_catalog(): void
    {
        $this->assertSame(15, FileType::query()->count());
        $this->assertSame(
            [
                'csv', 'doc', 'docx', 'gif', 'jpeg', 'jpg', 'pdf', 'png',
                'ppt', 'pptx', 'txt', 'webp', 'xls', 'xlsx', 'zip',
            ],
            FileType::query()->orderBy('extension')->pluck('extension')->all(),
        );

        $pdf = FileType::query()->where('extension', 'pdf')->sole();
        $this->assertSame(['application/pdf'], $pdf->mime_types);
        $this->assertSame(FileType::PREVIEW_PDF, $pdf->preview_mode);
    }

    public function test_file_type_administration_requires_system_manage(): void
    {
        $this->getJson('/api/v1/administration/file-types')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'AUTH_REQUIRED');

        $member = $this->userWithRole(Role::MEMBER);
        $this->actingAs($member, 'web')
            ->getJson('/api/v1/administration/file-types')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCESS_DENIED');
    }

    public function test_active_super_admin_can_list_file_types(): void
    {
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);

        $this->actingAs($superAdmin)
            ->getJson('/api/v1/administration/file-types')
            ->assertOk()
            ->assertJsonCount(15, 'data');
    }

    public function test_super_admin_can_create_and_update_sanitized_file_type_without_raw_svg_audit_data(): void
    {
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $safeSvg = '<svg viewBox="0 0 24 24"><path stroke-width="2" stroke="currentColor" fill="none" d="M2 2L22 22"/></svg>';

        $created = $this->actingAs($superAdmin)
            ->postJson('/api/v1/administration/file-types', [
                'extension' => '.LOG',
                'label' => ' Log file ',
                'category' => 'text',
                'mime_types' => ['TEXT/PLAIN'],
                'is_enabled' => true,
                'preview_mode' => 'none',
                'icon_svg' => $safeSvg,
            ])
            ->assertCreated()
            ->assertJsonPath('data.extension', 'log')
            ->assertJsonPath('data.label', 'Log file')
            ->assertJsonPath('data.mime_types.0', 'text/plain')
            ->assertJsonPath('data.is_enabled', true);

        $uuid = (string) $created->json('data.id');
        $stored = FileType::query()->where('uuid', $uuid)->sole();

        $this->assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg"', (string) $stored->icon_svg);
        $this->assertStringNotContainsString('<script', (string) $stored->icon_svg);
        $this->assertStringNotContainsString('onload=', (string) $stored->icon_svg);

        $this->actingAs($superAdmin)
            ->patchJson("/api/v1/administration/file-types/{$uuid}", [
                'is_enabled' => false,
                'label' => 'System log',
            ])
            ->assertOk()
            ->assertJsonPath('data.is_enabled', false)
            ->assertJsonPath('data.label', 'System log');

        $audits = AuditLog::query()
            ->whereIn('action', ['file_type.created', 'file_type.updated'])
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $audits);
        $this->assertNull($audits[0]->metadata);
        $this->assertSame(['changed_fields' => ['is_enabled', 'label']], $audits[1]->metadata);
        $this->assertStringNotContainsString($safeSvg, $audits->toJson());
    }

    public function test_svg_attack_matrix_is_rejected_and_never_persisted(): void
    {
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $attacks = [
            '<svg><script>alert(1)</script></svg>',
            '<svg onload="alert(1)"><path d="M0 0L1 1"/></svg>',
            '<svg><foreignObject><div>html</div></foreignObject></svg>',
            '<svg><path d="M0 0L1 1" href="https://example.com/a"/></svg>',
            '<svg><path d="M0 0L1 1" style="fill:url(https://example.com/a)"/></svg>',
            '<svg><image href="data:image/png;base64,AAAA"/></svg>',
            '<?xml version="1.0"?><svg><path d="M0 0L1 1"/></svg>',
            '<svg><path d="M0 0L1 1"></svg>',
        ];

        foreach ($attacks as $index => $attack) {
            $this->actingAs($superAdmin)
                ->postJson('/api/v1/administration/file-types', [
                    'extension' => 'x'.$index,
                    'label' => 'Unsafe icon',
                    'category' => 'other',
                    'mime_types' => ['application/x-unsafe'.$index],
                    'is_enabled' => true,
                    'preview_mode' => 'none',
                    'icon_svg' => $attack,
                ])
                ->assertUnprocessable()
                ->assertJsonPath('error.code', 'VALIDATION_FAILED');
        }

        $this->assertDatabaseMissing('file_types', ['label' => 'Unsafe icon']);
    }

    public function test_department_policy_is_explicit_deny_without_tree_inheritance_and_is_audited(): void
    {
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $parent = Department::factory()->create(['name' => 'Parent']);
        $child = Department::factory()->create([
            'name' => 'Child',
            'parent_id' => $parent->getKey(),
        ]);
        FileSpace::factory()->department()->create(['department_id' => $parent->getKey()]);
        FileSpace::factory()->department()->create(['department_id' => $child->getKey()]);
        $pdf = FileType::query()->where('extension', 'pdf')->sole();

        $this->actingAs($superAdmin)
            ->putJson("/api/v1/administration/departments/{$parent->uuid}/file-type-policy", [
                'disabled_file_type_ids' => [$pdf->uuid],
            ])
            ->assertOk()
            ->assertJsonPath('data.disabled_file_type_ids.0', $pdf->uuid);

        $this->actingAs($superAdmin)
            ->getJson("/api/v1/administration/departments/{$child->uuid}/file-type-policy")
            ->assertOk()
            ->assertJsonPath('data.disabled_file_type_ids', []);

        $audit = AuditLog::query()
            ->where('action', 'file_type.department_policy_updated')
            ->sole();

        $this->assertSame($parent->uuid, $audit->target_uuid);
        $this->assertSame($parent->uuid, $audit->department_uuid);
        $this->assertSame(['disabled_count' => 1], $audit->metadata);
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('name', $roleName)->firstOrFail());

        return $user->refresh();
    }
}

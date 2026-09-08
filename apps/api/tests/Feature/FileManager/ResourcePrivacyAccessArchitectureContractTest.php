<?php

namespace Tests\Feature\FileManager;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class ResourcePrivacyAccessArchitectureContractTest extends TestCase
{
    public function test_public_contract_locks_resource_privacy_boundaries(): void
    {
        $contract = File::get(
            base_path('docs/architecture/resource-privacy-access-policy-contract.md'),
        );

        foreach ([
            'Resource Privacy / Access Policy Contract',
            'Status: **CONTRACT LOCKED**',
            '`inherit` — no local visibility restriction is added.',
            '`private` — the local policy gate admits only the owner.',
            '`restricted` — the local policy gate admits the owner plus explicit direct user grants.',
            'There is deliberately **no `public` resource state**.',
            'never bypasses organizational/FileSpace authorization',
            '`node_access_policies`',
            '`node_access_grants`',
            '`node_access_unlocks`',
            'direct **user-only** grants',
            'All applicable restrictive gates must pass.',
            'Resource passwords are never stored raw.',
            '`RESOURCE_PASSWORD_REQUIRED` with HTTP 423',
            '`node.privacy_changed`',
        ] as $requiredFragment) {
            $this->assertStringContainsString($requiredFragment, $contract);
        }
    }

    public function test_starter_keeps_direct_resource_acl_without_removed_collaboration_models(): void
    {
        $this->assertFileDoesNotExist(app_path('Models/NodeShare.php'));
        $this->assertFileDoesNotExist(app_path('Models/PublicShareLink.php'));

        $contract = File::get(
            base_path('docs/architecture/resource-privacy-access-policy-contract.md'),
        );

        $this->assertStringContainsString('There is deliberately **no `public` resource state**', $contract);
        $this->assertStringContainsString('Password unlock is not authorization', $contract);
    }

    public function test_direct_privacy_contract_does_not_approve_guessed_permission_names(): void
    {
        $permissions = config('access-control.permissions', []);

        $this->assertArrayNotHasKey('files.folder.can_grant_password', $permissions);
        $this->assertArrayNotHasKey('files.folder.can_grant_permission_view', $permissions);
    }
}

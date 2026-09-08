<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

final class ApiGuestRedirectContractTest extends TestCase
{
    public function test_guest_auth_me_without_json_accept_fails_closed_as_api_401(): void
    {
        $this->withHeader('Accept', 'text/html')
            ->get('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'AUTH_REQUIRED')
            ->assertJsonPath('error.message', 'Authentication is required.');
    }

    public function test_guest_file_manager_without_json_accept_fails_closed_before_resource_resolution(): void
    {
        $this->withHeader('Accept', 'text/html')
            ->get('/api/v1/file-manager/spaces')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'AUTH_REQUIRED')
            ->assertJsonPath('error.message', 'Authentication is required.');
    }
}

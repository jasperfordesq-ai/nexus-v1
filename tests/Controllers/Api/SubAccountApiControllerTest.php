<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

class SubAccountApiControllerTest extends ApiTestCase
{
    public function testGetChildAccounts(): void
    {
        $response = $this->get('/api/v2/users/me/sub-accounts', [], [],
            'Nexus\Controllers\Api\SubAccountApiController@getChildAccounts');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testGetParentAccounts(): void
    {
        $response = $this->get('/api/v2/users/me/parent-accounts', [], [],
            'Nexus\Controllers\Api\SubAccountApiController@getParentAccounts');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testRequestRelationship(): void
    {
        $user = $this->createUser();
        $response = $this->post('/api/v2/users/me/sub-accounts', [
            'child_user_id'     => $user['id'],
            'relationship_type' => 'family',
            'permissions'       => ['can_view_activity' => true],
        ], [], 'Nexus\Controllers\Api\SubAccountApiController@requestRelationship');

        $this->assertIsArray($response);
        $this->cleanupUser($user['id']);
    }

    public function testRequestRelationshipRequiresChildId(): void
    {
        $response = $this->post('/api/v2/users/me/sub-accounts', [
            'relationship_type' => 'family',
        ], [], 'Nexus\Controllers\Api\SubAccountApiController@requestRelationship');

        $this->assertIsArray($response);
    }

    public function testRevokeRelationship(): void
    {
        $response = $this->delete('/api/v2/users/me/sub-accounts/999', [], [],
            'Nexus\Controllers\Api\SubAccountApiController@revokeRelationship');

        $this->assertIsArray($response);
    }

    public function testGetChildActivity(): void
    {
        $response = $this->get('/api/v2/users/me/sub-accounts/1/activity', [], [],
            'Nexus\Controllers\Api\SubAccountApiController@getChildActivity');

        $this->assertIsArray($response);
    }
}

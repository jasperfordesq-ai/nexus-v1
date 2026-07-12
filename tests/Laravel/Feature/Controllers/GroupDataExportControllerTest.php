<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Http\Controllers\Api\GroupDataExportController;
use ReflectionMethod;
use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use App\Models\User;

/**
 * Feature smoke tests for GroupDataExportController.
 */
class GroupDataExportControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authenticatedUser(): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    public function test_export_all_requires_auth(): void
    {
        $this->apiGet('/v2/groups/1/export')->assertStatus(401);
    }

    public function test_synchronous_export_is_retired_with_an_explicit_gone_response(): void
    {
        $this->authenticatedUser();

        $this->apiGet('/v2/groups/1/export')
            ->assertStatus(410)
            ->assertJsonPath('errors.0.code', 'CAPABILITY_RETIRED');
    }

    public function test_retired_endpoint_cannot_invoke_the_full_manifest_builder(): void
    {
        $method = new ReflectionMethod(GroupDataExportController::class, 'exportAll');
        $fileName = $method->getFileName();
        self::assertIsString($fileName);
        $source = file($fileName, FILE_IGNORE_NEW_LINES);
        self::assertIsArray($source);
        $body = implode("\n", array_slice(
            $source,
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1,
        ));

        self::assertStringNotContainsString('GroupDataExportService::exportAll', $body);
        self::assertStringContainsString('CAPABILITY_RETIRED', $body);
    }
}

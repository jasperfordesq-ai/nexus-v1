<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Core\TenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Smoke + feature-gate tests for the Courses module (alpha).
 */
class CourseControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function enableCourses(bool $enabled = true): void
    {
        DB::table('tenants')->where('id', $this->testTenantId)
            ->update(['features' => json_encode(['courses' => $enabled])]);
        // Reload tenant context so hasFeature() sees the change.
        TenantContext::setById($this->testTenantId);
    }

    private function authenticatedUser(): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        Sanctum::actingAs($user, ['*']);
        return $user;
    }

    public function test_browse_returns_403_when_feature_disabled(): void
    {
        $this->enableCourses(false);
        $response = $this->apiGet('/v2/courses');
        $this->assertSame(403, $response->status());
    }

    public function test_browse_public_smoke_when_enabled(): void
    {
        $this->enableCourses(true);
        $response = $this->apiGet('/v2/courses');
        $this->assertLessThan(500, $response->status());
    }

    public function test_categories_public_smoke_when_enabled(): void
    {
        $this->enableCourses(true);
        $response = $this->apiGet('/v2/courses/categories');
        $this->assertLessThan(500, $response->status());
    }

    public function test_create_requires_auth(): void
    {
        $this->enableCourses(true);
        $response = $this->apiPost('/v2/courses', ['title' => 'X']);
        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_create_allowed_for_any_member(): void
    {
        // Authoring is open to any authenticated member by default.
        $this->enableCourses(true);
        $this->authenticatedUser(); // plain member, no instructor grant
        $response = $this->apiPost('/v2/courses', ['title' => 'My course']);
        $this->assertSame(201, $response->status());
    }

    public function test_my_enrolled_requires_auth(): void
    {
        $this->enableCourses(true);
        $response = $this->apiGet('/v2/me/courses');
        $this->assertContains($response->status(), [401, 403]);
    }
}

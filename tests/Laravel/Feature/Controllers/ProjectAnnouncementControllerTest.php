<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Controllers;

use App\Core\TenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

class ProjectAnnouncementControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function setCaringCommunityFeature(bool $enabled): void
    {
        $tenant = DB::table('tenants')->where('id', $this->testTenantId)->first();
        $features = [];
        if ($tenant && ! empty($tenant->features)) {
            $decoded = is_string($tenant->features) ? json_decode($tenant->features, true) : $tenant->features;
            $features = is_array($decoded) ? $decoded : [];
        }

        $features['caring_community'] = $enabled;
        DB::table('tenants')
            ->where('id', $this->testTenantId)
            ->update(['features' => json_encode($features)]);
        TenantContext::setById($this->testTenantId);
    }

    private function requireProjectAnnouncementTables(): void
    {
        if (
            ! Schema::hasTable('caring_project_announcements')
            || ! Schema::hasTable('caring_project_updates')
            || ! Schema::hasTable('caring_project_subscriptions')
        ) {
            $this->markTestSkipped('Project announcement tables are not present in the test database.');
        }
    }

    public function test_admin_project_routes_return_403_when_feature_disabled(): void
    {
        $this->setCaringCommunityFeature(false);
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/caring-community/projects');

        $response->assertStatus(403);
        $response->assertJsonPath('errors.0.code', 'FEATURE_DISABLED');
    }

    public function test_admin_can_publish_project_and_notify_subscribed_member_on_update(): void
    {
        $this->requireProjectAnnouncementTables();
        $this->setCaringCommunityFeature(true);

        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($admin);

        $create = $this->apiPost('/v2/admin/caring-community/projects', [
            'title' => 'Community garden renewal',
            'summary' => 'A staged project to renew the local community garden.',
            'location' => 'North Square',
            'status' => 'active',
            'current_stage' => 'Planning',
            'progress_percent' => 10,
        ]);

        $create->assertStatus(201);
        $create->assertJsonPath('data.title', 'Community garden renewal');
        $create->assertJsonPath('data.status', 'active');

        $projectId = (int) $create->json('data.id');

        Sanctum::actingAs($member);
        $feed = $this->apiGet('/v2/caring-community/projects');
        $feed->assertStatus(200);
        $feed->assertJsonPath('data.0.id', $projectId);

        $subscribe = $this->apiPost("/v2/caring-community/projects/{$projectId}/subscribe");
        $subscribe->assertStatus(200);
        $subscribe->assertJsonPath('data.ok', true);

        Sanctum::actingAs($admin);
        $update = $this->apiPost("/v2/admin/caring-community/projects/{$projectId}/updates", [
            'title' => 'Design consultation opened',
            'body' => 'Residents can now review the initial garden layout.',
            'stage_label' => 'Consultation',
            'progress_percent' => 35,
            'is_milestone' => true,
            'status' => 'published',
        ]);

        $update->assertStatus(201);
        $update->assertJsonPath('data.status', 'published');
        $update->assertJsonPath('data.notification_count', 1);

        $this->assertDatabaseHas('caring_project_announcements', [
            'id' => $projectId,
            'tenant_id' => $this->testTenantId,
            'current_stage' => 'Consultation',
            'progress_percent' => 35,
            'subscriber_count' => 1,
        ]);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->testTenantId,
            'user_id' => $member->id,
            'type' => 'caring_project_update',
            'link' => "/caring-community/projects/{$projectId}",
        ]);
    }

    public function test_project_feed_is_tenant_scoped(): void
    {
        $this->requireProjectAnnouncementTables();
        $this->setCaringCommunityFeature(true);

        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $ownProject = $this->apiPost('/v2/admin/caring-community/projects', [
            'title' => 'Tenant local project',
            'status' => 'active',
        ]);
        $ownProject->assertStatus(201);

        DB::table('caring_project_announcements')->insert([
            'tenant_id' => 999,
            'created_by' => null,
            'title' => 'Other tenant project',
            'status' => 'active',
            'progress_percent' => 50,
            'published_at' => now(),
            'subscriber_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $feed = $this->apiGet('/v2/caring-community/projects');

        $feed->assertStatus(200);
        $titles = collect($feed->json('data'))->pluck('title')->all();
        $this->assertContains('Tenant local project', $titles);
        $this->assertNotContains('Other tenant project', $titles);
    }
}

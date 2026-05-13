<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * AdminPrerenderController — auth, validation, lifecycle smoke tests.
 *
 * These tests don't require the snapshot cache volume to be mounted — the
 * service returns empty inventory if the path is unreadable, and tests
 * cover that path along with the DB-backed job queue.
 */
class AdminPrerenderControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('prerender_jobs')) {
            $this->markTestSkipped('prerender_jobs table not present (migration not run).');
        }
    }

    public function test_summary_requires_auth(): void
    {
        $this->apiGet('/v2/admin/prerender/summary')->assertStatus(401);
    }

    public function test_summary_rejects_non_admin(): void
    {
        Sanctum::actingAs(User::factory()->forTenant($this->testTenantId)->create());
        $this->apiGet('/v2/admin/prerender/summary')->assertStatus(403);
    }

    public function test_summary_allows_admin(): void
    {
        Sanctum::actingAs(User::factory()->forTenant($this->testTenantId)->admin()->create());
        $r = $this->apiGet('/v2/admin/prerender/summary');
        $r->assertStatus(200);
        $r->assertJsonStructure(['data' => [
            'cache_readable', 'total_snapshots', 'expected_count',
            'queued_jobs', 'active_jobs', 'realtime_channel', 'realtime_event',
        ]]);
    }

    public function test_inventory_validates_tenant_slug(): void
    {
        Sanctum::actingAs(User::factory()->forTenant($this->testTenantId)->admin()->create());
        $this->apiGet('/v2/admin/prerender/inventory?tenant=' . urlencode('not!a!slug!'))
            ->assertStatus(400);
    }

    public function test_inspect_rejects_path_traversal(): void
    {
        Sanctum::actingAs(User::factory()->forTenant($this->testTenantId)->admin()->create());
        $this->apiGet('/v2/admin/prerender/inspect?path=' . urlencode('../../etc/passwd'))
            ->assertStatus(404);
    }

    public function test_enqueue_rejects_non_super_admin(): void
    {
        Sanctum::actingAs(User::factory()->forTenant($this->testTenantId)->admin()->create());
        $this->apiPost('/v2/admin/prerender/jobs', ['force' => true])
            ->assertStatus(403);
    }

    public function test_enqueue_creates_a_job_for_super_admin(): void
    {
        Sanctum::actingAs($this->makeSuperAdmin());
        $r = $this->apiPost('/v2/admin/prerender/jobs', [
            'routes' => '/about,/blog',
            'force'  => true,
        ]);
        $r->assertStatus(200);
        $id = $r->json('data.job_id');
        $this->assertGreaterThan(0, $id);
        $this->assertDatabaseHas('prerender_jobs', [
            'id'           => $id,
            'status'       => 'queued',
            'routes'       => '/about,/blog',
            'force_render' => 1,
        ]);
    }

    public function test_enqueue_dedupes_identical_queued_jobs(): void
    {
        Sanctum::actingAs($this->makeSuperAdmin());
        $a = $this->apiPost('/v2/admin/prerender/jobs', ['force' => true])->json('data.job_id');
        $b = $this->apiPost('/v2/admin/prerender/jobs', ['force' => true])->json('data.job_id');
        $this->assertSame($a, $b);
    }

    public function test_enqueue_rejects_bad_route_pattern(): void
    {
        Sanctum::actingAs($this->makeSuperAdmin());
        $this->apiPost('/v2/admin/prerender/jobs', ['routes' => 'no-leading-slash'])
            ->assertStatus(400);
    }

    public function test_cancel_marks_queued_job_cancelled(): void
    {
        Sanctum::actingAs($this->makeSuperAdmin());
        $id = $this->apiPost('/v2/admin/prerender/jobs', ['force' => true])->json('data.job_id');
        $this->apiPost("/v2/admin/prerender/jobs/{$id}/cancel")->assertStatus(200);
        $this->assertSame('cancelled', DB::table('prerender_jobs')->where('id', $id)->value('status'));
    }

    public function test_cancel_409_on_already_running_job(): void
    {
        Sanctum::actingAs($this->makeSuperAdmin());
        $id = $this->apiPost('/v2/admin/prerender/jobs', ['force' => true])->json('data.job_id');
        DB::table('prerender_jobs')->where('id', $id)->update(['status' => 'running']);
        $this->apiPost("/v2/admin/prerender/jobs/{$id}/cancel")->assertStatus(409);
    }

    public function test_jobs_listing_returns_recent_jobs(): void
    {
        Sanctum::actingAs($this->makeSuperAdmin());
        $this->apiPost('/v2/admin/prerender/jobs', ['force' => true]);
        $r = $this->apiGet('/v2/admin/prerender/jobs');
        $r->assertStatus(200);
        $this->assertNotEmpty($r->json('data.items'));
    }

    public function test_metrics_returns_prometheus_text(): void
    {
        Sanctum::actingAs(User::factory()->forTenant($this->testTenantId)->admin()->create());
        $r = $this->apiGet('/v2/admin/prerender/metrics');
        $r->assertStatus(200);
        $r->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
        $body = $r->getContent();
        $this->assertStringContainsString('# TYPE nexus_prerender_snapshots_total gauge', $body);
        $this->assertStringContainsString('nexus_prerender_jobs_total{status="queued"}', $body);
    }

    public function test_realtime_channel_returns_expected_shape(): void
    {
        Sanctum::actingAs(User::factory()->forTenant($this->testTenantId)->admin()->create());
        $r = $this->apiGet('/v2/admin/prerender/realtime-channel');
        $r->assertStatus(200);
        $this->assertSame('private-admin-prerender', $r->json('data.channel'));
        $this->assertSame('job.updated', $r->json('data.event'));
    }

    private function makeSuperAdmin(): User
    {
        return User::factory()
            ->forTenant($this->testTenantId)
            ->admin()
            ->create(['is_super_admin' => true]);
    }
}

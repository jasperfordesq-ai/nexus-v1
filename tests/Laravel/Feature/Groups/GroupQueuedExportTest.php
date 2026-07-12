<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Groups;

use App\Jobs\GenerateGroupDataExport;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

final class GroupQueuedExportTest extends TestCase
{
    use DatabaseTransactions;

    private User $owner;
    private int $groupId;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Queue::fake();
        $this->owner = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $this->groupId = (int) DB::table('groups')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'owner_id' => $this->owner->id,
            'name' => 'Queued export ' . uniqid('', true),
            'description' => 'Queued export integration fixture.',
            'visibility' => 'private',
            'status' => 'active',
            'is_active' => true,
            'cached_member_count' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('group_members')->insert([
            'tenant_id' => $this->testTenantId,
            'group_id' => $this->groupId,
            'user_id' => $this->owner->id,
            'role' => 'owner',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Sanctum::actingAs($this->owner, ['*']);
    }

    public function test_request_is_idempotent_job_generates_private_versioned_download_and_expires(): void
    {
        $first = $this->apiPost("/v2/groups/{$this->groupId}/exports")
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'queued');
        $exportId = (string) $first->json('data.id');

        $second = $this->apiPost("/v2/groups/{$this->groupId}/exports")
            ->assertStatus(202);
        self::assertSame($exportId, $second->json('data.id'));
        Queue::assertPushed(GenerateGroupDataExport::class, 1);

        (new GenerateGroupDataExport($exportId, $this->testTenantId))->handle();
        $row = DB::table('group_data_exports')->where('id', $exportId)->first();
        self::assertNotNull($row);
        self::assertSame('completed', $row->status);
        self::assertNotEmpty($row->storage_path);
        Storage::disk('local')->assertExists($row->storage_path);

        $payload = json_decode(Storage::disk('local')->get($row->storage_path), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('nexus.group-export', $payload['schema']['name']);
        self::assertSame(1, $payload['schema']['version']);

        $this->apiGet("/v2/groups/{$this->groupId}/exports/{$exportId}")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.download_url', "/api/v2/groups/{$this->groupId}/exports/{$exportId}/download");

        $download = $this->apiGet("/v2/groups/{$this->groupId}/exports/{$exportId}/download")
            ->assertStatus(200)
            ->assertHeader('content-type', 'application/json; charset=UTF-8');
        $cacheControl = strtolower((string) $download->headers->get('cache-control'));
        self::assertStringContainsString('private', $cacheControl);
        self::assertStringContainsString('no-store', $cacheControl);
        self::assertStringContainsString('nexus.group-export', $download->streamedContent());

        DB::table('group_data_exports')->where('id', $exportId)->update(['expires_at' => now()->subSecond()]);
        $this->apiGet("/v2/groups/{$this->groupId}/exports/{$exportId}/download")
            ->assertStatus(404);
        Storage::disk('local')->assertMissing($row->storage_path);
        self::assertSame('expired', DB::table('group_data_exports')->where('id', $exportId)->value('status'));
    }

    public function test_export_status_is_private_to_requester_even_for_another_tenant_admin(): void
    {
        $response = $this->apiPost("/v2/groups/{$this->groupId}/exports")->assertStatus(202);
        $exportId = (string) $response->json('data.id');

        $otherAdmin = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
            'role' => 'admin',
        ]);
        Sanctum::actingAs($otherAdmin, ['*']);

        $this->apiGet("/v2/groups/{$this->groupId}/exports/{$exportId}")
            ->assertStatus(404);
        $this->apiGet("/v2/groups/{$this->groupId}/exports/{$exportId}/download")
            ->assertStatus(404);
    }

    public function test_active_processing_lease_blocks_duplicate_worker_and_stale_lease_is_reclaimed(): void
    {
        $response = $this->apiPost("/v2/groups/{$this->groupId}/exports")->assertStatus(202);
        $exportId = (string) $response->json('data.id');
        DB::table('group_data_exports')->where('id', $exportId)->update([
            'status' => 'processing',
            'processing_started_at' => now(),
        ]);

        (new GenerateGroupDataExport($exportId, $this->testTenantId))->handle();
        self::assertSame('processing', DB::table('group_data_exports')->where('id', $exportId)->value('status'));
        self::assertSame(0, (int) DB::table('group_data_exports')->where('id', $exportId)->value('attempts'));

        DB::table('group_data_exports')->where('id', $exportId)->update([
            'processing_started_at' => now()->subMinutes(11),
        ]);
        (new GenerateGroupDataExport($exportId, $this->testTenantId))->handle();

        self::assertSame('completed', DB::table('group_data_exports')->where('id', $exportId)->value('status'));
        self::assertSame(1, (int) DB::table('group_data_exports')->where('id', $exportId)->value('attempts'));
    }
}

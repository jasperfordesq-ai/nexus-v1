<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Services\GroupAuditService;
use App\Models\Group;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature smoke tests for GroupMediaController.
 */
class GroupMediaControllerTest extends TestCase
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

    public function test_index_requires_auth(): void
    {
        $this->apiGet('/v2/groups/1/media')->assertStatus(401);
    }

    public function test_upload_requires_auth(): void
    {
        $this->apiPost('/v2/groups/1/media', [])->assertStatus(401);
    }

    public function test_destroy_requires_auth(): void
    {
        $this->apiDelete('/v2/groups/1/media/1')->assertStatus(401);
    }

    public function test_index_returns_non_5xx_when_authenticated(): void
    {
        $this->authenticatedUser();
        $response = $this->apiGet('/v2/groups/1/media');
        $this->assertNotEquals(401, $response->status(), 'Auth should have passed');
    }

    public function test_upload_and_delete_write_sanitized_media_audits(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $owner = $this->authenticatedUser();
        $group = Group::factory()->forTenant($this->testTenantId)->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_active' => true,
        ]);

        $upload = $this->apiPost("/v2/groups/{$group->id}/media", [
            'file' => UploadedFile::fake()->image('audit-photo.png', 4, 4),
            'caption' => 'Audited media',
        ])->assertCreated();
        $mediaId = (int) $upload->json('data.id');
        self::assertGreaterThan(0, $mediaId);

        $this->apiDelete("/v2/groups/{$group->id}/media/{$mediaId}")->assertOk();

        $audits = DB::table('group_audit_log')
            ->where('group_id', $group->id)
            ->whereIn('action', [
                GroupAuditService::ACTION_MEDIA_UPLOADED,
                GroupAuditService::ACTION_MEDIA_DELETED,
            ])
            ->get()
            ->keyBy('action');
        self::assertCount(2, $audits);
        foreach ($audits as $audit) {
            self::assertSame((int) $owner->id, (int) $audit->user_id);
            $details = json_decode((string) $audit->details, true, 512, JSON_THROW_ON_ERROR);
            self::assertSame($mediaId, (int) $details['media_id']);
            self::assertSame((int) $owner->id, (int) $details['target_user_id']);
            self::assertArrayNotHasKey('file_path', $details);
            self::assertArrayNotHasKey('url', $details);
        }
    }
}

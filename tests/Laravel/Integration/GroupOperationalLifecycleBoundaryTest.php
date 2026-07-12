<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Integration;

use App\Core\TenantContext;
use App\Enums\GroupStatus;
use App\Models\Group;
use App\Models\User;
use App\Services\GroupConfigurationService;
use App\Services\GroupScheduledPostService;
use App\Services\GroupWebhookService;
use App\Services\TenantFeatureConfig;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

final class GroupOperationalLifecycleBoundaryTest extends TestCase
{
    use DatabaseTransactions;

    private User $owner;
    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'features' => json_encode(
                array_merge(TenantFeatureConfig::FEATURE_DEFAULTS, ['groups' => true]),
                JSON_THROW_ON_ERROR,
            ),
        ]);
        TenantContext::setById($this->testTenantId);
        $this->owner = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $this->group = Group::factory()->forTenant($this->testTenantId)->create([
            'owner_id' => $this->owner->id,
            'status' => GroupStatus::Active->value,
            'is_active' => true,
        ]);
        GroupConfigurationService::set(GroupConfigurationService::CONFIG_TAB_ANNOUNCEMENTS, true);
        self::assertTrue(TenantContext::hasFeature('groups'));
        self::assertTrue(GroupConfigurationService::isTabEnabled('announcements'));
        Sanctum::actingAs($this->owner, ['*']);
    }

    protected function tearDown(): void
    {
        Cache::forget('group_config:' . $this->testTenantId);
        parent::tearDown();
    }

    public function test_archived_group_cannot_reach_scheduled_post_or_webhook_operations(): void
    {
        $this->archiveGroup();

        $this->apiGet('/v2/groups/' . $this->group->id . '/scheduled-posts')
            ->assertForbidden()
            ->assertJsonPath('errors.0.code', 'FORBIDDEN');
        $this->apiGet('/v2/groups/' . $this->group->id . '/webhooks')
            ->assertForbidden()
            ->assertJsonPath('errors.0.code', 'FORBIDDEN');
    }

    public function test_due_post_for_archived_group_is_not_published_and_tenant_context_is_restored(): void
    {
        $postId = DB::table('group_scheduled_posts')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'group_id' => $this->group->id,
            'user_id' => $this->owner->id,
            'post_type' => 'announcement',
            'title' => 'Archived scheduled canary',
            'content' => 'This content must never publish after archival.',
            'is_recurring' => false,
            'recurrence_pattern' => null,
            'scheduled_at' => now()->subMinute(),
            'status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->archiveGroup();
        TenantContext::setById($this->testTenantId);

        self::assertSame(0, GroupScheduledPostService::publishDue());
        self::assertSame($this->testTenantId, TenantContext::getId());
        self::assertSame('failed', DB::table('group_scheduled_posts')->where('id', $postId)->value('status'));
        self::assertSame('GROUP_UNAVAILABLE', DB::table('group_scheduled_posts')->where('id', $postId)->value('last_error_code'));
        self::assertFalse(DB::table('group_announcements')
            ->where('tenant_id', $this->testTenantId)
            ->where('group_id', $this->group->id)
            ->where('title', 'Archived scheduled canary')
            ->exists());
    }

    public function test_archived_group_never_fires_existing_webhooks(): void
    {
        DB::table('group_webhooks')->insert([
            'tenant_id' => $this->testTenantId,
            'group_id' => $this->group->id,
            'url' => 'https://example.test/groups-hook',
            'events' => json_encode([GroupWebhookService::EVENT_FILE_UPLOADED], JSON_THROW_ON_ERROR),
            'secret' => null,
            'is_active' => true,
            'failure_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->archiveGroup();
        Http::fake();

        GroupWebhookService::fire(
            (int) $this->group->id,
            GroupWebhookService::EVENT_FILE_UPLOADED,
            ['canary' => true],
        );

        Http::assertNothingSent();
    }

    private function archiveGroup(): void
    {
        DB::table('groups')->where('id', $this->group->id)->update([
            'status' => GroupStatus::Archived->value,
            'is_active' => false,
            'updated_at' => now(),
        ]);
    }
}

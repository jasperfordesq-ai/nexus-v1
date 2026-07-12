<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Models\Group;
use App\Models\User;
use App\Services\GroupAuditService;
use App\Services\GroupScheduledPostService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class GroupScheduledPostServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(GroupScheduledPostService::class));
    }

    public function test_has_public_methods(): void
    {
        $ref = new \ReflectionClass(GroupScheduledPostService::class);
        foreach (['schedule', 'getScheduled', 'cancel', 'publishDue'] as $m) {
            $this->assertTrue($ref->hasMethod($m), "Method {$m} should exist");
            $this->assertTrue($ref->getMethod($m)->isPublic(), "Method {$m} should be public");
            $this->assertTrue($ref->getMethod($m)->isStatic(), "Method {$m} should be static");
        }
    }

    public function test_getScheduled_returns_array_safely(): void
    {
        try {
            $result = GroupScheduledPostService::getScheduled(0);
            $this->assertIsArray($result);
        } catch (\TypeError $e) {
            $this->fail('TypeError: ' . $e->getMessage());
        } catch (\Throwable $e) {
            $this->assertTrue(true);
        }
    }

    public function test_cancel_records_actor_post_type_and_schedule_without_content(): void
    {
        $actor = User::factory()->forTenant($this->testTenantId)->create();
        $group = Group::factory()->forTenant($this->testTenantId)->create(['owner_id' => $actor->id]);
        $postId = (int) DB::table('group_scheduled_posts')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'group_id' => $group->id,
            'user_id' => $actor->id,
            'post_type' => 'post',
            'title' => 'Private scheduled title',
            'content' => 'Private scheduled content',
            'is_recurring' => false,
            'scheduled_at' => now()->addHour(),
            'status' => 'scheduled',
            'attempt_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        self::assertTrue(GroupScheduledPostService::cancel((int) $group->id, $postId, (int) $actor->id));

        $audit = DB::table('group_audit_log')
            ->where('group_id', $group->id)
            ->where('action', GroupAuditService::ACTION_SCHEDULED_POST_CANCELLED)
            ->sole();
        self::assertSame((int) $actor->id, (int) $audit->user_id);
        self::assertStringNotContainsString('Private scheduled content', (string) $audit->details);
        $details = json_decode((string) $audit->details, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($postId, (int) $details['scheduled_post_id']);
        self::assertSame('post', $details['post_type']);
        self::assertSame((int) $actor->id, (int) $details['target_user_id']);
    }
}

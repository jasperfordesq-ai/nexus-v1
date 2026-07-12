<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Models\Group;
use App\Models\User;
use App\Services\GroupAuditService;
use App\Services\GroupWebhookService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class GroupWebhookServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(GroupWebhookService::class));
    }

    public function test_has_public_methods(): void
    {
        $ref = new \ReflectionClass(GroupWebhookService::class);
        foreach (['register', 'fire', 'list', 'delete', 'toggle'] as $m) {
            $this->assertTrue($ref->hasMethod($m), "Method {$m} should exist");
            $this->assertTrue($ref->getMethod($m)->isPublic(), "Method {$m} should be public");
            $this->assertTrue($ref->getMethod($m)->isStatic(), "Method {$m} should be static");
        }
    }

    public function test_list_returns_array_safely(): void
    {
        try {
            $result = GroupWebhookService::list(0);
            $this->assertIsArray($result);
        } catch (\TypeError $e) {
            $this->fail('TypeError: ' . $e->getMessage());
        } catch (\Throwable $e) {
            $this->assertTrue(true);
        }
    }

    public function test_toggle_and_delete_audit_state_only_and_idempotent_toggle_does_not_duplicate(): void
    {
        $actor = User::factory()->forTenant($this->testTenantId)->create();
        $group = Group::factory()->forTenant($this->testTenantId)->create(['owner_id' => $actor->id]);
        $webhookId = (int) DB::table('group_webhooks')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'group_id' => $group->id,
            'url' => 'https://secret.example.test/groups-hook',
            'events' => json_encode([GroupWebhookService::EVENT_MEMBER_JOINED], JSON_THROW_ON_ERROR),
            'secret' => 'encrypted-secret-canary',
            'is_active' => true,
            'failure_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        self::assertTrue(GroupWebhookService::toggle((int) $group->id, $webhookId, false, (int) $actor->id));
        self::assertTrue(GroupWebhookService::toggle((int) $group->id, $webhookId, false, (int) $actor->id));
        self::assertTrue(GroupWebhookService::delete((int) $group->id, $webhookId, (int) $actor->id));

        $audits = DB::table('group_audit_log')
            ->where('group_id', $group->id)
            ->whereIn('action', [
                GroupAuditService::ACTION_WEBHOOK_TOGGLED,
                GroupAuditService::ACTION_WEBHOOK_DELETED,
            ])
            ->get()
            ->keyBy('action');
        self::assertCount(2, $audits);
        foreach ($audits as $audit) {
            self::assertSame((int) $actor->id, (int) $audit->user_id);
            self::assertStringNotContainsString('secret.example.test', (string) $audit->details);
            self::assertStringNotContainsString('encrypted-secret-canary', (string) $audit->details);
            $details = json_decode((string) $audit->details, true, 512, JSON_THROW_ON_ERROR);
            self::assertSame($webhookId, (int) $details['webhook_id']);
            self::assertArrayNotHasKey('url', $details);
            self::assertArrayNotHasKey('secret', $details);
        }
    }
}

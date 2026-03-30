<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Integration;

use App\Core\TenantContext;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationDispatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;
use Tests\Laravel\Traits\ActsAsMember;

/**
 * Integration test: verify the notification frequency preference cascade.
 *
 * The NotificationDispatcher resolves frequency via a hierarchy:
 *   Thread setting → Group setting → Global setting → Tenant config default → 'daily'
 *
 * These tests verify that the cascade behaves correctly, including the
 * default fallback to 'daily' when no preferences are set.
 */
class NotificationFrequencyTest extends TestCase
{
    use DatabaseTransactions;
    use ActsAsMember;

    // =========================================================================
    // Default frequency
    // =========================================================================

    public function test_default_frequency_is_daily_when_no_preferences_set(): void
    {
        $user = $this->createMember();

        // Ensure no notification_settings rows exist for this user
        DB::table('notification_settings')
            ->where('user_id', $user->id)
            ->delete();

        // Ensure the tenant has no custom default_frequency in configuration
        DB::table('tenants')
            ->where('id', $this->testTenantId)
            ->update(['configuration' => json_encode([])]);

        // Re-set tenant context so TenantContext::get() picks up the cleared config
        TenantContext::setById($this->testTenantId);

        // Dispatch a notification — it should use 'daily' as default frequency
        // We verify by checking what frequency was written to notification_queue
        $this->dispatchTestNotification($user->id);

        $queued = DB::table('notification_queue')
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($queued, 'A notification should have been queued');
        $this->assertEquals('daily', $queued->frequency,
            'Default frequency should be daily when no preferences are set');
    }

    public function test_global_frequency_setting_overrides_default(): void
    {
        $user = $this->createMember();

        // Set a global frequency preference for this user
        DB::table('notification_settings')->insertOrIgnore([
            'user_id'      => $user->id,
            'context_type' => 'global',
            'context_id'   => 0,
            'frequency'    => 'weekly',
        ]);

        $this->dispatchTestNotification($user->id);

        $queued = DB::table('notification_queue')
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($queued);
        $this->assertEquals('weekly', $queued->frequency,
            'Global setting should override the default daily frequency');
    }

    public function test_group_frequency_setting_overrides_global(): void
    {
        $user = $this->createMember();

        // Set a global frequency
        DB::table('notification_settings')->insertOrIgnore([
            'user_id'      => $user->id,
            'context_type' => 'global',
            'context_id'   => 0,
            'frequency'    => 'weekly',
        ]);

        // Set a group-level frequency that overrides global
        $groupId = $this->seedTestGroup();
        DB::table('notification_settings')->insertOrIgnore([
            'user_id'      => $user->id,
            'context_type' => 'group',
            'context_id'   => $groupId,
            'frequency'    => 'instant',
        ]);

        $this->dispatchTestNotification($user->id, 'group', $groupId);

        $queued = DB::table('notification_queue')
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($queued);
        $this->assertEquals('instant', $queued->frequency,
            'Group-level setting should override the global frequency');
    }

    public function test_thread_frequency_setting_overrides_group(): void
    {
        $user = $this->createMember();
        $groupId = $this->seedTestGroup();

        // Set a group-level frequency
        DB::table('notification_settings')->insertOrIgnore([
            'user_id'      => $user->id,
            'context_type' => 'group',
            'context_id'   => $groupId,
            'frequency'    => 'weekly',
        ]);

        // Create a thread (group discussion) in the group
        $threadId = DB::table('group_discussions')->insertGetId([
            'group_id'    => $groupId,
            'tenant_id'   => $this->testTenantId,
            'user_id'     => $user->id,
            'title'       => 'Test Thread',
            'content'     => 'Thread body for notification test',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // Set a thread-level frequency that overrides group
        DB::table('notification_settings')->insertOrIgnore([
            'user_id'      => $user->id,
            'context_type' => 'thread',
            'context_id'   => $threadId,
            'frequency'    => 'off',
        ]);

        $this->dispatchTestNotification($user->id, 'thread', $threadId);

        // When frequency is 'off', no notification should be queued
        $queued = DB::table('notification_queue')
            ->where('user_id', $user->id)
            ->where('activity_type', 'new_reply')
            ->orderByDesc('id')
            ->first();

        $this->assertNull($queued,
            'Thread-level "off" setting should prevent any notification from being queued');
    }

    public function test_thread_falls_back_to_group_when_no_thread_setting(): void
    {
        $user = $this->createMember();
        $groupId = $this->seedTestGroup();

        // Set only a group-level frequency (no thread-level setting)
        DB::table('notification_settings')->insertOrIgnore([
            'user_id'      => $user->id,
            'context_type' => 'group',
            'context_id'   => $groupId,
            'frequency'    => 'instant',
        ]);

        // Create a thread in the group
        $threadId = DB::table('group_discussions')->insertGetId([
            'group_id'    => $groupId,
            'tenant_id'   => $this->testTenantId,
            'user_id'     => $user->id,
            'title'       => 'Fallback Test Thread',
            'content'     => 'Thread body',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // No thread-specific setting — should fall back to group
        $this->dispatchTestNotification($user->id, 'thread', $threadId);

        $queued = DB::table('notification_queue')
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($queued);
        $this->assertEquals('instant', $queued->frequency,
            'Thread without its own setting should fall back to group frequency');
    }

    public function test_organizer_rule_defaults_to_instant_for_new_topic(): void
    {
        $user = $this->createMember();

        // Ensure no notification settings exist
        DB::table('notification_settings')
            ->where('user_id', $user->id)
            ->delete();

        // Dispatch with isOrganizer=true and activityType=new_topic
        NotificationDispatcher::dispatch(
            $user->id,
            'global',
            0,
            'new_topic',
            'Test organizer notification',
            '/test/link',
            null,
            true // isOrganizer
        );

        $queued = DB::table('notification_queue')
            ->where('user_id', $user->id)
            ->where('activity_type', 'new_topic')
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($queued);
        $this->assertEquals('instant', $queued->frequency,
            'Organizer rule should default to instant for new_topic when no setting exists');
    }

    public function test_tenant_config_default_frequency_is_used_as_global_fallback(): void
    {
        $user = $this->createMember();

        // Ensure no notification_settings rows for this user
        DB::table('notification_settings')
            ->where('user_id', $user->id)
            ->delete();

        // Set tenant configuration with a custom default frequency
        DB::table('tenants')
            ->where('id', $this->testTenantId)
            ->update(['configuration' => json_encode([
                'notifications' => ['default_frequency' => 'weekly'],
            ])]);

        // Re-set tenant context to pick up the new configuration
        TenantContext::setById($this->testTenantId);

        $this->dispatchTestNotification($user->id);

        $queued = DB::table('notification_queue')
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($queued);
        $this->assertEquals('weekly', $queued->frequency,
            'Tenant config default_frequency should be used when no user-level setting exists');
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Dispatch a test notification through NotificationDispatcher.
     */
    private function dispatchTestNotification(
        int $userId,
        string $contextType = 'global',
        int $contextId = 0,
    ): void {
        NotificationDispatcher::dispatch(
            $userId,
            $contextType,
            $contextId,
            'new_reply',
            'Test notification content',
            '/test/notification-link',
            null,
            false
        );
    }

    /**
     * Seed a test group and return its ID.
     */
    private function seedTestGroup(): int
    {
        return DB::table('groups')->insertGetId([
            'tenant_id'   => $this->testTenantId,
            'name'        => 'Test Group ' . uniqid(),
            'description' => 'Group for notification frequency tests',
            'status'      => 'active',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }
}

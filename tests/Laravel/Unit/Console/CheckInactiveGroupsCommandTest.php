<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Console;

use App\Core\TenantContext;
use App\Enums\GroupStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

final class CheckInactiveGroupsCommandTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99733;

    private int $ownerId;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-07-11 12:00:00'));
        Queue::fake();

        DB::table('tenants')->updateOrInsert(
            ['id' => self::TENANT_ID],
            [
                'name' => 'Lifecycle Test Tenant',
                'slug' => 'lifecycle-test-99733',
                'domain' => null,
                'is_active' => true,
                'depth' => 0,
                'allows_subtenants' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
        TenantContext::setById(self::TENANT_ID);

        $this->ownerId = (int) DB::table('users')->insertGetId([
            'tenant_id' => self::TENANT_ID,
            'name' => 'Lifecycle Owner',
            'email' => 'lifecycle-owner-99733@example.com',
            'role' => 'member',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_command_succeeds_without_groups(): void
    {
        $this->artisan('groups:check-inactive', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(0);
    }

    public function test_recent_activity_keeps_an_active_group_active(): void
    {
        $groupId = $this->insertGroup();
        $this->insertMemberActivity($groupId, now()->subDays(10));

        $this->runCommand();

        $this->assertLifecycle($groupId, GroupStatus::Active, true);
    }

    public function test_91_day_old_activity_transitions_to_read_only_dormant(): void
    {
        $groupId = $this->insertGroup();
        $this->insertMemberActivity($groupId, now()->subDays(91));

        $this->runCommand();

        $this->assertLifecycle($groupId, GroupStatus::Dormant, false);
    }

    public function test_181_day_old_activity_transitions_to_archived(): void
    {
        $groupId = $this->insertGroup();
        $this->insertMemberActivity($groupId, now()->subDays(181));

        $this->runCommand();

        $this->assertLifecycle($groupId, GroupStatus::Archived, false);
    }

    public function test_group_without_child_activity_uses_created_at(): void
    {
        $newGroup = $this->insertGroup(['created_at' => now()->subDays(5)]);
        $oldGroup = $this->insertGroup(['created_at' => now()->subDays(181)]);

        $this->runCommand();

        $this->assertLifecycle($newGroup, GroupStatus::Active, true);
        $this->assertLifecycle($oldGroup, GroupStatus::Archived, false);
    }

    public function test_dormant_group_remains_in_scan_and_advances_to_archived(): void
    {
        $groupId = $this->insertGroup([
            'status' => GroupStatus::Dormant->value,
            'is_active' => false,
        ]);
        $this->insertMemberActivity($groupId, now()->subDays(181));

        $this->runCommand();

        $this->assertLifecycle($groupId, GroupStatus::Archived, false);
    }

    public function test_archived_group_is_not_reprocessed(): void
    {
        $groupId = $this->insertGroup([
            'status' => GroupStatus::Archived->value,
            'is_active' => false,
            'updated_at' => now()->subDays(30),
        ]);
        $before = DB::table('groups')->where('id', $groupId)->value('updated_at');

        $this->runCommand();

        self::assertSame($before, DB::table('groups')->where('id', $groupId)->value('updated_at'));
        $this->assertLifecycle($groupId, GroupStatus::Archived, false);
    }

    public function test_exact_180_day_boundary_is_dormant_not_archived(): void
    {
        $groupId = $this->insertGroup();
        $this->insertMemberActivity($groupId, now()->subDays(180));

        $this->runCommand();

        $this->assertLifecycle($groupId, GroupStatus::Dormant, false);
    }

    public function test_tenant_option_does_not_process_another_tenant(): void
    {
        $otherTenantId = self::TENANT_ID + 1;
        DB::table('tenants')->updateOrInsert(
            ['id' => $otherTenantId],
            [
                'name' => 'Other Lifecycle Tenant',
                'slug' => 'other-lifecycle-99734',
                'is_active' => true,
                'depth' => 0,
                'allows_subtenants' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
        $otherOwnerId = (int) DB::table('users')->insertGetId([
            'tenant_id' => $otherTenantId,
            'name' => 'Other Owner',
            'email' => 'other-lifecycle-owner-99734@example.com',
            'role' => 'member',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherGroupId = (int) DB::table('groups')->insertGetId([
            'tenant_id' => $otherTenantId,
            'owner_id' => $otherOwnerId,
            'name' => 'Other Tenant Group',
            'visibility' => 'public',
            'status' => GroupStatus::Active->value,
            'is_active' => true,
            'created_at' => now()->subDays(181),
            'updated_at' => now()->subDays(181),
        ]);

        $this->runCommand();

        $stored = DB::table('groups')->where('id', $otherGroupId)->first();
        self::assertSame(GroupStatus::Active->value, $stored->status);
        self::assertTrue((bool) $stored->is_active);
    }

    private function runCommand(): void
    {
        $this->artisan('groups:check-inactive', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(0);
    }

    private function insertGroup(array $overrides = []): int
    {
        $attributes = array_merge([
            'tenant_id' => self::TENANT_ID,
            'owner_id' => $this->ownerId,
            'name' => 'Lifecycle Group ' . uniqid('', true),
            'visibility' => 'public',
            'status' => GroupStatus::Active->value,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        return (int) DB::table('groups')->insertGetId($attributes);
    }

    private function insertMemberActivity(int $groupId, Carbon $createdAt): void
    {
        DB::table('group_members')->insert([
            'tenant_id' => self::TENANT_ID,
            'group_id' => $groupId,
            'user_id' => $this->ownerId,
            'role' => 'owner',
            'status' => 'active',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function assertLifecycle(int $groupId, GroupStatus $status, bool $isActive): void
    {
        $stored = DB::table('groups')->where('id', $groupId)->first();
        self::assertSame($status->value, $stored->status);
        self::assertSame($isActive, (bool) $stored->is_active);
    }
}

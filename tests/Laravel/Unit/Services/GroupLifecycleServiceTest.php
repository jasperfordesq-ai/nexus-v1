<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\GroupLifecycleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GroupLifecycleServiceTest extends TestCase
{
    public function test_status_constants_define_full_lifecycle(): void
    {
        $this->assertEquals('draft', GroupLifecycleService::STATUS_DRAFT);
        $this->assertEquals('pending_approval', GroupLifecycleService::STATUS_PENDING);
        $this->assertEquals('active', GroupLifecycleService::STATUS_ACTIVE);
        $this->assertEquals('dormant', GroupLifecycleService::STATUS_DORMANT);
        $this->assertEquals('archived', GroupLifecycleService::STATUS_ARCHIVED);
        $this->assertEquals('deleted', GroupLifecycleService::STATUS_DELETED);
    }

    public function test_getStatus_returns_null_when_group_not_found(): void
    {
        DB::shouldReceive('table->where->where->first')
            ->once()
            ->andReturn(null);

        $result = GroupLifecycleService::getStatus(999);
        $this->assertNull($result);
    }

    public function test_getStatus_returns_active_when_is_active_true(): void
    {
        DB::shouldReceive('table->where->where->first')
            ->once()
            ->andReturn((object) ['id' => 1, 'is_active' => true]);

        $result = GroupLifecycleService::getStatus(1);
        $this->assertEquals('active', $result);
    }

    public function test_getStatus_returns_archived_when_is_active_false(): void
    {
        DB::shouldReceive('table->where->where->first')
            ->once()
            ->andReturn((object) ['id' => 1, 'is_active' => false]);

        $result = GroupLifecycleService::getStatus(1);
        $this->assertEquals('archived', $result);
    }

    public function test_transition_returns_false_for_invalid_status(): void
    {
        $result = GroupLifecycleService::transition(1, 'nonexistent_status', 10);
        $this->assertFalse($result);
    }

    public function test_transition_returns_false_when_group_not_found(): void
    {
        DB::shouldReceive('table->where->where->update')
            ->once()
            ->andReturn(0);

        $result = GroupLifecycleService::transition(999, 'active', 10);
        $this->assertFalse($result);
    }

    public function test_transition_to_active_sets_is_active_true(): void
    {
        DB::shouldReceive('table->where->where->update')
            ->once()
            ->withArgs(function ($args) {
                // is_active should be true for 'active' status
                return $args['is_active'] === true;
            })
            ->andReturn(1);

        // GroupAuditService::log will be called — mock it via DB
        DB::shouldReceive('table->insertGetId')->andReturn(1);

        $result = GroupLifecycleService::transition(1, 'active', 10, 'Reactivating');
        $this->assertTrue($result);
    }

    public function test_archive_delegates_to_transition(): void
    {
        // archive calls transition(groupId, 'archived', performedBy, reason)
        // 'archived' sets is_active = false
        DB::shouldReceive('table->where->where->update')
            ->once()
            ->withArgs(function ($args) {
                return $args['is_active'] === false;
            })
            ->andReturn(1);

        DB::shouldReceive('table->insertGetId')->andReturn(1);

        $result = GroupLifecycleService::archive(1, 10, 'Inactive group');
        $this->assertTrue($result);
    }

    public function test_transferOwnership_returns_false_when_new_owner_not_member(): void
    {
        DB::shouldReceive('table->where->where->where->exists')
            ->once()
            ->andReturn(false);

        $result = GroupLifecycleService::transferOwnership(1, 99, 10);
        $this->assertFalse($result);
    }

    public function test_transferOwnership_returns_false_when_group_not_found(): void
    {
        // New owner is a member
        DB::shouldReceive('table->where->where->where->exists')
            ->once()
            ->andReturn(true);

        // Group not found
        DB::shouldReceive('table->where->where->first')
            ->once()
            ->andReturn(null);

        $result = GroupLifecycleService::transferOwnership(1, 5, 10);
        $this->assertFalse($result);
    }

    public function test_bulkArchive_returns_affected_count(): void
    {
        DB::shouldReceive('table->where->whereIn->where->update')
            ->once()
            ->andReturn(3);

        $result = GroupLifecycleService::bulkArchive([1, 2, 3], 10);
        $this->assertEquals(3, $result);
    }

    public function test_bulkUnarchive_returns_affected_count(): void
    {
        DB::shouldReceive('table->where->whereIn->where->update')
            ->once()
            ->andReturn(2);

        $result = GroupLifecycleService::bulkUnarchive([4, 5], 10);
        $this->assertEquals(2, $result);
    }
}

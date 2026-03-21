<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\GroupExchangeService;
use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * GroupExchangeServiceTest — tests for group exchange CRUD, participants, and split calculation.
 */
class GroupExchangeServiceTest extends TestCase
{
    private GroupExchangeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GroupExchangeService();
        TenantContext::setById(1);
    }

    // =========================================================================
    // create
    // =========================================================================

    public function testCreateReturnsExchangeId(): void
    {
        DB::shouldReceive('table')->once()->andReturnSelf();
        DB::shouldReceive('insertGetId')->once()->andReturn(42);

        $result = $this->service->create(1, [
            'title' => 'Test Exchange',
            'description' => 'A test',
            'split_type' => 'equal',
            'total_hours' => 10.0,
        ]);

        $this->assertEquals(42, $result);
    }

    public function testCreateSetsDefaultStatusToDraft(): void
    {
        DB::shouldReceive('table')->once()->andReturnSelf();
        DB::shouldReceive('insertGetId')
            ->once()
            ->withArgs(function ($data) {
                return $data['status'] === 'draft';
            })
            ->andReturn(1);

        $this->service->create(1, ['title' => 'Test']);
    }

    // =========================================================================
    // get
    // =========================================================================

    public function testGetReturnsNullForNonExistent(): void
    {
        DB::shouldReceive('table')->once()->andReturnSelf();
        DB::shouldReceive('where')->twice()->andReturnSelf();
        DB::shouldReceive('first')->once()->andReturn(null);

        $result = $this->service->get(999);
        $this->assertNull($result);
    }

    public function testGetReturnsStructuredArrayWithParticipants(): void
    {
        $exchange = (object) [
            'id' => 1,
            'tenant_id' => 1,
            'title' => 'Test',
            'description' => 'Desc',
            'organizer_id' => 10,
            'listing_id' => null,
            'status' => 'draft',
            'split_type' => 'equal',
            'total_hours' => 10.0,
            'broker_id' => null,
            'broker_notes' => null,
            'completed_at' => null,
            'created_at' => '2026-01-01',
            'updated_at' => '2026-01-01',
        ];

        $participants = collect([
            (object) [
                'participant_id' => 1,
                'user_id' => 10,
                'role' => 'provider',
                'hours' => 5.0,
                'weight' => 1.0,
                'confirmed' => 0,
                'confirmed_at' => null,
                'notes' => null,
                'created_at' => '2026-01-01',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'avatar_url' => null,
            ],
        ]);

        DB::shouldReceive('table')->once()->andReturnSelf();
        DB::shouldReceive('where')->twice()->andReturnSelf();
        DB::shouldReceive('first')->once()->andReturn($exchange);

        DB::shouldReceive('table')->once()->andReturnSelf();
        DB::shouldReceive('join')->once()->andReturnSelf();
        DB::shouldReceive('where')->once()->andReturnSelf();
        DB::shouldReceive('select')->once()->andReturnSelf();
        DB::shouldReceive('get')->once()->andReturn($participants);

        $result = $this->service->get(1);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('participants', $result);
        $this->assertCount(1, $result['participants']);
        $this->assertEquals('John Doe', $result['participants'][0]['name']);
    }

    // =========================================================================
    // addParticipant
    // =========================================================================

    public function testAddParticipantReturnsTrueOnSuccess(): void
    {
        DB::shouldReceive('table')->once()->andReturnSelf();
        DB::shouldReceive('where')->times(3)->andReturnSelf();
        DB::shouldReceive('exists')->once()->andReturn(false);

        DB::shouldReceive('table')->once()->andReturnSelf();
        DB::shouldReceive('insert')->once()->andReturn(true);

        $result = $this->service->addParticipant(1, 10, 'provider', 5.0);
        $this->assertTrue($result);
    }

    public function testAddParticipantReturnsFalseIfAlreadyExists(): void
    {
        DB::shouldReceive('table')->once()->andReturnSelf();
        DB::shouldReceive('where')->times(3)->andReturnSelf();
        DB::shouldReceive('exists')->once()->andReturn(true);

        $result = $this->service->addParticipant(1, 10, 'provider');
        $this->assertFalse($result);
    }

    // =========================================================================
    // removeParticipant
    // =========================================================================

    public function testRemoveParticipantReturnsTrueWhenDeleted(): void
    {
        DB::shouldReceive('table')->once()->andReturnSelf();
        DB::shouldReceive('where')->twice()->andReturnSelf();
        DB::shouldReceive('delete')->once()->andReturn(1);

        $this->assertTrue($this->service->removeParticipant(1, 10));
    }

    public function testRemoveParticipantReturnsFalseWhenNotFound(): void
    {
        DB::shouldReceive('table')->once()->andReturnSelf();
        DB::shouldReceive('where')->twice()->andReturnSelf();
        DB::shouldReceive('delete')->once()->andReturn(0);

        $this->assertFalse($this->service->removeParticipant(1, 999));
    }

    // =========================================================================
    // calculateSplit — equal
    // =========================================================================

    public function testCalculateSplitReturnsEmptyForNonExistentExchange(): void
    {
        DB::shouldReceive('table')->once()->andReturnSelf();
        DB::shouldReceive('where')->twice()->andReturnSelf();
        DB::shouldReceive('first')->once()->andReturn(null);

        $result = $this->service->calculateSplit(999);
        $this->assertEmpty($result);
    }

    public function testCalculateSplitEqualDistributesEvenly(): void
    {
        $exchange = (object) [
            'id' => 1,
            'total_hours' => 10.0,
            'split_type' => 'equal',
        ];

        $participants = collect([
            (object) ['user_id' => 10, 'role' => 'provider', 'hours' => 0, 'weight' => 1.0],
            (object) ['user_id' => 20, 'role' => 'provider', 'hours' => 0, 'weight' => 1.0],
        ]);

        DB::shouldReceive('table')->once()->andReturnSelf();
        DB::shouldReceive('where')->twice()->andReturnSelf();
        DB::shouldReceive('first')->once()->andReturn($exchange);

        DB::shouldReceive('table')->once()->andReturnSelf();
        DB::shouldReceive('where')->once()->andReturnSelf();
        DB::shouldReceive('get')->once()->andReturn($participants);

        $result = $this->service->calculateSplit(1);

        $this->assertCount(2, $result);
        $this->assertEquals(5.0, $result[0]['hours']);
        $this->assertEquals(5.0, $result[1]['hours']);
    }

    // =========================================================================
    // calculateSplit — custom
    // =========================================================================

    public function testCalculateSplitCustomUsesPresetHours(): void
    {
        $exchange = (object) [
            'id' => 1,
            'total_hours' => 10.0,
            'split_type' => 'custom',
        ];

        $participants = collect([
            (object) ['user_id' => 10, 'role' => 'provider', 'hours' => 7.0, 'weight' => 1.0],
            (object) ['user_id' => 20, 'role' => 'receiver', 'hours' => 3.0, 'weight' => 1.0],
        ]);

        DB::shouldReceive('table')->once()->andReturnSelf();
        DB::shouldReceive('where')->twice()->andReturnSelf();
        DB::shouldReceive('first')->once()->andReturn($exchange);

        DB::shouldReceive('table')->once()->andReturnSelf();
        DB::shouldReceive('where')->once()->andReturnSelf();
        DB::shouldReceive('get')->once()->andReturn($participants);

        $result = $this->service->calculateSplit(1);

        $this->assertCount(2, $result);
        $this->assertEquals(7.0, $result[0]['hours']);
        $this->assertEquals(3.0, $result[1]['hours']);
    }

    // =========================================================================
    // calculateSplit — weighted
    // =========================================================================

    public function testCalculateSplitWeightedDistributesByWeight(): void
    {
        $exchange = (object) [
            'id' => 1,
            'total_hours' => 12.0,
            'split_type' => 'weighted',
        ];

        // Two providers with weights 1 and 2 (total weight 3)
        $participants = collect([
            (object) ['user_id' => 10, 'role' => 'provider', 'hours' => 0, 'weight' => 1.0],
            (object) ['user_id' => 20, 'role' => 'provider', 'hours' => 0, 'weight' => 2.0],
        ]);

        DB::shouldReceive('table')->once()->andReturnSelf();
        DB::shouldReceive('where')->twice()->andReturnSelf();
        DB::shouldReceive('first')->once()->andReturn($exchange);

        DB::shouldReceive('table')->once()->andReturnSelf();
        DB::shouldReceive('where')->once()->andReturnSelf();
        DB::shouldReceive('get')->once()->andReturn($participants);

        $result = $this->service->calculateSplit(1);

        $this->assertCount(2, $result);
        $this->assertEquals(4.0, $result[0]['hours']); // weight 1/3 * 12 = 4
        $this->assertEquals(8.0, $result[1]['hours']); // weight 2/3 * 12 = 8
    }

    // =========================================================================
    // update
    // =========================================================================

    public function testUpdateReturnsTrueWhenNoChanges(): void
    {
        $result = $this->service->update(1, []);
        $this->assertTrue($result);
    }

    public function testUpdateFiltersDisallowedFields(): void
    {
        DB::shouldReceive('table')->once()->andReturnSelf();
        DB::shouldReceive('where')->twice()->andReturnSelf();
        DB::shouldReceive('update')
            ->once()
            ->withArgs(function ($data) {
                return isset($data['title']) && !isset($data['hacker_field']);
            })
            ->andReturn(1);

        $this->service->update(1, ['title' => 'New', 'hacker_field' => 'evil']);
    }

    // =========================================================================
    // updateStatus
    // =========================================================================

    public function testUpdateStatusSetsCompletedAtWhenCompleted(): void
    {
        DB::shouldReceive('table')->once()->andReturnSelf();
        DB::shouldReceive('where')->twice()->andReturnSelf();
        DB::shouldReceive('update')
            ->once()
            ->withArgs(function ($data) {
                return $data['status'] === 'completed' && isset($data['completed_at']);
            })
            ->andReturn(1);

        $this->assertTrue($this->service->updateStatus(1, 'completed'));
    }

    public function testUpdateStatusDoesNotSetCompletedAtForOtherStatuses(): void
    {
        DB::shouldReceive('table')->once()->andReturnSelf();
        DB::shouldReceive('where')->twice()->andReturnSelf();
        DB::shouldReceive('update')
            ->once()
            ->withArgs(function ($data) {
                return $data['status'] === 'active' && !isset($data['completed_at']);
            })
            ->andReturn(1);

        $this->assertTrue($this->service->updateStatus(1, 'active'));
    }

    // =========================================================================
    // confirmParticipation
    // =========================================================================

    public function testConfirmParticipationReturnsTrueWhenUpdated(): void
    {
        DB::shouldReceive('table')->once()->andReturnSelf();
        DB::shouldReceive('where')->times(3)->andReturnSelf();
        DB::shouldReceive('update')->once()->andReturn(1);

        $this->assertTrue($this->service->confirmParticipation(1, 10));
    }

    public function testConfirmParticipationReturnsFalseWhenAlreadyConfirmed(): void
    {
        DB::shouldReceive('table')->once()->andReturnSelf();
        DB::shouldReceive('where')->times(3)->andReturnSelf();
        DB::shouldReceive('update')->once()->andReturn(0);

        $this->assertFalse($this->service->confirmParticipation(1, 10));
    }

    // =========================================================================
    // complete
    // =========================================================================

    public function testCompleteReturnsErrorForNonExistentExchange(): void
    {
        DB::shouldReceive('table')->once()->andReturnSelf();
        DB::shouldReceive('where')->twice()->andReturnSelf();
        DB::shouldReceive('first')->once()->andReturn(null);

        $result = $this->service->complete(999);
        $this->assertFalse($result['success']);
        $this->assertEquals('Exchange not found', $result['error']);
    }

    public function testCompleteReturnsErrorIfAlreadyCompleted(): void
    {
        $exchange = (object) ['id' => 1, 'status' => 'completed'];

        DB::shouldReceive('table')->once()->andReturnSelf();
        DB::shouldReceive('where')->twice()->andReturnSelf();
        DB::shouldReceive('first')->once()->andReturn($exchange);

        $result = $this->service->complete(1);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already completed', $result['error']);
    }

    public function testCompleteReturnsErrorIfCancelled(): void
    {
        $exchange = (object) ['id' => 1, 'status' => 'cancelled'];

        DB::shouldReceive('table')->once()->andReturnSelf();
        DB::shouldReceive('where')->twice()->andReturnSelf();
        DB::shouldReceive('first')->once()->andReturn($exchange);

        $result = $this->service->complete(1);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('cancelled', $result['error']);
    }

    public function testCompleteReturnsErrorIfUnconfirmedParticipants(): void
    {
        $exchange = (object) ['id' => 1, 'status' => 'active'];

        DB::shouldReceive('table')->once()->andReturnSelf();
        DB::shouldReceive('where')->twice()->andReturnSelf();
        DB::shouldReceive('first')->once()->andReturn($exchange);

        DB::shouldReceive('table')->once()->andReturnSelf();
        DB::shouldReceive('where')->twice()->andReturnSelf();
        DB::shouldReceive('count')->once()->andReturn(2);

        $result = $this->service->complete(1);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Not all participants', $result['error']);
    }
}

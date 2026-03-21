<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\BrokerMessageVisibilityService;
use App\Services\BrokerControlConfigService;
use App\Models\BrokerMessageCopy;
use App\Models\Message;
use Illuminate\Support\Facades\DB;
use Mockery;

class BrokerMessageVisibilityServiceTest extends TestCase
{
    private BrokerMessageVisibilityService $service;
    private $mockConfig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockConfig = Mockery::mock(BrokerControlConfigService::class);
        $this->service = new BrokerMessageVisibilityService($this->mockConfig);
    }

    public function test_constants_defined(): void
    {
        $this->assertSame('first_contact', BrokerMessageVisibilityService::REASON_FIRST_CONTACT);
        $this->assertSame('high_risk_listing', BrokerMessageVisibilityService::REASON_HIGH_RISK_LISTING);
        $this->assertSame('new_member', BrokerMessageVisibilityService::REASON_NEW_MEMBER);
        $this->assertSame('flagged_user', BrokerMessageVisibilityService::REASON_FLAGGED_USER);
        $this->assertSame('random_sample', BrokerMessageVisibilityService::REASON_MONITORING);
    }

    public function test_shouldCopyMessage_returns_null_when_visibility_disabled(): void
    {
        $this->mockConfig->shouldReceive('isBrokerVisibilityEnabled')->andReturn(false);

        $result = $this->service->shouldCopyMessage(1, 2);
        $this->assertNull($result);
    }

    public function test_shouldCopyMessage_returns_flagged_user_when_under_monitoring(): void
    {
        $this->mockConfig->shouldReceive('isBrokerVisibilityEnabled')->andReturn(true);

        DB::shouldReceive('table')->with('user_messaging_restrictions')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) [
            'under_monitoring' => true,
            'monitoring_expires_at' => null,
        ]);

        $result = $this->service->shouldCopyMessage(1, 2);
        $this->assertSame('flagged_user', $result);
    }

    public function test_copyMessageForBroker_returns_null_when_message_not_found(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_copyMessageForBroker_returns_null_when_already_copied(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_markAsReviewed_returns_false_when_not_found(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_markAsReviewed_returns_true_on_success(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_countUnreviewed_returns_integer(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_isMessagingDisabledForUser_returns_false_when_no_restriction(): void
    {
        DB::shouldReceive('table')->with('user_messaging_restrictions')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturnNull();

        $result = $this->service->isMessagingDisabledForUser(1);
        $this->assertFalse($result);
    }

    public function test_getUserRestrictionStatus_returns_defaults_when_no_record(): void
    {
        DB::shouldReceive('table')->with('user_messaging_restrictions')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturnNull();

        $result = $this->service->getUserRestrictionStatus(1);
        $this->assertFalse($result['messaging_disabled']);
        $this->assertFalse($result['under_monitoring']);
        $this->assertNull($result['restriction_reason']);
    }
}

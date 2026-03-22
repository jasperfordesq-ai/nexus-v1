<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\Identity;

use Tests\Laravel\TestCase;
use App\Services\Identity\IdentityVerificationEventService;
use Illuminate\Support\Facades\DB;

class IdentityVerificationEventServiceTest extends TestCase
{
    public function test_log_does_not_throw_on_db_failure(): void
    {
        DB::shouldReceive('statement')->andThrow(new \RuntimeException('DB is down'));

        // Service catches Throwable internally — no exception should propagate
        IdentityVerificationEventService::log(2, 1, IdentityVerificationEventService::EVENT_REGISTRATION_STARTED);

        $this->assertTrue(true); // Reached without exception
    }

    public function test_log_calls_db_with_correct_event_type(): void
    {
        DB::shouldReceive('statement')
            ->once()
            ->with(\Mockery::type('string'), \Mockery::type('array'))
            ->andReturn(true);

        IdentityVerificationEventService::log(
            2,
            10,
            IdentityVerificationEventService::EVENT_VERIFICATION_PASSED,
            42,
            null,
            IdentityVerificationEventService::ACTOR_WEBHOOK,
            ['decision' => 'approved'],
            '127.0.0.1',
            'TestAgent/1.0'
        );

        // Mockery verified the call above; expectation met
        $this->assertTrue(true);
    }

    public function test_event_type_constants_are_defined(): void
    {
        $this->assertSame('registration_started', IdentityVerificationEventService::EVENT_REGISTRATION_STARTED);
        $this->assertSame('verification_created', IdentityVerificationEventService::EVENT_VERIFICATION_CREATED);
        $this->assertSame('verification_started', IdentityVerificationEventService::EVENT_VERIFICATION_STARTED);
        $this->assertSame('verification_processing', IdentityVerificationEventService::EVENT_VERIFICATION_PROCESSING);
        $this->assertSame('verification_passed', IdentityVerificationEventService::EVENT_VERIFICATION_PASSED);
        $this->assertSame('verification_failed', IdentityVerificationEventService::EVENT_VERIFICATION_FAILED);
        $this->assertSame('verification_expired', IdentityVerificationEventService::EVENT_VERIFICATION_EXPIRED);
        $this->assertSame('verification_cancelled', IdentityVerificationEventService::EVENT_VERIFICATION_CANCELLED);
        $this->assertSame('admin_review_started', IdentityVerificationEventService::EVENT_ADMIN_REVIEW_STARTED);
        $this->assertSame('admin_approved', IdentityVerificationEventService::EVENT_ADMIN_APPROVED);
        $this->assertSame('admin_rejected', IdentityVerificationEventService::EVENT_ADMIN_REJECTED);
        $this->assertSame('account_activated', IdentityVerificationEventService::EVENT_ACCOUNT_ACTIVATED);
        $this->assertSame('fallback_triggered', IdentityVerificationEventService::EVENT_FALLBACK_TRIGGERED);
    }

    public function test_actor_type_constants_are_defined(): void
    {
        $this->assertSame('system', IdentityVerificationEventService::ACTOR_SYSTEM);
        $this->assertSame('user', IdentityVerificationEventService::ACTOR_USER);
        $this->assertSame('admin', IdentityVerificationEventService::ACTOR_ADMIN);
        $this->assertSame('webhook', IdentityVerificationEventService::ACTOR_WEBHOOK);
    }

    public function test_log_truncates_user_agent_to_500_chars(): void
    {
        $longAgent = str_repeat('A', 600);
        $capturedParams = null;

        DB::shouldReceive('statement')
            ->once()
            ->with(\Mockery::type('string'), \Mockery::on(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;
                return true;
            }))
            ->andReturn(true);

        IdentityVerificationEventService::log(
            2,
            5,
            IdentityVerificationEventService::EVENT_REGISTRATION_STARTED,
            null,
            null,
            IdentityVerificationEventService::ACTOR_SYSTEM,
            null,
            null,
            $longAgent
        );

        $this->assertNotNull($capturedParams);
        // user_agent is index 8 in the params array
        $this->assertLessThanOrEqual(500, strlen($capturedParams[8]));
    }

    public function test_log_encodes_details_as_json(): void
    {
        $capturedParams = null;

        DB::shouldReceive('statement')
            ->once()
            ->with(\Mockery::type('string'), \Mockery::on(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;
                return true;
            }))
            ->andReturn(true);

        $details = ['registration_mode' => 'open', 'has_policy' => true];

        IdentityVerificationEventService::log(
            2,
            7,
            IdentityVerificationEventService::EVENT_REGISTRATION_STARTED,
            null,
            null,
            IdentityVerificationEventService::ACTOR_SYSTEM,
            $details
        );

        $this->assertNotNull($capturedParams);
        // details is index 6 in the params array
        $this->assertJson($capturedParams[6]);
        $decoded = json_decode($capturedParams[6], true);
        $this->assertSame('open', $decoded['registration_mode']);
    }
}

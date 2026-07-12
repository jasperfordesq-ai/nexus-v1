<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Support;

use App\Models\Event;
use App\Models\EventSafetyRequirement;
use App\Models\EventSafetyRequirementVersion;
use App\Support\Events\EventSafetyContractMapper;
use App\Support\Events\EventSafetyEligibilityDecision;
use Tests\Laravel\TestCase;

final class EventSafetyContractMapperTest extends TestCase
{
    public function test_shared_safety_fixture_is_frozen_and_privacy_minimised(): void
    {
        $fixture = json_decode(
            (string) file_get_contents(dirname(__DIR__, 4) . '/contracts/events/v2/event-safety.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $event = (new Event())->forceFill(['id' => 101]);
        $requirements = (new EventSafetyRequirement())->setRawAttributes([
            'id' => 41,
            'event_id' => 101,
            'status' => 'published',
            'revision' => 3,
            'current_version' => 2,
            'published_version' => 2,
            'published_at' => '2030-04-01 09:00:00',
            'created_by_user_id' => 7,
            'published_by_user_id' => 7,
        ], true);
        $version = (new EventSafetyRequirementVersion())->forceFill([
            'id' => 42,
            'event_id' => 101,
            'requirements_id' => 41,
            'version_number' => 2,
            'minimum_age' => 14,
            'guardian_consent_required' => true,
            'minor_age_threshold' => 18,
            'code_of_conduct_required' => true,
            'code_of_conduct_text' => 'Treat everyone with respect and follow the event safety guidance.',
            'code_of_conduct_text_version' => 'conduct-2026-07',
            'code_of_conduct_text_hash' => '426bb49f31b7c15dfd91b62db039e1247633019cc53a970926f4bff91f549296',
            'eligibility_policy_metadata' => ['private' => 'must-not-project'],
        ]);
        $decision = new EventSafetyEligibilityDecision(
            status: EventSafetyEligibilityDecision::DENY,
            eventId: 101,
            userId: 8,
            reasonCodes: ['event_safety_code_of_conduct_acknowledgement_required'],
            requiredActions: ['event_safety_acknowledge_code_of_conduct'],
            requirementsVersion: 2,
            ageAtEvent: 20,
            minorAtEvent: false,
            safeguardingPolicy: [
                'code' => 'private-policy-evidence',
                'required_attestation_codes' => ['private-attestation'],
            ],
        );

        $actual = EventSafetyContractMapper::project(
            event: $event,
            requirements: $requirements,
            version: $version,
            eligibility: $decision,
            evidence: [
                'code_of_conduct' => [
                    'status' => 'required',
                    'acknowledgement_id' => null,
                    'text_version' => 'conduct-2026-07',
                    'acknowledged_at' => null,
                    'request_hash' => 'private',
                ],
                'guardian_consent' => null,
                'active_denial' => null,
                'guardian_token' => 'private-token',
            ],
            permissions: [
                'acknowledge_code_of_conduct' => true,
            ],
            rollout: [
                'resolved_mode' => 'shadow',
                'source' => 'tenant_override',
                'configuration_valid' => true,
                'configuration_fingerprint' => 'private-fingerprint',
            ],
        );

        self::assertSame($fixture, $actual);
        $encoded = json_encode($actual, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('private', $encoded);
        self::assertArrayNotHasKey('user_id', $actual['eligibility']);
        self::assertArrayNotHasKey('safeguarding_policy', $actual['eligibility']);
    }

    public function test_missing_configuration_projects_non_actionable_defaults(): void
    {
        $actual = EventSafetyContractMapper::project(
            event: (new Event())->forceFill(['id' => 9]),
            requirements: null,
            version: null,
            eligibility: null,
            evidence: [],
            permissions: [],
            rollout: [
                'resolved_mode' => 'off',
                'source' => 'global',
                'configuration_valid' => true,
            ],
        );

        self::assertNull($actual['requirements']);
        self::assertSame('not_evaluated', $actual['eligibility']['status']);
        self::assertSame('not_required', $actual['evidence']['code_of_conduct']['status']);
        self::assertSame('not_required', $actual['evidence']['guardian_consent']['status']);
        self::assertFalse($actual['rollout']['enforcement_active']);
        self::assertFalse(in_array(true, $actual['permissions'], true));
    }
}

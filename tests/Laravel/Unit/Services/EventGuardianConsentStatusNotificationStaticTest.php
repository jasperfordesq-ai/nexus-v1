<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use PHPUnit\Framework\TestCase;

final class EventGuardianConsentStatusNotificationStaticTest extends TestCase
{
    public function test_grant_and_withdraw_write_attendee_only_status_facts(): void
    {
        $publisher = $this->source('app/Services/EventGuardianConsentStatusPublisher.php');
        $service = $this->source('app/Services/EventGuardianConsentService.php');

        self::assertStringContainsString('event.safety.guardian_consent.', $publisher);
        self::assertStringContainsString("'recipient_user_id' => \$recipientUserId", $publisher);
        self::assertStringContainsString("'to_status' => \$status->value", $publisher);
        self::assertStringContainsString('EventNotificationDeliveryMode::OutboxAuthoritative', $publisher);
        self::assertStringContainsString('EventGuardianConsentAction::Granted', $service);
        self::assertStringContainsString('EventGuardianConsentAction::Withdrawn', $service);
        self::assertSame(2, substr_count($service, 'EventGuardianConsentStatusPublisher('));
    }

    public function test_status_fact_contract_contains_no_guardian_identity_or_token(): void
    {
        $source = $this->source('app/Services/EventGuardianConsentStatusPublisher.php');
        $payload = $this->between(
            $source,
            "'schema_version' => 1,",
            "'occurred_at' => \$occurredAt->toIso8601String(),",
        );

        foreach ([
            'guardian_email',
            'guardian_name',
            'guardian_identity',
            'relationship_code',
            'ciphertext',
            'token',
            'actor_user_id',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $payload);
        }
        self::assertStringContainsString('recipient_user_id', $payload);
        self::assertStringContainsString('consent_version', $payload);
    }

    public function test_notification_consumer_owns_and_validates_all_guardian_actions(): void
    {
        $scope = $this->source('app/Services/EventNotificationOutboxScope.php');
        $handler = $this->source('app/Services/EventNotificationOutboxActionHandler.php');

        foreach (['requested', 'granted', 'withdrawn'] as $action) {
            self::assertStringContainsString(
                "event.safety.guardian_consent.{$action}",
                $scope,
            );
        }
        self::assertStringContainsString('event_notification_guardian_consent_payload_invalid', $handler);
        self::assertStringContainsString("'recipient_user_id'", $handler);
        self::assertStringContainsString("'kind' => 'guardian_consent'", $handler);
        self::assertStringContainsString('guardianStatusChannelSuppression', $handler);
        self::assertStringContainsString('EventNotificationPreferenceResolver::resolveForEvent', $handler);
        self::assertStringContainsString('LocaleContext::withLocale', $handler);
        self::assertStringContainsString('event_safety.govuk.guardian_status.', $handler);
    }

    public function test_guardian_status_recipient_plan_does_not_add_an_organizer(): void
    {
        $handler = $this->source('app/Services/EventNotificationOutboxActionHandler.php');
        $block = $this->between(
            $handler,
            "if (\$descriptor['kind'] === 'guardian_consent') {",
            "return \$plans;",
        );

        self::assertStringContainsString("\$payload['recipient_user_id']", $block);
        self::assertStringNotContainsString('$organizerId', $block);
        self::assertStringNotContainsString('guardian_email', $block);
        self::assertStringNotContainsString('guardian_identity', $block);
        self::assertStringNotContainsString('token', $block);
    }

    private function source(string $relative): string
    {
        $path = dirname(__DIR__, 4) . '/' . $relative;
        $source = file_get_contents($path);
        self::assertIsString($source, "Could not read {$relative}");

        return $source;
    }

    private function between(string $source, string $start, string $end): string
    {
        $startAt = strpos($source, $start);
        self::assertNotFalse($startAt, "Missing start marker: {$start}");
        $endAt = strpos($source, $end, $startAt);
        self::assertNotFalse($endAt, "Missing end marker: {$end}");

        return substr($source, $startAt, $endAt - $startAt + strlen($end));
    }
}

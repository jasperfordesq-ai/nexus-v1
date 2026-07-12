<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use App\Services\EventNotificationErrorSanitizer;
use PHPUnit\Framework\TestCase;

final class EventNotificationEnterpriseStaticTest extends TestCase
{
    public function test_every_canonical_meaningful_event_update_has_a_rendered_label(): void
    {
        $producer = $this->source('app/Services/EventService.php');
        $consumer = $this->source('app/Services/EventNotificationOutboxActionHandler.php');

        foreach ([
            'title',
            'start_time',
            'end_time',
            'timezone',
            'all_day',
            'location',
            'is_online',
            'online_link',
            'allow_remote_attendance',
            'max_attendees',
            'venue_accessibility',
        ] as $field) {
            self::assertStringContainsString("'{$field}'", $producer);
            self::assertStringContainsString("'{$field}'", $consumer);
        }
        self::assertStringContainsString("'venue_accessibility' => 'accessibility'", $consumer);
    }

    public function test_all_canonical_member_channels_and_email_cadence_recheck_event_preferences(): void
    {
        $source = $this->source('app/Services/EventNotificationOutboxActionHandler.php');

        self::assertStringContainsString("if (\$descriptor['kind'] !== 'reminder')", $source);
        self::assertStringContainsString('guardianStatusChannelSuppression(', $source);
        self::assertStringContainsString('EventNotificationPreferenceResolver::resolveForEvent(', $source);
        self::assertStringNotContainsString(
            "in_array(\$descriptor['kind'], ['reminder', 'guardian_consent'], true)",
            $source,
        );
    }

    public function test_broadcast_push_never_calls_a_disabled_provider(): void
    {
        $source = $this->source('app/Services/EventBroadcastChannelDispatcher.php');

        self::assertStringContainsString('EventNotificationPreferenceResolver::resolveForEvent(', $source);
        self::assertStringContainsString('$webPushEnabled && WebPushService::sendToUserStatic(', $source);
        self::assertStringContainsString('$fcmEnabled', $source);
        self::assertStringContainsString('? FCMPushService::sendToUser(', $source);
        self::assertStringContainsString("'type' => \$message->notificationType", $source);
    }

    public function test_routine_invitations_fail_closed_after_events_feature_disable(): void
    {
        $source = $this->source('app/Services/EventInvitationDeliveryConsumer.php');

        self::assertStringContainsString("TenantContext::hasFeature('events')", $source);
        self::assertStringContainsString("'events_feature_disabled'", $source);
        self::assertStringContainsString('event_invitation_delivery_evidence', $source);
        self::assertStringContainsString('event_invitation_delivery_locale_evidence_invalid', $source);
        self::assertStringContainsString('LocaleContext::withLocale($locale', $source);
    }

    public function test_durable_error_sanitizer_redacts_tokens_and_recipient_email(): void
    {
        $email = 'private.recipient+events@example.test';
        $token = str_repeat('ab', 32);
        $safe = EventNotificationErrorSanitizer::sanitize(
            "provider rejected {$email} at /events/1?token={$token} Bearer {$token}",
        );

        self::assertStringNotContainsString($email, $safe);
        self::assertStringNotContainsString($token, $safe);
        self::assertStringContainsString('[REDACTED_EMAIL]', $safe);
        self::assertStringContainsString('[REDACTED]', $safe);
    }

    public function test_fresh_install_defaults_activate_the_canonical_workers(): void
    {
        $environment = $this->source('.env.example');
        $config = $this->source('config/events.php');
        $deliveryResolver = $this->source(
            'app/Services/EventNotificationDeliveryModeResolver.php',
        );
        $deliveryModes = $this->source('app/Enums/EventNotificationDeliveryMode.php');
        $reminderScheduler = $this->source(
            'app/Services/EventReminderScheduleService.php',
        );

        self::assertStringContainsString('EVENTS_REMINDER_MODE=canonical', $environment);
        self::assertStringContainsString(
            'EVENTS_NOTIFICATION_DELIVERY_MODE=outbox_authoritative',
            $environment,
        );
        self::assertStringContainsString(
            'EVENTS_NOTIFICATION_OUTBOX_CONSUMER_ENABLED=true',
            $environment,
        );
        self::assertStringContainsString("env('EVENTS_REMINDER_MODE', 'canonical')", $config);
        self::assertStringContainsString(
            "env('EVENTS_NOTIFICATION_DELIVERY_MODE', 'outbox_authoritative')",
            $config,
        );
        self::assertStringContainsString(
            "env('EVENTS_NOTIFICATION_OUTBOX_CONSUMER_ENABLED', true)",
            $config,
        );
        self::assertStringContainsString("TenantContext::getSetting('events.notification_delivery_mode')", $deliveryResolver);
        self::assertStringContainsString("config('events.notification_delivery.mode', 'outbox_authoritative')", $deliveryResolver);
        self::assertStringContainsString("config('events.reminders.mode', 'canonical')", $reminderScheduler);
        foreach (['direct', 'shadow_outbox', 'outbox_authoritative'] as $override) {
            self::assertStringContainsString("'{$override}'", $deliveryModes);
        }
    }

    public function test_queue_migration_adds_tenant_scoped_nullable_event_context_safely(): void
    {
        $source = $this->source(
            'database/migrations/2026_07_11_000066_add_event_context_to_notification_queue.php',
        );

        self::assertStringContainsString("integer('event_id')->nullable()", $source);
        self::assertStringContainsString(
            "['tenant_id', 'event_id', 'status', 'frequency']",
            $source,
        );
        self::assertStringContainsString(
            'event_notification_queue_context_rollback_refused_evidence_exists',
            $source,
        );
        self::assertStringNotContainsString('->foreign(', $source);
    }

    public function test_canonical_deferred_email_producers_persist_event_context(): void
    {
        foreach ([
            'app/Services/EventNotificationOutboxActionHandler.php',
            'app/Services/EventBroadcastChannelDispatcher.php',
        ] as $relative) {
            $source = $this->source($relative);
            self::assertStringContainsString("DB::table('notification_queue')", $source);
            self::assertStringContainsString("'event_id' => \$eventId", $source);
        }
    }

    public function test_every_active_cron_queue_sender_uses_the_scoped_pre_send_gate(): void
    {
        $source = $this->source('app/Services/CronJobRunner.php');
        self::assertSame(3, substr_count(
            $source,
            '$this->filterEventQueueItemsByPreference(',
        ));

        foreach ([
            $this->between($source, 'private function processDigest(', 'private function logSuppressedDigestEmail('),
            $this->between($source, 'public function runInstantQueue()', 'private static function resolveNotificationQueueAuditCategory('),
            $this->between($source, 'private function runInstantQueueInternal()', 'private function processNewsletterQueueInternal()'),
        ] as $runner) {
            $gate = strpos($runner, '$this->filterEventQueueItemsByPreference(');
            $send = strpos($runner, 'EmailDispatchService::sendRaw(');
            self::assertNotFalse($gate);
            self::assertNotFalse($send);
            self::assertLessThan($send, $gate);
        }

        $filter = $this->between(
            $source,
            'private function filterEventQueueItemsByPreference(',
            'private function updateClaimedEventQueueRow(',
        );
        self::assertStringContainsString("\$item['event_id']", $filter);
        self::assertStringContainsString('EventNotificationPreferenceResolver::resolveForEvent(', $filter);
        self::assertStringContainsString("\$resolution['channels']['email']", $filter);
        self::assertStringContainsString("\$resolution['cadence']", $filter);
        self::assertStringContainsString('EventNotificationPreferenceResolver::frequency(', $filter);
        self::assertStringContainsString('isCriticalEventActivity(', $filter);
    }

    public function test_focused_events_harness_selects_enterprise_notification_contracts(): void
    {
        $source = $this->source('scripts/test-events.mjs');

        self::assertSame(4, substr_count(
            $source,
            'EventNotificationEnterpriseStaticTest',
        ));
        self::assertStringContainsString("phpBatch === 'notifications'", $source);
        self::assertStringContainsString("phpBatch === 'broadcasts'", $source);
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

        return substr($source, $startAt, $endAt - $startAt);
    }
}

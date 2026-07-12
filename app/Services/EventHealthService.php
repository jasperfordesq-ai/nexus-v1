<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payload-free, read-only operational health snapshot for the Events module.
 *
 * High-cardinality identifiers, notification payloads and reminder recipients
 * intentionally never leave this service. Operators receive only aggregate
 * counts and ages; events:integrity-audit remains the controlled diagnostic.
 */
final class EventHealthService
{
    public function __construct(
        private readonly EventIntegrityAuditService $integrityAudit,
        private readonly EventNotificationOutboxDiagnostics $outboxDiagnostics,
    ) {}

    /** @return array<string,mixed> */
    public function snapshot(?int $tenantId = null, int $maxOverdueSeconds = 600): array
    {
        $maxOverdueSeconds = max(60, min($maxOverdueSeconds, 86_400));
        $integrity = $this->integrityAudit->run($tenantId, 1);
        $outbox = $this->outboxDiagnostics->snapshot($tenantId);
        $domainOutbox = $this->domainOutboxOwnershipSnapshot($tenantId);
        $reminders = $this->reminderSnapshot($tenantId);
        $waitlist = $this->waitlistSnapshot($tenantId, $maxOverdueSeconds);
        $requiredSchema = $this->requiredSchemaSnapshot();

        $integrityCodes = [];
        foreach ((array) ($integrity['issues'] ?? []) as $issue) {
            if (! is_array($issue)) {
                continue;
            }
            $integrityCodes[(string) $issue['code']] = [
                'severity' => (string) $issue['severity'],
                'count' => (int) $issue['count'],
            ];
        }
        ksort($integrityCodes);

        $deliveryConfiguration = EventNotificationDeliveryModeResolver::inspect($tenantId);
        $deliveryMode = $deliveryConfiguration['resolved_mode'];
        $deliveryConfigurationInvalid = ! $deliveryConfiguration['global_configuration_valid']
            || $deliveryConfiguration['tenant_configuration_valid'] === false
            || $deliveryConfiguration['tenant_override_lookup_failed'];
        $channelConfigurationInvalid = ! (bool) ($outbox['channel_configuration']['valid'] ?? false);
        $authoritativeConsumerMisconfigured = $deliveryMode === 'outbox_authoritative'
            && ! (bool) ($outbox['consumer_enabled'] ?? false);
        $notificationUnhealthy = ! (bool) ($outbox['schema_available'] ?? false)
            || (int) ($outbox['dead_lettered'] ?? 0) > 0
            || (int) ($outbox['terminal_delivery_failures'] ?? 0) > 0
            || (int) ($outbox['stale_processing'] ?? 0) > 0
            || (int) ($outbox['oldest_deliverable_age_seconds'] ?? 0) > $maxOverdueSeconds
            || $deliveryConfigurationInvalid
            || $channelConfigurationInvalid
            || $authoritativeConsumerMisconfigured;
        $reminderUnhealthy = ! $reminders['schema_available']
            || $reminders['oldest_overdue_age_seconds'] > $maxOverdueSeconds;
        $waitlistUnhealthy = ! $waitlist['schema_available']
            || $waitlist['overdue_expired_active_offers'] > 0;
        $domainOutboxUnhealthy = ! $domainOutbox['schema_available']
            || $domainOutbox['unowned_authoritative_facts'] > 0
            || $domainOutbox['invalid_authoritative_statuses'] > 0;
        $schemaUnhealthy = $requiredSchema['missing'] !== [];
        $healthy = ! (bool) $integrity['blocking']
            && ! $notificationUnhealthy
            && ! $reminderUnhealthy
            && ! $waitlistUnhealthy
            && ! $domainOutboxUnhealthy
            && ! $schemaUnhealthy;

        return [
            'read_only' => true,
            'payload_free' => true,
            'generated_at' => now()->toIso8601String(),
            'tenant_id' => $tenantId,
            'healthy' => $healthy,
            'max_overdue_seconds' => $maxOverdueSeconds,
            'schema' => $requiredSchema,
            'integrity' => [
                'blocking' => (bool) $integrity['blocking'],
                'issue_types' => (int) $integrity['issue_types'],
                'critical_rows' => (int) $integrity['issues_by_severity']['critical'],
                'warning_rows' => (int) $integrity['issues_by_severity']['warning'],
                'issues' => $integrityCodes,
            ],
            'notifications' => [
                ...$outbox,
                'delivery_mode' => $deliveryMode,
                'delivery_configuration' => $deliveryConfiguration,
                'delivery_configuration_invalid' => $deliveryConfigurationInvalid,
                'channel_configuration_invalid' => $channelConfigurationInvalid,
                'authoritative_consumer_misconfigured' => $authoritativeConsumerMisconfigured,
                'unhealthy' => $notificationUnhealthy,
            ],
            'domain_outbox' => [
                ...$domainOutbox,
                'unhealthy' => $domainOutboxUnhealthy,
            ],
            'reminders' => [
                ...$reminders,
                'unhealthy' => $reminderUnhealthy,
            ],
            'waitlist' => [
                ...$waitlist,
                'unhealthy' => $waitlistUnhealthy,
            ],
        ];
    }

    /**
     * @return array{
     *   schema_available:bool,
     *   unowned_authoritative_facts:int,
     *   oldest_unowned_age_seconds:int,
     *   invalid_authoritative_statuses:int,
     *   oldest_invalid_status_age_seconds:int
     * }
     */
    private function domainOutboxOwnershipSnapshot(?int $tenantId): array
    {
        if (! Schema::hasTable('event_domain_outbox')
            || ! Schema::hasColumn('event_domain_outbox', 'action')
            || ! Schema::hasColumn('event_domain_outbox', 'production_mode')
            || ! Schema::hasColumn('event_domain_outbox', 'status')) {
            return [
                'schema_available' => false,
                'unowned_authoritative_facts' => 0,
                'oldest_unowned_age_seconds' => 0,
                'invalid_authoritative_statuses' => 0,
                'oldest_invalid_status_age_seconds' => 0,
            ];
        }

        // Only the notification consumer is active in this rollout. An
        // authoritative fact outside its exact ownership boundary has no
        // worker and must block cutover instead of aging silently forever.
        $unowned = DB::table('event_domain_outbox')
            ->whereIn('status', ['pending', 'processing', 'dead_letter'])
            ->when($tenantId !== null, static fn ($query) => $query->where('tenant_id', $tenantId));
        EventNotificationOutboxScope::applyUnowned($unowned);
        $oldest = (clone $unowned)->min('created_at');
        $invalidStatus = DB::table('event_domain_outbox')
            ->where('production_mode', 'outbox_authoritative')
            ->where(static function ($status): void {
                $status->whereNull('status')
                    ->orWhereNotIn('status', ['pending', 'processing', 'processed', 'dead_letter']);
            })
            ->when($tenantId !== null, static fn ($query) => $query->where('tenant_id', $tenantId));
        $oldestInvalid = (clone $invalidStatus)->min('created_at');

        return [
            'schema_available' => true,
            'unowned_authoritative_facts' => (clone $unowned)->count(),
            'oldest_unowned_age_seconds' => $this->ageInSeconds($oldest),
            'invalid_authoritative_statuses' => (clone $invalidStatus)->count(),
            'oldest_invalid_status_age_seconds' => $this->ageInSeconds($oldestInvalid),
        ];
    }

    /** @return array{schema_available:bool,overdue_pending:int,oldest_overdue_age_seconds:int} */
    private function reminderSnapshot(?int $tenantId): array
    {
        if (! Schema::hasTable('event_reminders')
            || ! Schema::hasColumn('event_reminders', 'scheduled_for')
            || ! Schema::hasColumn('event_reminders', 'status')) {
            return [
                'schema_available' => false,
                'overdue_pending' => 0,
                'oldest_overdue_age_seconds' => 0,
            ];
        }

        $due = DB::table('event_reminders')
            ->where('status', 'pending')
            ->where('scheduled_for', '<=', now())
            ->when($tenantId !== null, static fn ($query) => $query->where('tenant_id', $tenantId));
        $oldest = (clone $due)->min('scheduled_for');

        return [
            'schema_available' => true,
            'overdue_pending' => (clone $due)->count(),
            'oldest_overdue_age_seconds' => $this->ageInSeconds($oldest),
        ];
    }

    /**
     * @return array{
     *   schema_available:bool,
     *   expired_active_offers:int,
     *   overdue_expired_active_offers:int,
     *   oldest_expiry_age_seconds:int
     * }
     */
    private function waitlistSnapshot(?int $tenantId, int $maxOverdueSeconds): array
    {
        if (! Schema::hasTable('event_waitlist_entries')
            || ! Schema::hasColumn('event_waitlist_entries', 'queue_state')
            || ! Schema::hasColumn('event_waitlist_entries', 'offer_expires_at')) {
            return [
                'schema_available' => false,
                'expired_active_offers' => 0,
                'overdue_expired_active_offers' => 0,
                'oldest_expiry_age_seconds' => 0,
            ];
        }

        $expired = DB::table('event_waitlist_entries')
            ->where('queue_state', 'offered')
            ->whereNotNull('offer_expires_at')
            ->where('offer_expires_at', '<=', now())
            ->when($tenantId !== null, static fn ($query) => $query->where('tenant_id', $tenantId));
        $oldest = (clone $expired)->min('offer_expires_at');
        $overdue = (clone $expired)
            ->where('offer_expires_at', '<=', now()->subSeconds($maxOverdueSeconds));

        return [
            'schema_available' => true,
            'expired_active_offers' => (clone $expired)->count(),
            'overdue_expired_active_offers' => $overdue->count(),
            'oldest_expiry_age_seconds' => $this->ageInSeconds($oldest),
        ];
    }

    /** @return array{available:list<string>,missing:list<string>} */
    private function requiredSchemaSnapshot(): array
    {
        $required = [
            'events',
            'event_domain_outbox',
            'event_notification_deliveries',
            'event_notification_outbox_replays',
            'event_status_history',
            'event_series',
            'event_registrations',
            'event_registration_history',
            'event_waitlist_entries',
            'event_waitlist_entry_history',
            'event_waitlist_offer_envelopes',
            'event_waitlist_offer_envelope_access',
            'event_reminders',
            'event_attendance',
            'event_attendance_activity',
            'event_attendance_credit_claims',
            'event_staff_assignments',
            'event_staff_assignment_history',
            'event_calendar_feed_tokens',
        ];
        $available = [];
        $missing = [];
        foreach ($required as $table) {
            if (Schema::hasTable($table)) {
                $available[] = $table;
            } else {
                $missing[] = $table;
            }
        }

        return ['available' => $available, 'missing' => $missing];
    }

    private function ageInSeconds(mixed $timestamp): int
    {
        if ($timestamp === null || $timestamp === '') {
            return 0;
        }

        $instant = CarbonImmutable::parse((string) $timestamp);

        return max(0, now()->getTimestamp() - $instant->getTimestamp());
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Enums\EventGuardianConsentAction;
use App\Enums\EventGuardianConsentStatus;
use App\Enums\EventNotificationDeliveryMode;
use App\Exceptions\EventSafetyException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;

/**
 * Writes an attendee-only status fact in the consent mutation transaction.
 * Guardian address, identity, relationship, ciphertext, and token never cross
 * this boundary.
 */
final class EventGuardianConsentStatusPublisher
{
    public function __construct(
        private readonly EventDomainOutboxService $outbox,
    ) {
    }

    /** @return array<string,mixed> */
    public function record(
        int $tenantId,
        int $eventId,
        int $consentId,
        int $consentVersion,
        int $recipientUserId,
        EventGuardianConsentAction $action,
        EventGuardianConsentStatus $status,
        string $mutationIdempotencyHash,
        ?CarbonImmutable $occurredAt = null,
    ): array {
        if (! Schema::hasTable('event_domain_outbox')
            || ! Schema::hasTable('event_notification_deliveries')) {
            throw new EventSafetyException('event_guardian_status_notification_schema_unavailable');
        }
        $expected = match ($action) {
            EventGuardianConsentAction::Granted => EventGuardianConsentStatus::Active,
            EventGuardianConsentAction::Withdrawn => EventGuardianConsentStatus::Withdrawn,
            default => null,
        };
        if ($tenantId <= 0
            || $eventId <= 0
            || $consentId <= 0
            || $consentVersion <= 0
            || $recipientUserId <= 0
            || $expected === null
            || $status !== $expected
            || preg_match('/^[0-9a-f]{64}$/', $mutationIdempotencyHash) !== 1) {
            throw new EventSafetyException('event_guardian_status_notification_contract_invalid');
        }
        $actionName = 'event.safety.guardian_consent.' . $action->value;
        $occurredAt ??= CarbonImmutable::now('UTC');

        return $this->outbox->record(
            $tenantId,
            $eventId,
            $consentVersion,
            $actionName,
            implode(':', [
                'event-guardian-status-v1',
                $tenantId,
                $consentId,
                $consentVersion,
                $action->value,
                $mutationIdempotencyHash,
            ]),
            [
                'schema_version' => 1,
                'tenant_id' => $tenantId,
                'event_id' => $eventId,
                'consent_id' => $consentId,
                'consent_version' => $consentVersion,
                'recipient_user_id' => $recipientUserId,
                'to_status' => $status->value,
                'occurred_at' => $occurredAt->toIso8601String(),
            ],
            EventNotificationDeliveryMode::OutboxAuthoritative,
            "event:{$eventId}:safety:guardian-consent:{$consentId}",
        );
    }
}

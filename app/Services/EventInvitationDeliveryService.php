<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Enums\EventNotificationDeliveryMode;
use App\Exceptions\EventRegistrationFoundationException;
use App\Support\Events\EventRegistrationFoundationSupport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Durable preference and locale evidence for every invitation delivery channel. */
final class EventInvitationDeliveryService
{
    private const CHANNELS = ['email', 'in_app', 'web_push', 'fcm', 'realtime'];
    private const SUPPORTED_LOCALES = [
        'ar', 'de', 'en', 'es', 'fr', 'ga', 'it', 'ja', 'nl', 'pl', 'pt',
    ];

    public function __construct(
        private readonly EventRegistrationFoundationSupport $support = new EventRegistrationFoundationSupport(),
        private readonly EventDomainOutboxService $outbox = new EventDomainOutboxService(),
    ) {
    }

    /**
     * @param array{type:string,member_id:?int,email:?string} $recipient
     * @return array{outbox:array<string,mixed>,evidence:list<array<string,mixed>>}
     */
    public function queue(
        int $tenantId,
        int $eventId,
        int $campaignId,
        int $invitationId,
        int $invitationVersion,
        array $recipient,
        string $token,
        string $tokenExpiresAt,
        string $campaignLocale,
    ): array {
        $this->assertSchema();
        $campaignLocale = $this->locale($campaignLocale);
        $memberId = $recipient['type'] === 'member' ? (int) ($recipient['member_id'] ?? 0) : null;
        $email = $recipient['type'] === 'email' && is_string($recipient['email'] ?? null)
            ? $this->support->normalizeEmail($recipient['email'])
            : null;
        if (($memberId === null && $email === null)
            || ($memberId !== null && $memberId <= 0)
            || ! in_array($recipient['type'], ['member', 'email'], true)) {
            throw new EventRegistrationFoundationException('event_invitation_delivery_recipient_invalid');
        }

        $locale = $campaignLocale;
        $preference = array_fill_keys(self::CHANNELS, true);
        $preferenceSources = array_fill_keys(self::CHANNELS, 'external_invitation');
        $externalHash = null;
        $allowIneligibleForSuppression = false;
        if ($memberId !== null) {
            $member = DB::table('users')
                ->where('tenant_id', $tenantId)
                ->where('id', $memberId)
                ->first(['id', 'email', 'preferred_language', 'status', 'deleted_at']);
            if ($member === null) {
                throw new EventRegistrationFoundationException('event_invitation_delivery_recipient_invalid');
            }
            $preferredLocale = trim((string) ($member->preferred_language ?? ''));
            if ($preferredLocale !== '') {
                $locale = $this->locale($preferredLocale);
            }
            $allowIneligibleForSuppression = (string) ($member->status ?? '') !== 'active'
                || $member->deleted_at !== null;
            if ($allowIneligibleForSuppression) {
                $preference = array_fill_keys(self::CHANNELS, false);
                $preferenceSources = array_fill_keys(self::CHANNELS, 'recipient_ineligible');
            } else {
                $resolution = EventNotificationPreferenceResolver::resolveForEvent(
                    $memberId,
                    $tenantId,
                    $eventId,
                );
                $preference = $resolution['channels'];
                $preferenceSources = $resolution['channel_sources'];
            }
            if (! is_string($member->email ?? null) || trim((string) $member->email) === '') {
                $preference['email'] = false;
                $preferenceSources['email'] = 'recipient_email_missing';
            }
        } else {
            $externalHash = $this->support->emailBlindHash($tenantId, (string) $email);
            foreach (self::CHANNELS as $channel) {
                $preference[$channel] = $channel === 'email';
                $preferenceSources[$channel] = $channel === 'email'
                    ? 'external_invitation'
                    : 'external_channel_unavailable';
            }
        }

        $action = 'event.invitation.issued';
        $outbox = $this->outbox->record(
            $tenantId,
            $eventId,
            $invitationVersion,
            $action,
            "event-invitation:{$tenantId}:{$eventId}:{$invitationId}:{$invitationVersion}",
            [
                'campaign_id' => $campaignId,
                'invitation_id' => $invitationId,
                'invitation_version' => $invitationVersion,
                'recipient_user_id' => $memberId,
                'external_recipient_hash' => $externalHash,
                'external_email_ciphertext' => $email === null ? null : $this->support->encrypt($email),
                'recipient_locale' => $locale,
                'token_ciphertext' => $this->support->encrypt($token),
                'token_expires_at' => $tokenExpiresAt,
                'channels' => $preference,
            ],
            EventNotificationDeliveryMode::OutboxAuthoritative,
            "event:{$eventId}:invitation:{$invitationId}",
        );

        $evidence = [];
        foreach (self::CHANNELS as $channel) {
            $deliveryKey = $memberId !== null
                ? EventDomainOutboxService::deliveryKey(
                    $tenantId,
                    $eventId,
                    $action . '.' . $invitationId,
                    $memberId,
                    $channel,
                    $invitationVersion,
                )
                : EventDomainOutboxService::externalDeliveryKey(
                    $tenantId,
                    $eventId,
                    $action . '.' . $invitationId,
                    (string) $externalHash,
                    $channel,
                    $invitationVersion,
                );
            if ($memberId !== null) {
                $delivery = $this->outbox->ensureDelivery(
                    (int) $outbox['id'],
                    $memberId,
                    $channel,
                    $deliveryKey,
                    $allowIneligibleForSuppression,
                );
            } elseif ($channel === 'email') {
                $delivery = $this->outbox->ensureExternalDelivery(
                    (int) $outbox['id'],
                    (string) $externalHash,
                    $channel,
                    $deliveryKey,
                );
            } else {
                $delivery = null;
            }

            $allowed = (bool) $preference[$channel];
            if ($delivery !== null && ! $allowed) {
                DB::table('event_notification_deliveries')
                    ->where('tenant_id', $tenantId)
                    ->where('id', (int) $delivery['id'])
                    ->whereNotIn('status', ['delivered', 'suppressed'])
                    ->update([
                        'status' => 'suppressed',
                        'suppression_reason' => mb_substr((string) $preferenceSources[$channel], 0, 191),
                        'suppressed_at' => CarbonImmutable::now('UTC'),
                        'updated_at' => CarbonImmutable::now('UTC'),
                    ]);
            }
            $evidenceIdempotency = hash('sha256', implode('|', [
                'event-invitation-delivery-v1',
                $tenantId,
                $eventId,
                $invitationId,
                $invitationVersion,
                $channel,
            ]));
            DB::table('event_invitation_delivery_evidence')->insertOrIgnore([
                'tenant_id' => $tenantId,
                'event_id' => $eventId,
                'campaign_id' => $campaignId,
                'invitation_id' => $invitationId,
                'outbox_id' => (int) $outbox['id'],
                'notification_delivery_id' => $delivery === null ? null : (int) $delivery['id'],
                'evidence_version' => 1,
                'channel' => $channel,
                'recipient_locale' => $locale,
                'preference_decision' => $allowed ? 'deliver' : 'suppressed',
                'preference_reason' => mb_substr((string) $preferenceSources[$channel], 0, 100),
                'status' => $allowed ? 'queued' : 'suppressed',
                'idempotency_hash' => $evidenceIdempotency,
                'provider_evidence_id' => null,
                'failure_code' => null,
                'created_at' => CarbonImmutable::now('UTC'),
            ]);
            $stored = DB::table('event_invitation_delivery_evidence')
                ->where('tenant_id', $tenantId)
                ->where('idempotency_hash', $evidenceIdempotency)
                ->first();
            if ($stored === null) {
                throw new EventRegistrationFoundationException('event_invitation_delivery_evidence_unavailable');
            }
            $evidence[] = (array) $stored;
        }

        return ['outbox' => $outbox, 'evidence' => $evidence];
    }

    private function locale(string $locale): string
    {
        $locale = strtolower(trim(str_replace('_', '-', $locale)));
        $locale = explode('-', $locale, 2)[0];
        if (! in_array($locale, self::SUPPORTED_LOCALES, true)) {
            throw new EventRegistrationFoundationException('event_invitation_delivery_locale_invalid');
        }

        return $locale;
    }

    private function assertSchema(): void
    {
        foreach ([
            'event_domain_outbox', 'event_notification_deliveries',
            'event_invitation_delivery_evidence',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                throw new EventRegistrationFoundationException('event_invitation_delivery_schema_unavailable');
            }
        }
    }
}

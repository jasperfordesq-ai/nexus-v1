<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Core\EmailTemplateBuilder;
use App\Core\Mailer;
use App\Core\TenantContext;
use App\I18n\LocaleContext;
use App\Support\Events\EventNotificationOutboxHandleResult;
use App\Support\Events\EventRegistrationFoundationSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/** Consent-gated operational email delivery for an external event guest. */
final class EventRegistrationGuestNotificationConsumer
{
    private const ACTION = 'event.registration_guest.withdrawn';
    private const EMAIL_CATEGORY = 'event_registration_guest';

    public function __construct(
        private readonly EventRegistrationFoundationSupport $support = new EventRegistrationFoundationSupport(),
        private readonly EventDomainOutboxService $outbox = new EventDomainOutboxService(),
        private readonly EventReminderChannelDeliveryService $deliveries = new EventReminderChannelDeliveryService(),
    ) {
    }

    /** @param array<string,mixed> $outbox */
    public function handle(array $outbox): EventNotificationOutboxHandleResult
    {
        if (($outbox['action'] ?? null) !== self::ACTION) {
            throw new RuntimeException('event_registration_guest_notification_action_invalid');
        }
        $tenantId = (int) ($outbox['tenant_id'] ?? 0);
        $eventId = (int) ($outbox['event_id'] ?? 0);
        $outboxId = (int) ($outbox['id'] ?? 0);
        $aggregateVersion = (int) ($outbox['aggregate_version'] ?? 0);
        $payload = is_array($outbox['payload'] ?? null)
            ? $outbox['payload']
            : json_decode((string) ($outbox['payload'] ?? ''), true);
        if ($tenantId <= 0 || $eventId <= 0 || $outboxId <= 0
            || $aggregateVersion <= 0 || ! is_array($payload)) {
            throw new RuntimeException('event_registration_guest_notification_payload_invalid');
        }
        $guestId = (int) ($payload['guest_id'] ?? 0);
        $registrationId = (int) ($payload['registration_id'] ?? 0);
        $guestRevision = (int) ($payload['guest_revision'] ?? 0);
        if ($guestId <= 0 || $registrationId <= 0 || $guestRevision <= 0
            || $guestRevision !== $aggregateVersion) {
            throw new RuntimeException('event_registration_guest_notification_payload_invalid');
        }

        $event = DB::table('events')
            ->where('tenant_id', $tenantId)
            ->where('id', $eventId)
            ->first(['id', 'title']);
        $guest = DB::table('event_registration_guests')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('registration_id', $registrationId)
            ->where('id', $guestId)
            ->first();
        if ($event === null || $guest === null
            || (string) $guest->status !== 'withdrawn'
            || (int) $guest->revision !== $guestRevision) {
            throw new RuntimeException('event_registration_guest_notification_subject_invalid');
        }
        if (! (bool) $guest->notification_consent) {
            return new EventNotificationOutboxHandleResult(0, 0, 0, true);
        }

        $locale = trim((string) ($payload['recipient_locale'] ?? ''));
        $emailCiphertext = $payload['external_email_ciphertext'] ?? null;
        if ($locale === ''
            || ! is_string($guest->preferred_locale)
            || ! hash_equals((string) $guest->preferred_locale, $locale)
            || ! is_string($emailCiphertext)
            || ! is_string($guest->email_ciphertext)
            || ! hash_equals((string) $guest->email_ciphertext, $emailCiphertext)) {
            throw new RuntimeException('event_registration_guest_notification_recipient_invalid');
        }
        $email = $this->support->normalizeEmail($this->support->decrypt($emailCiphertext));
        $externalHash = $this->support->emailBlindHash($tenantId, $email);
        $deliveryKey = EventDomainOutboxService::externalDeliveryKey(
            $tenantId,
            $eventId,
            self::ACTION,
            $externalHash,
            'email',
            $guestRevision,
        );
        $delivery = $this->outbox->ensureExternalDelivery(
            $outboxId,
            $externalHash,
            'email',
            $deliveryKey,
        );
        $status = (string) ($delivery['status'] ?? '');
        if ($status === 'delivered') {
            return new EventNotificationOutboxHandleResult(1, 1, 0);
        }
        if ($status === 'suppressed') {
            return new EventNotificationOutboxHandleResult(1, 0, 1);
        }
        if ($status === 'failed_terminal') {
            throw new RuntimeException('event_registration_guest_notification_terminal_failure');
        }
        if (Mailer::isSuppressed($email)) {
            $this->deliveries->markSuppressed(
                $tenantId,
                (int) $delivery['id'],
                'email_provider_suppressed',
            );

            return new EventNotificationOutboxHandleResult(1, 0, 1);
        }
        if ($this->successfulEmailEvidence($tenantId, $deliveryKey)) {
            $claim = $this->deliveries->claim($tenantId, (int) $delivery['id']);
            if ($claim === null || ! $this->deliveries->markDelivered(
                $tenantId,
                (int) $claim['id'],
                (string) $claim['claim_token'],
                'email_log',
            )) {
                throw new RuntimeException('event_registration_guest_notification_evidence_completion_failed');
            }

            return new EventNotificationOutboxHandleResult(1, 1, 0);
        }

        $name = $this->support->decrypt((string) $guest->display_name_ciphertext);
        $eventUrl = TenantContext::getFrontendUrl()
            . TenantContext::getSlugPrefix()
            . '/events/' . $eventId;
        $rendered = LocaleContext::withLocale($locale, function () use ($event, $name, $eventUrl): array {
            $subject = __('emails.event_registration_guest.cancelled_subject', [
                'event' => (string) $event->title,
            ]);
            $message = __('emails.event_registration_guest.cancelled_message', [
                'event' => (string) $event->title,
            ]);
            $html = EmailTemplateBuilder::make()
                ->theme('info')
                ->title($subject)
                ->greeting($name)
                ->paragraph(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'))
                ->button(__('emails.event_registration_guest.view_event'), $eventUrl)
                ->render();

            return ['subject' => $subject, 'html' => $html];
        });

        $claim = $this->deliveries->claim($tenantId, (int) $delivery['id']);
        if ($claim === null) {
            throw new RuntimeException('event_registration_guest_notification_claim_unavailable');
        }
        try {
            $sent = EmailDispatchService::sendRaw(
                $email,
                $rendered['subject'],
                $rendered['html'],
                null,
                null,
                null,
                self::EMAIL_CATEGORY,
                [
                    'tenant_id' => $tenantId,
                    'event_id' => $eventId,
                    'idempotency_key' => $deliveryKey,
                    'source' => self::class,
                ],
            );
            if (! $sent) {
                throw new RuntimeException('event_registration_guest_notification_provider_rejected');
            }
            if (! $this->deliveries->markDelivered(
                $tenantId,
                (int) $claim['id'],
                (string) $claim['claim_token'],
                'email',
            )) {
                throw new RuntimeException('event_registration_guest_notification_ledger_completion_failed');
            }
        } catch (Throwable $exception) {
            $this->deliveries->markRetrying(
                $tenantId,
                (int) $claim['id'],
                (string) $claim['claim_token'],
                EventNotificationErrorSanitizer::sanitize($exception->getMessage()),
            );
            throw new RuntimeException('event_registration_guest_notification_retry_required', 0, $exception);
        }

        return new EventNotificationOutboxHandleResult(1, 1, 0);
    }

    private function successfulEmailEvidence(int $tenantId, string $deliveryKey): bool
    {
        return Schema::hasTable('email_log')
            && Schema::hasColumn('email_log', 'idempotency_key')
            && DB::table('email_log')
                ->where('tenant_id', $tenantId)
                ->where('category', self::EMAIL_CATEGORY)
                ->where('idempotency_key', $deliveryKey)
                ->whereIn('status', ['sent', 'delivered'])
                ->exists();
    }
}

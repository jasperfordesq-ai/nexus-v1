<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\EmailTemplateBuilder;
use App\Core\TenantContext;
use App\I18n\LocaleContext;
use Illuminate\Support\Facades\Log;

/**
 * DonationEmailService — Sends email notifications for credit donations.
 *
 * Sends a confirmation to the donor and a notification to the recipient
 * when a credit donation is made between members.
 */
class DonationEmailService
{
    /**
     * Send donation emails to both donor and recipient.
     *
     * Wraps each send in an individual try/catch so one failure doesn't block the other.
     *
     * @param int         $tenantId  Tenant ID
     * @param object      $donor     Donor User model instance
     * @param object      $recipient Recipient User model instance
     * @param float       $amount    Amount donated
     * @param string|null $message   Optional personal message from donor
     */
    public static function sendDonationEmails(
        int $tenantId,
        object $donor,
        object $recipient,
        float $amount,
        ?string $message
    ): void {
        $walletUrl = EmailTemplateBuilder::tenantUrl('/wallet');

        // ── Donor confirmation — render in donor's preferred_language ──────
        try {
            if (!empty($donor->email)) {
                LocaleContext::withLocale($donor, function () use ($donor, $recipient, $amount, $message, $walletUrl) {
                    $donorName         = $donor->first_name ?? $donor->name ?? __('emails.common.fallback_name');
                    $recipientFullName = trim(($recipient->first_name ?? '') . ' ' . ($recipient->last_name ?? '')) ?: ($recipient->name ?? __('emails.common.fallback_member_name'));

                    $subject = __('emails.donation.sent_subject', ['amount' => $amount]);

                    $builder = EmailTemplateBuilder::make()
                        ->theme('success')
                        ->title(__('emails.donation.sent_title'))
                        ->previewText(__('emails.donation.sent_preview', ['amount' => $amount, 'recipient' => $recipientFullName]))
                        ->greeting($donorName)
                        ->paragraph(__('emails.donation.sent_greeting'))
                        ->paragraph(__('emails.donation.sent_body', ['amount' => $amount, 'recipient' => $recipientFullName]));

                    $infoCard = [
                        __('emails.donation.sent_message_label') => $message ?: '—',
                    ];
                    $builder->infoCard($infoCard);

                    $html = $builder
                        ->button(__('emails.donation.sent_cta'), $walletUrl)
                        ->render();

                    /** @var EmailService $emailService */
                    $emailService = app(EmailService::class);
                    $emailService->send($donor->email, $subject, $html);
                });
            }
        } catch (\Throwable $e) {
            Log::warning('DonationEmailService: failed to send donor confirmation', [
                'donor_id'  => $donor->id ?? null,
                'tenant_id' => $tenantId,
                'error'     => $e->getMessage(),
            ]);
        }

        // ── Recipient notification — render in recipient's preferred_language ──
        try {
            if (!empty($recipient->email)) {
                LocaleContext::withLocale($recipient, function () use ($donor, $recipient, $amount, $message, $walletUrl) {
                    $recipientName = $recipient->first_name ?? $recipient->name ?? __('emails.common.fallback_name');
                    $donorFullName = trim(($donor->first_name ?? '') . ' ' . ($donor->last_name ?? '')) ?: ($donor->name ?? __('emails.common.fallback_member_name'));

                    $subject = __('emails.donation.received_subject', ['amount' => $amount, 'donor' => $donorFullName]);

                    $builder = EmailTemplateBuilder::make()
                        ->theme('success')
                        ->title(__('emails.donation.received_title'))
                        ->previewText(__('emails.donation.received_preview', ['donor' => $donorFullName, 'amount' => $amount]))
                        ->greeting($recipientName)
                        ->paragraph(__('emails.donation.received_greeting'))
                        ->paragraph(__('emails.donation.received_body', ['donor' => $donorFullName, 'amount' => $amount]));

                    $infoCard = [
                        __('emails.donation.received_message_label') => $message ?: '—',
                    ];
                    $builder->infoCard($infoCard);

                    $html = $builder
                        ->button(__('emails.donation.received_cta'), $walletUrl)
                        ->render();

                    /** @var EmailService $emailService */
                    $emailService = app(EmailService::class);
                    $emailService->send($recipient->email, $subject, $html);
                });
            }
        } catch (\Throwable $e) {
            Log::warning('DonationEmailService: failed to send recipient notification', [
                'recipient_id' => $recipient->id ?? null,
                'tenant_id'    => $tenantId,
                'error'        => $e->getMessage(),
            ]);
        }
    }
}

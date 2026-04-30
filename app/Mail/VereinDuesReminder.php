<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Mail;

use App\Core\EmailTemplateBuilder;
use App\I18n\LocaleContext;
use App\Services\EmailService;
use Throwable;

/**
 * AG54 — Sent on the 7-day cadence for overdue dues. Wrapped in LocaleContext.
 */
class VereinDuesReminder
{
    public static function send(object $recipient, string $orgName, int $year, int $amountCents, string $currency, string $dueDate, string $url): void
    {
        if (empty($recipient->email)) {
            return;
        }

        LocaleContext::withLocale($recipient->preferred_language ?? null, function () use ($recipient, $orgName, $year, $amountCents, $currency, $dueDate, $url): void {
            try {
                $name = trim(($recipient->first_name ?? '') . ' ' . ($recipient->last_name ?? ''));
                $amount = number_format($amountCents / 100, 2) . ' ' . strtoupper($currency);
                $params = ['year' => $year, 'amount' => $amount, 'organization' => $orgName, 'due_date' => $dueDate];

                $html = EmailTemplateBuilder::make()
                    ->theme('warning')
                    ->title(__('emails.verein_dues.reminder_title', $params))
                    ->greeting($name !== '' ? $name : __('emails.common.fallback_name'))
                    ->paragraph(__('emails.verein_dues.reminder_body', $params))
                    ->button(__('emails.verein_dues.cta_pay'), $url)
                    ->render();

                /** @var EmailService $email */
                $email = app(EmailService::class);
                $email->send($recipient->email, __('emails.verein_dues.reminder_subject', $params), $html);
            } catch (Throwable $e) {
                // best-effort
            }
        });
    }
}

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
 * AG55 — Verein cross-invitation accepted email (sent to inviter).
 */
class VereinCrossInvitationAccepted
{
    public static function send(object $recipient, string $accepterName, string $targetName): void
    {
        if (empty($recipient->email)) {
            return;
        }

        LocaleContext::withLocale($recipient->preferred_language ?? null, function () use ($recipient, $accepterName, $targetName): void {
            try {
                $name = trim(($recipient->first_name ?? '') . ' ' . ($recipient->last_name ?? ''));
                $builder = EmailTemplateBuilder::make()
                    ->theme('success')
                    ->title(__('emails.verein_federation.invitation_accepted_title'))
                    ->previewText(__('emails.verein_federation.invitation_accepted_preview'))
                    ->greeting($name !== '' ? $name : __('emails.common.fallback_name'))
                    ->paragraph(__('emails.verein_federation.invitation_accepted_body', [
                        'name' => $accepterName,
                        'target' => $targetName,
                    ]));

                $subject = __('emails.verein_federation.invitation_accepted_subject');

                /** @var EmailService $email */
                $email = app(EmailService::class);
                $email->send($recipient->email, $subject, $builder->render());
            } catch (Throwable $e) {
                // best-effort
            }
        });
    }
}

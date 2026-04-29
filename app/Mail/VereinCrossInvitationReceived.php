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
 * AG55 — Verein cross-invitation email (sent to invitee).
 *
 * Always wrapped in LocaleContext::withLocale() so it renders in the
 * recipient's preferred_language, regardless of caller / queue worker locale.
 */
class VereinCrossInvitationReceived
{
    public static function send(object $recipient, int $invitationId, string $sourceName, string $targetName, ?string $message): void
    {
        if (empty($recipient->email)) {
            return;
        }

        LocaleContext::withLocale($recipient->preferred_language ?? null, function () use ($recipient, $invitationId, $sourceName, $targetName, $message): void {
            try {
                $base = (string) (config('app.frontend_url') ?: 'https://app.project-nexus.ie');
                $url = rtrim($base, '/') . '/me/verein-invitations';

                $name = trim(($recipient->first_name ?? '') . ' ' . ($recipient->last_name ?? ''));
                $builder = EmailTemplateBuilder::make()
                    ->theme('info')
                    ->title(__('emails.verein_federation.invitation_received_title'))
                    ->previewText(__('emails.verein_federation.invitation_received_preview', ['target' => $targetName]))
                    ->greeting($name !== '' ? $name : __('emails.common.fallback_name'))
                    ->paragraph(__('emails.verein_federation.invitation_received_body', [
                        'source' => $sourceName,
                        'target' => $targetName,
                    ]));

                if ($message !== null && $message !== '') {
                    $builder->infoCard([
                        __('emails.verein_federation.message_label') => $message,
                    ]);
                }

                $builder->button(__('emails.verein_federation.cta_respond'), $url);

                $subject = __('emails.verein_federation.invitation_received_subject', ['target' => $targetName]);

                /** @var EmailService $email */
                $email = app(EmailService::class);
                $email->send($recipient->email, $subject, $builder->render());
            } catch (Throwable $e) {
                // best-effort; in-app notification is primary channel
            }
        });
    }
}

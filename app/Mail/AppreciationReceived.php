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
 * SOC14 — Appreciation received email (sent to receiver).
 *
 * Always wrapped in LocaleContext::withLocale() so it renders in the
 * recipient's preferred_language, regardless of caller / queue worker locale.
 */
class AppreciationReceived
{
    /**
     * @param object $recipient User-like object with email/first_name/last_name/preferred_language
     */
    public static function send(object $recipient, string $senderName, string $message, bool $isPublic): void
    {
        if (empty($recipient->email)) {
            return;
        }

        LocaleContext::withLocale($recipient->preferred_language ?? null, function () use ($recipient, $senderName, $message, $isPublic): void {
            try {
                $base = (string) (config('app.frontend_url') ?: 'https://app.project-nexus.ie');
                $url = rtrim($base, '/') . '/users/' . ($recipient->id ?? 'me') . '/appreciations';

                $name = trim(($recipient->first_name ?? '') . ' ' . ($recipient->last_name ?? ''));
                if ($name === '') {
                    $name = (string) ($recipient->name ?? __('emails.common.fallback_name'));
                }

                $builder = EmailTemplateBuilder::make()
                    ->theme('success')
                    ->title(__('emails.appreciation.title'))
                    ->previewText(__('emails.appreciation.preview', ['sender' => $senderName]))
                    ->greeting($name !== '' ? $name : __('emails.common.fallback_name'))
                    ->paragraph(__('emails.appreciation.body', ['sender' => $senderName]))
                    ->infoCard([
                        __('emails.appreciation.message_label') => $message,
                    ])
                    ->paragraph($isPublic
                        ? __('emails.appreciation.public_note')
                        : __('emails.appreciation.private_note'))
                    ->button(__('emails.appreciation.cta_view'), $url);

                $subject = __('emails.appreciation.subject', ['sender' => $senderName]);

                /** @var EmailService $email */
                $email = app(EmailService::class);
                $email->send($recipient->email, $subject, $builder->render());
            } catch (Throwable $e) {
                // best-effort; in-app notification is primary channel
            }
        });
    }
}

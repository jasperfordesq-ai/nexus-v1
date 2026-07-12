<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Core\EmailTemplateBuilder;
use App\Core\TenantContext;
use App\Enums\EventBroadcastVariant;
use App\Exceptions\EventBroadcastException;
use App\Support\Events\EventBroadcastRenderedMessage;

/** Locale-sensitive wrapper around byte-preserved organizer-authored prose. */
final class EventBroadcastRenderer
{
    public function render(object $broadcast, object $event, object $recipient): EventBroadcastRenderedMessage
    {
        $variant = EventBroadcastVariant::tryFrom((string) ($broadcast->variant ?? ''));
        $body = $broadcast->body ?? null;
        $eventTitle = trim((string) ($event->title ?? ''));
        if ($variant === null || ! is_string($body) || trim($body) === '' || $eventTitle === '') {
            throw new EventBroadcastException('event_broadcast_render_contract_invalid');
        }

        $subject = __($variant->subjectTranslationKey(), ['title' => $eventTitle]);
        $heading = __($variant->headingTranslationKey(), ['title' => $eventTitle]);
        $path = '/events/' . (int) $event->id;
        $url = TenantContext::getFrontendUrl() . TenantContext::getSlugPrefix() . $path;
        $recipientName = trim((string) ($recipient->first_name ?? $recipient->name ?? ''));
        if ($recipientName === '') {
            $recipientName = __('emails.common.fallback_name');
        }
        $buttonKey = $variant === EventBroadcastVariant::ReviewRequest
            ? 'emails.events.leave_event_review'
            : 'emails.events.view_event';
        $safeBody = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'), false);
        $html = EmailTemplateBuilder::make()
            ->theme($variant === EventBroadcastVariant::ReviewRequest ? 'success' : 'info')
            ->title($heading)
            ->greeting($recipientName)
            ->paragraph($safeBody)
            ->button(__($buttonKey), $url)
            ->render();

        return new EventBroadcastRenderedMessage(
            subject: $subject,
            message: $body,
            html: $html,
            path: $path,
            notificationType: $variant->notificationType(),
        );
    }
}

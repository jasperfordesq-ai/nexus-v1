<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Listeners;

use App\Core\Mailer;
use App\Core\TenantContext;
use App\Events\FederatedReviewReceived;
use App\I18n\LocaleContext;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * HandleFederatedReviewReceived — notify a local user when a partner federation
 * node delivers an inbound review about them.
 *
 * The webhook controller has already persisted the review into the local
 * `reviews` table with `review_type = 'federated'`. This listener handles
 * the post-persistence side effects:
 *   - in-app bell notification to the reviewee in their preferred locale
 *   - per-user-preference email via the same path the local-review flow uses
 *     (NotificationDispatcher::sendReviewEmail honours email_reviews pref and
 *      tenant locale)
 *   - structured audit log
 */
class HandleFederatedReviewReceived implements ShouldQueue
{
    public string $queue = 'federation';

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function handle(FederatedReviewReceived $event): void
    {
        try {
            TenantContext::setById($event->tenantId);

            $receiverId = (int) ($event->shadowRow['receiver_id'] ?? 0);
            if ($receiverId <= 0) {
                return;
            }

            $receiver = DB::table('users')
                ->where('id', $receiverId)
                ->where('tenant_id', $event->tenantId)
                ->where('status', 'active')
                ->select(['id', 'email', 'first_name', 'name', 'preferred_language', 'federation_notifications_enabled'])
                ->first();
            if (! $receiver) {
                Log::info('[HandleFederatedReviewReceived] receiver not found locally', [
                    'tenant_id'   => $event->tenantId,
                    'partner_id'  => $event->externalPartnerId,
                    'receiver_id' => $receiverId,
                ]);
                return;
            }

            // Honour the receiver's federation-notifications preference. The
            // column defaults to 1; only members who explicitly opted out
            // (via Settings → Notifications) are skipped. The bell + email
            // are both suppressed because the whole notification is about
            // federated activity.
            if (isset($receiver->federation_notifications_enabled)
                && (int) $receiver->federation_notifications_enabled === 0) {
                Log::info('[HandleFederatedReviewReceived] receiver opted out of federation notifications', [
                    'tenant_id'   => $event->tenantId,
                    'receiver_id' => $receiverId,
                ]);
                return;
            }

            $rating  = (int) ($event->shadowRow['rating'] ?? 0);
            $comment = $event->shadowRow['comment'] ?? null;

            // Partner-side member is anonymous from our perspective — we know
            // the external user id but not their name. Use the locale-aware
            // "Someone" fallback in the email body via $isAnonymous = true.
            LocaleContext::withLocale($receiver, function () use ($receiver, $receiverId, $rating, $comment) {
                Notification::createNotification(
                    $receiverId,
                    __('notifications.review_received_in_app', [
                        'name'   => __('emails.notification.someone'),
                        'rating' => $rating,
                    ]),
                    '/profile/' . $receiverId . '/reviews',
                    'federation_review'
                );

                // Email honours $prefs['email_reviews']. Anonymous flag flips
                // the rendered name to a locale-aware "Someone".
                \App\Services\NotificationDispatcher::sendReviewEmail(
                    $receiverId,
                    '', // reviewer name unknown — sendReviewEmail handles anonymous
                    $rating,
                    is_string($comment) ? $comment : null,
                    true
                );
            });

            Log::info('[HandleFederatedReviewReceived] notified reviewee', [
                'tenant_id'    => $event->tenantId,
                'partner_id'   => $event->externalPartnerId,
                'review_id'    => $event->localId,
                'receiver_id'  => $receiverId,
                'rating'       => $rating,
            ]);
        } catch (\Throwable $e) {
            Log::warning('HandleFederatedReviewReceived failed', [
                'tenant_id'  => $event->tenantId ?? null,
                'partner_id' => $event->externalPartnerId ?? null,
                'review_id'  => $event->localId ?? null,
                'error'      => $e->getMessage(),
            ]);
        } finally {
            TenantContext::reset();
        }
    }
}

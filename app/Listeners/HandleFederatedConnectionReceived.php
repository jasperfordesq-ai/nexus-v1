<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Listeners;

use App\Core\TenantContext;
use App\Events\FederatedConnectionReceived;
use App\I18n\LocaleContext;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * HandleFederatedConnectionReceived — fires when a partner federation node
 * sends us a connection request or accept aimed at one of our local members.
 *
 * The webhook controller already upserts the row into
 * `federation_inbound_connections`. This listener notifies the local user
 * so they actually see the inbound connection rather than only learning
 * about it on their next visit to the network page.
 */
class HandleFederatedConnectionReceived implements ShouldQueue
{
    public string $queue = 'federation';

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function handle(FederatedConnectionReceived $event): void
    {
        try {
            TenantContext::setById($event->tenantId);

            $localUserId = (int) ($event->shadowRow['local_user_id'] ?? 0);
            if ($localUserId <= 0) {
                return;
            }

            $localUser = DB::table('users')
                ->where('id', $localUserId)
                ->where('tenant_id', $event->tenantId)
                ->where('status', 'active')
                ->select(['id', 'first_name', 'name', 'preferred_language', 'federation_notifications_enabled'])
                ->first();
            if (! $localUser) {
                Log::info('[HandleFederatedConnectionReceived] local user gone', [
                    'tenant_id'     => $event->tenantId,
                    'partner_id'    => $event->externalPartnerId,
                    'local_user_id' => $localUserId,
                ]);
                return;
            }

            // Honour the user's federation-notifications preference.
            if (isset($localUser->federation_notifications_enabled)
                && (int) $localUser->federation_notifications_enabled === 0) {
                Log::info('[HandleFederatedConnectionReceived] user opted out of federation notifications', [
                    'tenant_id'     => $event->tenantId,
                    'local_user_id' => $localUserId,
                ]);
                return;
            }

            $status = (string) ($event->shadowRow['status'] ?? 'pending');

            // Render the bell preview in the recipient's locale.
            LocaleContext::withLocale($localUser, function () use ($localUserId, $status) {
                $key = $status === 'accepted'
                    ? 'notifications.federation_connection_accepted'
                    : 'notifications.federation_connection_request';
                $fallback = $status === 'accepted'
                    ? 'A partner-platform member accepted your connection request.'
                    : 'A partner-platform member sent you a connection request.';
                $message = __($key);
                if ($message === $key) {
                    $message = $fallback;
                }
                Notification::createNotification(
                    $localUserId,
                    $message,
                    '/network',
                    'federation_connection'
                );
            });

            Log::info('[HandleFederatedConnectionReceived] notified local user', [
                'tenant_id'     => $event->tenantId,
                'partner_id'    => $event->externalPartnerId,
                'local_user_id' => $localUserId,
                'status'        => $status,
            ]);
        } catch (\Throwable $e) {
            Log::warning('HandleFederatedConnectionReceived failed', [
                'tenant_id'  => $event->tenantId ?? null,
                'partner_id' => $event->externalPartnerId ?? null,
                'local_id'   => $event->localId ?? null,
                'error'      => $e->getMessage(),
            ]);
        } finally {
            TenantContext::reset();
        }
    }
}

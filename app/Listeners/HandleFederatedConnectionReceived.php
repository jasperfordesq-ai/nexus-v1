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
use App\Services\FederationEmailService;
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
        $previousTenantId = TenantContext::currentId();

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
            $externalUserName = trim((string) (
                $event->shadowRow['external_user_name']
                ?? $event->shadowRow['sender_name']
                ?? $event->shadowRow['name']
                ?? $event->shadowRow['external_user_id']
                ?? ''
            ));
            if ($externalUserName === '') {
                $externalUserName = __('emails.common.fallback_federation_member');
            }
            $partnerName = DB::table('federation_external_partners')
                ->where('id', $event->externalPartnerId)
                ->where('tenant_id', $event->tenantId)
                ->value('name') ?: __('emails.common.fallback_partner_community');

            // Render the bell preview in the recipient's locale.
            LocaleContext::withLocale($localUser, function () use ($event, $localUserId, $status, $externalUserName, $partnerName) {
                $key = $status === 'accepted'
                    ? 'svc_notifications.federation.connection_accepted'
                    : 'svc_notifications.federation.connection_request';
                $message = __($key, [
                    'name' => $externalUserName,
                    'sender' => $externalUserName,
                    'community' => $partnerName,
                ]);

                $exists = DB::table('notifications')
                    ->where('tenant_id', $event->tenantId)
                    ->where('user_id', $localUserId)
                    ->where('type', 'federation_connection')
                    ->where('link', '/network')
                    ->where('message', $message)
                    ->exists();

                if (! $exists) {
                    Notification::createNotification(
                        $localUserId,
                        $message,
                        '/network',
                        'federation_connection',
                        false,
                        $event->tenantId
                    );
                }
            });

            $sent = FederationEmailService::sendExternalConnectionNotification(
                $localUserId,
                $event->tenantId,
                $externalUserName,
                $partnerName,
                $status
            );

            if (! $sent) {
                Log::warning('[HandleFederatedConnectionReceived] external connection email returned false', [
                    'tenant_id'     => $event->tenantId,
                    'partner_id'    => $event->externalPartnerId,
                    'local_user_id' => $localUserId,
                    'status'        => $status,
                ]);
            }

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
            TenantContext::restoreAfterScopedListener($previousTenantId);
        }
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FederatedMessageService — cross-tenant messaging between federated timebank members.
 */
class FederatedMessageService
{
    public function __construct()
    {
    }

    /**
     * Send a federated message.
     *
     * @return array{success: bool, error?: string, message_id?: int}
     */
    public static function sendMessage(int $senderId, int $receiverId, int $receiverTenantId, string $subject, string $body): array
    {
        try {
            $sender = DB::table('users')->where('id', $senderId)->where('tenant_id', TenantContext::getId())->first();
            if (!$sender) {
                return ['success' => false, 'error' => 'Sender not found'];
            }

            $receiver = DB::table('users')
                ->where('id', $receiverId)
                ->where('tenant_id', $receiverTenantId)
                ->first();
            if (!$receiver) {
                return ['success' => false, 'error' => 'Receiver not found in specified tenant'];
            }

            // Check sender has opted into federation
            $senderSettings = DB::table('federation_user_settings')
                ->where('user_id', $senderId)
                ->first();

            if (!$senderSettings || !$senderSettings->federation_optin || !$senderSettings->messaging_enabled_federated) {
                return ['success' => false, 'error' => 'Sender has not enabled federated messaging'];
            }

            // Check receiver has opted into federated messaging
            $receiverOptIn = DB::table('federation_user_settings')
                ->where('user_id', $receiverId)
                ->where('messaging_enabled_federated', true)
                ->exists();
            if (!$receiverOptIn) {
                return ['success' => false, 'error' => 'Receiver has not opted into federated messaging'];
            }

            // Check active federation partnership between tenants
            $senderTenantId = $sender->tenant_id;
            $partnershipActive = DB::table('federation_partnerships')
                ->where(function ($outer) use ($senderTenantId, $receiverTenantId) {
                    $outer->where(function ($q) use ($senderTenantId, $receiverTenantId) {
                        $q->where('tenant_id', $senderTenantId)->where('partner_tenant_id', $receiverTenantId);
                    })->orWhere(function ($q) use ($senderTenantId, $receiverTenantId) {
                        $q->where('tenant_id', $receiverTenantId)->where('partner_tenant_id', $senderTenantId);
                    });
                })
                ->where('status', 'active')
                ->where('messaging_enabled', 1)
                ->exists();
            if (!$partnershipActive) {
                return ['success' => false, 'error' => 'No active federation partnership between tenants'];
            }

            $messageId = DB::table('federation_messages')->insertGetId([
                'sender_user_id'    => $senderId,
                'sender_tenant_id'  => $sender->tenant_id,
                'receiver_user_id'  => $receiverId,
                'receiver_tenant_id' => $receiverTenantId,
                'subject'           => $subject,
                'body'              => $body,
                'direction'         => 'outbound',
                'status'            => 'pending',
                'created_at'        => now(),
            ]);

            return ['success' => true, 'message_id' => $messageId];
        } catch (\Throwable $e) {
            Log::warning('Failed to send federated message', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get unread message count for a user.
     */
    public static function getUnreadCount(int $userId): int
    {
        try {
            return (int) DB::table('federation_messages')
                ->where('receiver_user_id', $userId)
                ->where('receiver_tenant_id', TenantContext::getId())
                ->whereIn('status', ['pending', 'delivered', 'unread'])
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Get federated user info for messaging context.
     *
     * @return array|null User info array or null if not found
     */
    public static function getFederatedUserInfo(int $userId, int $tenantId): ?array
    {
        try {
            $row = DB::selectOne(
                "SELECT u.id,
                        COALESCE(NULLIF(u.name, ''), TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')))) as name,
                        u.avatar_url, u.tenant_id,
                        t.name as tenant_name,
                        COALESCE(fus.service_reach, 'local_only') as service_reach,
                        COALESCE(fus.messaging_enabled_federated, 0) as messaging_enabled_federated,
                        COALESCE(fus.federation_optin, 0) as federation_optin
                 FROM users u
                 INNER JOIN tenants t ON u.tenant_id = t.id
                 LEFT JOIN federation_user_settings fus ON u.id = fus.user_id
                 WHERE u.id = ? AND u.tenant_id = ? AND u.status = 'active'",
                [$userId, $tenantId]
            );

            if (!$row) {
                return null;
            }

            return [
                'id'                          => (int) $row->id,
                'name'                        => $row->name,
                'avatar_url'                  => $row->avatar_url,
                'tenant_id'                   => (int) $row->tenant_id,
                'tenant_name'                 => $row->tenant_name,
                'service_reach'               => $row->service_reach,
                'messaging_enabled_federated' => (bool) $row->messaging_enabled_federated,
                'federation_optin'            => (bool) $row->federation_optin,
            ];
        } catch (\Throwable $e) {
            Log::warning('Failed to get federated user info', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Store a message received from an external federation partner.
     *
     * @return array{success: bool, message_id?: int, error?: string}
     */
    public static function storeExternalMessage(
        int $receiverUserId,
        int $externalPartnerId,
        int $externalSenderId,
        string $senderName,
        string $partnerName,
        string $subject,
        string $body,
        ?string $externalMessageId = null
    ): array {
        try {
            // Sanitize and limit external input
            $senderName = substr($senderName, 0, 255);
            $partnerName = substr($partnerName, 0, 255);
            $subject = substr($subject ?? '', 0, 500);
            $body = substr($body, 0, 10000);
            $externalMessageId = $externalMessageId ? substr($externalMessageId, 0, 255) : null;

            $receiver = DB::table('users')->where('id', $receiverUserId)->where('tenant_id', TenantContext::getId())->where('status', 'active')->first();
            if (!$receiver) {
                return ['success' => false, 'error' => 'Receiver not found'];
            }

            // FED-001: Verify receiver has opted into federated messaging
            $receiverOptIn = DB::table('federation_user_settings')
                ->where('user_id', $receiverUserId)
                ->where('federation_optin', 1)
                ->where('messaging_enabled_federated', 1)
                ->exists();
            if (!$receiverOptIn) {
                return ['success' => false, 'error' => 'Receiver has not opted into federated messaging'];
            }

            $messageId = DB::table('federation_messages')->insertGetId([
                'sender_user_id'         => $externalSenderId,
                'sender_tenant_id'       => 0, // External origin — not a real tenant ID
                'receiver_user_id'       => $receiverUserId,
                'receiver_tenant_id'     => $receiver->tenant_id,
                'subject'                => $subject,
                'body'                   => $body,
                'external_partner_id'    => $externalPartnerId,
                'external_receiver_name' => $senderName,
                'external_message_id'    => $externalMessageId,
                'direction'              => 'inbound',
                'status'                 => 'pending',
                'created_at'             => now(),
            ]);

            return ['success' => true, 'message_id' => $messageId];
        } catch (\Throwable $e) {
            Log::warning('Failed to store external message', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

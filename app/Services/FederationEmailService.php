<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * FederationEmailService — Email notifications for cross-tenant federation events.
 *
 * Sends notification emails for federated messages, transactions, weekly digests,
 * and partnership requests using Laravel's Mail facade.
 */
class FederationEmailService
{
    public function __construct()
    {
    }

    /**
     * Send a new message notification to a federation recipient.
     */
    public static function sendNewMessageNotification(int $recipientUserId, int $senderUserId, int $senderTenantId, string $messagePreview): bool
    {
        try {
            $recipient = self::getUserWithEmail($recipientUserId);
            if (!$recipient || empty($recipient->email)) {
                return false;
            }

            $sender = self::getUserBasicInfo($senderUserId);
            $senderTenant = DB::selectOne("SELECT name FROM tenants WHERE id = ?", [$senderTenantId]);

            $senderName = $sender ? trim(($sender->first_name ?? '') . ' ' . ($sender->last_name ?? '')) : 'A federation member';
            $tenantName = $senderTenant->name ?? 'a partner community';
            $preview = mb_substr(strip_tags($messagePreview), 0, 200);

            Mail::raw(
                "Hello " . trim(($recipient->first_name ?? '') . ' ' . ($recipient->last_name ?? '')) . ",\n\n"
                . "You have a new federated message from {$senderName} ({$tenantName}):\n\n"
                . "\"{$preview}\"\n\n"
                . "Log in to your timebank to read and reply.\n\n"
                . "— Project NEXUS Federation",
                function ($message) use ($recipient, $senderName) {
                    $message->to($recipient->email)
                        ->subject("New federated message from {$senderName}");
                }
            );

            Log::info('[FederationEmail] Message notification sent', [
                'recipient' => $recipientUserId,
                'sender' => $senderUserId,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('[FederationEmail] sendNewMessageNotification failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Send a transaction notification to a recipient.
     */
    public static function sendTransactionNotification(int $recipientUserId, int $senderUserId, int $senderTenantId, float $amount, string $description): bool
    {
        try {
            $recipient = self::getUserWithEmail($recipientUserId);
            if (!$recipient || empty($recipient->email)) {
                return false;
            }

            $sender = self::getUserBasicInfo($senderUserId);
            $senderTenant = DB::selectOne("SELECT name FROM tenants WHERE id = ?", [$senderTenantId]);

            $senderName = $sender ? trim(($sender->first_name ?? '') . ' ' . ($sender->last_name ?? '')) : 'A federation member';
            $tenantName = $senderTenant->name ?? 'a partner community';

            Mail::raw(
                "Hello " . trim(($recipient->first_name ?? '') . ' ' . ($recipient->last_name ?? '')) . ",\n\n"
                . "You have received a cross-community time credit transfer:\n\n"
                . "From: {$senderName} ({$tenantName})\n"
                . "Amount: {$amount} hour(s)\n"
                . "Description: {$description}\n\n"
                . "Log in to your timebank to view your updated balance.\n\n"
                . "— Project NEXUS Federation",
                function ($message) use ($recipient, $amount) {
                    $message->to($recipient->email)
                        ->subject("You received {$amount} hour(s) via federation");
                }
            );

            return true;
        } catch (\Exception $e) {
            Log::error('[FederationEmail] sendTransactionNotification failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Send a transaction confirmation to the sender.
     */
    public static function sendTransactionConfirmation(int $senderUserId, int $recipientUserId, int $recipientTenantId, float $amount, string $description, float $newBalance): bool
    {
        try {
            $sender = self::getUserWithEmail($senderUserId);
            if (!$sender || empty($sender->email)) {
                return false;
            }

            $recipient = self::getUserBasicInfo($recipientUserId);
            $recipientTenant = DB::selectOne("SELECT name FROM tenants WHERE id = ?", [$recipientTenantId]);

            $recipientName = $recipient ? trim(($recipient->first_name ?? '') . ' ' . ($recipient->last_name ?? '')) : 'a federation member';
            $tenantName = $recipientTenant->name ?? 'a partner community';

            Mail::raw(
                "Hello " . trim(($sender->first_name ?? '') . ' ' . ($sender->last_name ?? '')) . ",\n\n"
                . "Your cross-community time credit transfer has been processed:\n\n"
                . "To: {$recipientName} ({$tenantName})\n"
                . "Amount: {$amount} hour(s)\n"
                . "Description: {$description}\n"
                . "Your new balance: {$newBalance} hour(s)\n\n"
                . "— Project NEXUS Federation",
                function ($message) use ($sender, $amount) {
                    $message->to($sender->email)
                        ->subject("Federation transfer of {$amount} hour(s) confirmed");
                }
            );

            return true;
        } catch (\Exception $e) {
            Log::error('[FederationEmail] sendTransactionConfirmation failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Send a weekly federation digest to a user.
     */
    public static function sendWeeklyDigest(int $userId, int $tenantId): bool
    {
        try {
            $user = self::getUserWithEmail($userId);
            if (!$user || empty($user->email)) {
                return false;
            }

            $tenant = DB::selectOne("SELECT name FROM tenants WHERE id = ?", [$tenantId]);
            $tenantName = $tenant->name ?? 'your community';

            // Gather stats for the week
            $messageCount = (int) (DB::selectOne(
                "SELECT COUNT(*) as cnt FROM federation_messages
                 WHERE (sender_tenant_id = ? OR receiver_tenant_id = ?)
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                [$tenantId, $tenantId]
            )->cnt ?? 0);

            $transactionCount = (int) (DB::selectOne(
                "SELECT COUNT(*) as cnt FROM federation_transactions
                 WHERE (sender_tenant_id = ? OR receiver_tenant_id = ?)
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                [$tenantId, $tenantId]
            )->cnt ?? 0);

            $connectionCount = (int) (DB::selectOne(
                "SELECT COUNT(*) as cnt FROM federation_connections
                 WHERE (requester_tenant_id = ? OR receiver_tenant_id = ?)
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                [$tenantId, $tenantId]
            )->cnt ?? 0);

            $userName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

            Mail::raw(
                "Hello {$userName},\n\n"
                . "Here's your weekly federation activity summary for {$tenantName}:\n\n"
                . "- Cross-community messages: {$messageCount}\n"
                . "- Cross-community transactions: {$transactionCount}\n"
                . "- New federation connections: {$connectionCount}\n\n"
                . "Log in to see full details.\n\n"
                . "— Project NEXUS Federation",
                function ($message) use ($user, $tenantName) {
                    $message->to($user->email)
                        ->subject("Weekly Federation Digest — {$tenantName}");
                }
            );

            return true;
        } catch (\Exception $e) {
            Log::error('[FederationEmail] sendWeeklyDigest failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Send a partnership request notification to the admin(s) of the target tenant.
     */
    public static function sendPartnershipRequestNotification(int $targetTenantId, int $requestingTenantId, string $requestingTenantName, int $requestedLevel, ?string $notes = null): bool
    {
        try {
            $levelNames = [1 => 'Discovery', 2 => 'Social', 3 => 'Economic', 4 => 'Integrated'];
            $levelName = $levelNames[$requestedLevel] ?? 'Level ' . $requestedLevel;

            // Get admin users for target tenant
            $admins = DB::select(
                "SELECT id, email, first_name, last_name FROM users
                 WHERE tenant_id = ? AND role IN ('admin', 'super_admin') AND status = 'active' AND email IS NOT NULL
                 LIMIT 5",
                [$targetTenantId]
            );

            if (empty($admins)) {
                Log::warning('[FederationEmail] No admins found for partnership notification', [
                    'target_tenant' => $targetTenantId,
                ]);
                return false;
            }

            $notesText = $notes ? "\nNotes from the requesting community:\n\"{$notes}\"\n" : '';

            foreach ($admins as $admin) {
                $adminName = trim(($admin->first_name ?? '') . ' ' . ($admin->last_name ?? ''));

                Mail::raw(
                    "Hello {$adminName},\n\n"
                    . "{$requestingTenantName} has sent your community a federation partnership request.\n\n"
                    . "Requested level: {$levelName}\n"
                    . $notesText . "\n"
                    . "Log in to your admin panel to review and respond to this request.\n\n"
                    . "— Project NEXUS Federation",
                    function ($message) use ($admin, $requestingTenantName) {
                        $message->to($admin->email)
                            ->subject("Federation Partnership Request from {$requestingTenantName}");
                    }
                );
            }

            Log::info('[FederationEmail] Partnership notification sent', [
                'target_tenant' => $targetTenantId,
                'requesting_tenant' => $requestingTenantId,
                'admins_notified' => count($admins),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('[FederationEmail] sendPartnershipRequestNotification failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get user with email for notifications.
     */
    private static function getUserWithEmail(int $userId): ?object
    {
        return DB::selectOne(
            "SELECT id, email, first_name, last_name FROM users WHERE id = ? AND email IS NOT NULL",
            [$userId]
        );
    }

    /**
     * Get basic user info for display in emails.
     */
    private static function getUserBasicInfo(int $userId): ?object
    {
        return DB::selectOne(
            "SELECT id, first_name, last_name FROM users WHERE id = ?",
            [$userId]
        );
    }
}

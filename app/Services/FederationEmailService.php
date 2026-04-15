<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Core\EmailTemplateBuilder;
use App\Core\Mailer;
use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FederationEmailService — Email notifications for cross-tenant federation events.
 *
 * Sends notification emails for federated messages, transactions, weekly digests,
 * and partnership requests using rich HTML templates via EmailTemplateBuilder.
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
            $recipient = self::getUserWithEmail($recipientUserId, TenantContext::getId());
            if (!$recipient || empty($recipient->email)) {
                return false;
            }

            $sender = self::getUserBasicInfo($senderUserId, $senderTenantId);
            $senderTenant = DB::selectOne("SELECT name FROM tenants WHERE id = ?", [$senderTenantId]);

            $senderName = $sender ? trim(($sender->first_name ?? '') . ' ' . ($sender->last_name ?? '')) : 'A federation member';
            $tenantName = $senderTenant->name ?? 'a partner community';
            $preview = mb_substr(strip_tags($messagePreview), 0, 200);

            $recipientName = trim(($recipient->first_name ?? '') . ' ' . ($recipient->last_name ?? ''));
            $safeSenderName = htmlspecialchars($senderName, ENT_QUOTES, 'UTF-8');
            $safeTenantName = htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8');
            $safePreview = htmlspecialchars($preview, ENT_QUOTES, 'UTF-8');

            $subject = __('emails.federation.message_subject', ['sender' => $senderName]);

            $html = EmailTemplateBuilder::make()
                ->theme('federation')
                ->title(__('emails.federation.message_title'))
                ->previewText(__('emails.federation.message_preview', ['sender' => $senderName, 'community' => $tenantName]))
                ->greeting($recipientName)
                ->paragraph(__('emails.federation.message_body', ['sender' => $safeSenderName, 'community' => $safeTenantName]))
                ->infoCard([
                    __('emails.federation.label_from') => $safeSenderName,
                    __('emails.federation.label_community') => $safeTenantName,
                ])
                ->blockquote($safePreview)
                ->button(__('emails.federation.read_reply'), EmailTemplateBuilder::tenantUrl('/messages'))
                ->render();

            $mailer = Mailer::forCurrentTenant();
            $mailer->send($recipient->email, $subject, $html);

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
            $recipient = self::getUserWithEmail($recipientUserId, TenantContext::getId());
            if (!$recipient || empty($recipient->email)) {
                return false;
            }

            $sender = self::getUserBasicInfo($senderUserId, $senderTenantId);
            $senderTenant = DB::selectOne("SELECT name FROM tenants WHERE id = ?", [$senderTenantId]);

            $senderName = $sender ? trim(($sender->first_name ?? '') . ' ' . ($sender->last_name ?? '')) : 'A federation member';
            $tenantName = $senderTenant->name ?? 'a partner community';

            $recipientName = trim(($recipient->first_name ?? '') . ' ' . ($recipient->last_name ?? ''));
            $safeSenderName = htmlspecialchars($senderName, ENT_QUOTES, 'UTF-8');
            $safeTenantName = htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8');
            $safeDescription = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');

            $subject = __('emails.federation.transaction_received_subject', ['amount' => $amount]);

            $html = EmailTemplateBuilder::make()
                ->theme('success')
                ->title(__('emails.federation.transaction_received_title'))
                ->previewText(__('emails.federation.transaction_received_preview', ['amount' => $amount, 'sender' => $senderName]))
                ->greeting($recipientName)
                ->paragraph(__('emails.federation.transaction_received_body', ['sender' => $safeSenderName, 'community' => $safeTenantName]))
                ->infoCard([
                    __('emails.federation.label_from') => "{$safeSenderName} ({$safeTenantName})",
                    __('emails.federation.label_amount') => __('emails.federation.hours', ['amount' => $amount]),
                    __('emails.federation.label_description') => $safeDescription,
                ])
                ->button(__('emails.federation.view_wallet'), EmailTemplateBuilder::tenantUrl('/wallet'))
                ->render();

            $mailer = Mailer::forCurrentTenant();
            $mailer->send($recipient->email, $subject, $html);

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
            $sender = self::getUserWithEmail($senderUserId, TenantContext::getId());
            if (!$sender || empty($sender->email)) {
                return false;
            }

            $recipient = self::getUserBasicInfo($recipientUserId, $recipientTenantId);
            $recipientTenant = DB::selectOne("SELECT name FROM tenants WHERE id = ?", [$recipientTenantId]);

            $recipientName = $recipient ? trim(($recipient->first_name ?? '') . ' ' . ($recipient->last_name ?? '')) : 'a federation member';
            $tenantName = $recipientTenant->name ?? 'a partner community';

            $senderName = trim(($sender->first_name ?? '') . ' ' . ($sender->last_name ?? ''));
            $safeRecipientName = htmlspecialchars($recipientName, ENT_QUOTES, 'UTF-8');
            $safeTenantName = htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8');
            $safeDescription = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');

            $subject = __('emails.federation.transaction_confirmed_subject', ['amount' => $amount]);

            $html = EmailTemplateBuilder::make()
                ->theme('success')
                ->title(__('emails.federation.transaction_confirmed_title'))
                ->previewText(__('emails.federation.transaction_confirmed_preview', ['amount' => $amount, 'recipient' => $recipientName]))
                ->greeting($senderName)
                ->paragraph(__('emails.federation.transaction_confirmed_body'))
                ->infoCard([
                    __('emails.federation.label_to') => "{$safeRecipientName} ({$safeTenantName})",
                    __('emails.federation.label_amount') => __('emails.federation.hours', ['amount' => $amount]),
                    __('emails.federation.label_description') => $safeDescription,
                    __('emails.federation.label_new_balance') => __('emails.federation.hours', ['amount' => $newBalance]),
                ])
                ->button(__('emails.federation.view_wallet'), EmailTemplateBuilder::tenantUrl('/wallet'))
                ->render();

            $mailer = Mailer::forCurrentTenant();
            $mailer->send($sender->email, $subject, $html);

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
            $user = self::getUserWithEmail($userId, $tenantId);
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

            $subject = __('emails.federation.digest_subject', ['community' => $tenantName]);

            $builder = EmailTemplateBuilder::make()
                ->theme('federation')
                ->title(__('emails.federation.digest_title'))
                ->previewText(__('emails.federation.digest_preview', ['community' => $tenantName]))
                ->greeting($userName)
                ->paragraph(__('emails.federation.digest_body'))
                ->statCards([
                    ['value' => (string) $messageCount, 'label' => __('emails.federation.label_messages'), 'icon' => "\xF0\x9F\x92\xAC"],
                    ['value' => (string) $transactionCount, 'label' => __('emails.federation.label_transactions'), 'icon' => "\xF0\x9F\x92\xB0"],
                    ['value' => (string) $connectionCount, 'label' => __('emails.federation.label_connections'), 'icon' => "\xF0\x9F\xA4\x9D"],
                ]);

            if ($messageCount === 0 && $transactionCount === 0 && $connectionCount === 0) {
                $builder->paragraph(__('emails.federation.digest_quiet'));
            }

            $html = $builder
                ->button(__('emails.federation.explore_federation'), EmailTemplateBuilder::tenantUrl('/federation'))
                ->render();

            $mailer = Mailer::forCurrentTenant();
            $mailer->send($user->email, $subject, $html);

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
            $levelNames = [
                1 => __('emails.federation.level_discovery'),
                2 => __('emails.federation.level_social'),
                3 => __('emails.federation.level_economic'),
                4 => __('emails.federation.level_integrated'),
            ];
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

            $safeRequestingName = htmlspecialchars($requestingTenantName, ENT_QUOTES, 'UTF-8');

            foreach ($admins as $admin) {
                $adminName = trim(($admin->first_name ?? '') . ' ' . ($admin->last_name ?? ''));

                $subject = __('emails.federation.partnership_subject', ['community' => $requestingTenantName]);

                $builder = EmailTemplateBuilder::make()
                    ->theme('federation')
                    ->title(__('emails.federation.partnership_title'))
                    ->previewText(__('emails.federation.partnership_preview', ['community' => $requestingTenantName]))
                    ->greeting($adminName)
                    ->paragraph(__('emails.federation.partnership_body', ['community' => $safeRequestingName]))
                    ->infoCard([
                        __('emails.federation.label_from_community') => $safeRequestingName,
                        __('emails.federation.label_requested_level') => htmlspecialchars($levelName, ENT_QUOTES, 'UTF-8'),
                    ]);

                if ($notes !== null && $notes !== '') {
                    $builder->blockquote(
                        htmlspecialchars($notes, ENT_QUOTES, 'UTF-8'),
                        $safeRequestingName
                    );
                }

                $html = $builder
                    ->button(__('emails.federation.review_request'), EmailTemplateBuilder::tenantUrl('/admin/federation'))
                    ->render();

                $mailer = Mailer::forCurrentTenant();
                $mailer->send($admin->email, $subject, $html);
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
     * Send a connection request notification to the recipient.
     */
    public static function sendConnectionRequestNotification(int $recipientUserId, int $senderUserId, int $senderTenantId): bool
    {
        try {
            $recipient = self::getUserWithEmail($recipientUserId, TenantContext::getId());
            if (!$recipient || empty($recipient->email)) {
                return false;
            }

            $sender = self::getUserBasicInfo($senderUserId, $senderTenantId);
            $senderTenant = DB::selectOne("SELECT name FROM tenants WHERE id = ?", [$senderTenantId]);

            $senderName = $sender ? trim(($sender->first_name ?? '') . ' ' . ($sender->last_name ?? '')) : 'A federation member';
            $tenantName = $senderTenant->name ?? 'a partner community';

            $recipientName = trim(($recipient->first_name ?? '') . ' ' . ($recipient->last_name ?? ''));
            $safeSenderName = htmlspecialchars($senderName, ENT_QUOTES, 'UTF-8');
            $safeTenantName = htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8');

            $subject = __('emails.federation.connection_request_subject', ['community' => $tenantName]);

            $html = EmailTemplateBuilder::make()
                ->theme('federation')
                ->title(__('emails.federation.connection_request_heading'))
                ->previewText(__('emails.federation.connection_request_body', ['name' => $senderName, 'community' => $tenantName]))
                ->greeting($recipientName)
                ->paragraph(__('emails.federation.connection_request_body', ['name' => $safeSenderName, 'community' => $safeTenantName]))
                ->button(__('emails.federation.connection_request_cta'), EmailTemplateBuilder::tenantUrl('/profile/' . $senderUserId))
                ->render();

            TenantContext::setById(TenantContext::getId());
            $mailer = Mailer::forCurrentTenant();
            $mailer->send($recipient->email, $subject, $html);

            Log::info('[FederationEmail] Connection request notification sent', [
                'recipient' => $recipientUserId,
                'sender' => $senderUserId,
                'sender_tenant' => $senderTenantId,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('[FederationEmail] sendConnectionRequestNotification failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Send a connection accepted notification to the original sender.
     */
    public static function sendConnectionAcceptedNotification(int $senderUserId, int $recipientUserId, int $recipientTenantId): bool
    {
        try {
            $sender = self::getUserWithEmail($senderUserId, TenantContext::getId());
            if (!$sender || empty($sender->email)) {
                return false;
            }

            $recipient = self::getUserBasicInfo($recipientUserId, $recipientTenantId);
            $recipientTenant = DB::selectOne("SELECT name FROM tenants WHERE id = ?", [$recipientTenantId]);

            $recipientName = $recipient ? trim(($recipient->first_name ?? '') . ' ' . ($recipient->last_name ?? '')) : 'A federation member';
            $tenantName = $recipientTenant->name ?? 'a partner community';

            $senderName = trim(($sender->first_name ?? '') . ' ' . ($sender->last_name ?? ''));
            $safeRecipientName = htmlspecialchars($recipientName, ENT_QUOTES, 'UTF-8');
            $safeTenantName = htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8');

            $subject = __('emails.federation.connection_accepted_subject', ['name' => $recipientName]);

            $html = EmailTemplateBuilder::make()
                ->theme('federation')
                ->title(__('emails.federation.connection_accepted_heading'))
                ->previewText(__('emails.federation.connection_accepted_body', ['name' => $recipientName, 'community' => $tenantName]))
                ->greeting($senderName)
                ->paragraph(__('emails.federation.connection_accepted_body', ['name' => $safeRecipientName, 'community' => $safeTenantName]))
                ->button(__('emails.federation.connection_accepted_cta'), EmailTemplateBuilder::tenantUrl('/profile/' . $recipientUserId))
                ->render();

            TenantContext::setById(TenantContext::getId());
            $mailer = Mailer::forCurrentTenant();
            $mailer->send($sender->email, $subject, $html);

            Log::info('[FederationEmail] Connection accepted notification sent', [
                'sender' => $senderUserId,
                'recipient' => $recipientUserId,
                'recipient_tenant' => $recipientTenantId,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('[FederationEmail] sendConnectionAcceptedNotification failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get user with email for notifications.
     */
    private static function getUserWithEmail(int $userId, int $tenantId): ?object
    {
        return DB::selectOne(
            "SELECT id, email, first_name, last_name FROM users WHERE id = ? AND tenant_id = ? AND email IS NOT NULL",
            [$userId, $tenantId]
        );
    }

    /**
     * Get basic user info for display in emails.
     */
    private static function getUserBasicInfo(int $userId, int $tenantId): ?object
    {
        return DB::selectOne(
            "SELECT id, first_name, last_name FROM users WHERE id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        );
    }
}

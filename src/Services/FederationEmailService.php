<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\Mailer;

/**
 * FederationEmailService
 *
 * Sends email notifications for federated messaging and transactions.
 * Uses purple/violet theme colors consistent with federation UI.
 */
class FederationEmailService
{
    // Theme colors - Federation purple theme
    private const BRAND_COLOR = '#8b5cf6';
    private const BRAND_COLOR_DARK = '#7c3aed';
    private const ACCENT_COLOR = '#a78bfa';
    private const SUCCESS_COLOR = '#10b981';
    private const TEXT_COLOR = '#374151';
    private const MUTED_COLOR = '#6b7280';
    private const BG_COLOR = '#f3f4f6';

    /**
     * Send notification when user receives a new federated message
     */
    public static function sendNewMessageNotification(
        int $recipientUserId,
        int $senderUserId,
        int $senderTenantId,
        string $messagePreview
    ): bool {
        // Get recipient info
        $recipient = Database::query(
            "SELECT u.id, u.email, u.first_name, u.last_name, u.tenant_id,
                    ufs.email_notifications
             FROM users u
             LEFT JOIN federation_user_settings ufs ON u.id = ufs.user_id
             WHERE u.id = ?",
            [$recipientUserId]
        )->fetch();

        if (!$recipient || empty($recipient['email'])) {
            return false;
        }

        // Check if email notifications are enabled
        if ($recipient['email_notifications'] === 0) {
            return false;
        }

        // Get sender info
        $sender = Database::query(
            "SELECT u.first_name, u.last_name, t.name as tenant_name
             FROM users u
             JOIN tenants t ON u.tenant_id = t.id
             WHERE u.id = ? AND u.tenant_id = ?",
            [$senderUserId, $senderTenantId]
        )->fetch();

        if (!$sender) {
            return false;
        }

        // Get recipient's tenant info for branding
        $recipientTenant = Database::query(
            "SELECT name FROM tenants WHERE id = ?",
            [$recipient['tenant_id']]
        )->fetch();

        $senderName = trim($sender['first_name'] . ' ' . $sender['last_name']);
        $siteName = $recipientTenant['name'] ?? 'Timebank';

        // Temporarily set tenant context for URL generation
        $basePath = '/' . ($recipientTenant['name'] ?? '');
        $siteUrl = $_ENV['APP_URL'] ?? 'http://localhost';

        $subject = "New federated message from {$senderName}";

        $html = self::generateNewMessageHtml(
            $recipient,
            $sender,
            $messagePreview,
            $basePath,
            $siteName,
            $siteUrl
        );

        try {
            $mailer = new Mailer();
            return $mailer->send($recipient['email'], $subject, $html);
        } catch (\Throwable $e) {
            error_log("Failed to send federation message notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification when user receives a federated transaction
     */
    public static function sendTransactionNotification(
        int $recipientUserId,
        int $senderUserId,
        int $senderTenantId,
        float $amount,
        string $description
    ): bool {
        // Get recipient info
        $recipient = Database::query(
            "SELECT u.id, u.email, u.first_name, u.last_name, u.tenant_id,
                    ufs.email_notifications
             FROM users u
             LEFT JOIN federation_user_settings ufs ON u.id = ufs.user_id
             WHERE u.id = ?",
            [$recipientUserId]
        )->fetch();

        if (!$recipient || empty($recipient['email'])) {
            return false;
        }

        // Check if email notifications are enabled
        if ($recipient['email_notifications'] === 0) {
            return false;
        }

        // Get sender info
        $sender = Database::query(
            "SELECT u.first_name, u.last_name, t.name as tenant_name
             FROM users u
             JOIN tenants t ON u.tenant_id = t.id
             WHERE u.id = ? AND u.tenant_id = ?",
            [$senderUserId, $senderTenantId]
        )->fetch();

        if (!$sender) {
            return false;
        }

        // Get recipient's tenant info for branding
        $recipientTenant = Database::query(
            "SELECT name FROM tenants WHERE id = ?",
            [$recipient['tenant_id']]
        )->fetch();

        $senderName = trim($sender['first_name'] . ' ' . $sender['last_name']);
        $siteName = $recipientTenant['name'] ?? 'Timebank';
        $basePath = '/' . ($recipientTenant['name'] ?? '');
        $siteUrl = $_ENV['APP_URL'] ?? 'http://localhost';

        $subject = "You received {$amount} hours from {$senderName}";

        $html = self::generateTransactionHtml(
            $recipient,
            $sender,
            $amount,
            $description,
            $basePath,
            $siteName,
            $siteUrl
        );

        try {
            $mailer = new Mailer();
            return $mailer->send($recipient['email'], $subject, $html);
        } catch (\Throwable $e) {
            error_log("Failed to send federation transaction notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send confirmation to sender after a successful federated transaction
     */
    public static function sendTransactionConfirmation(
        int $senderUserId,
        int $recipientUserId,
        int $recipientTenantId,
        float $amount,
        string $description,
        float $newBalance
    ): bool {
        // Get sender info
        $sender = Database::query(
            "SELECT u.id, u.email, u.first_name, u.last_name, u.tenant_id,
                    ufs.email_notifications
             FROM users u
             LEFT JOIN federation_user_settings ufs ON u.id = ufs.user_id
             WHERE u.id = ?",
            [$senderUserId]
        )->fetch();

        if (!$sender || empty($sender['email'])) {
            return false;
        }

        // Check if email notifications are enabled
        if ($sender['email_notifications'] === 0) {
            return false;
        }

        // Get recipient info
        $recipient = Database::query(
            "SELECT u.first_name, u.last_name, t.name as tenant_name
             FROM users u
             JOIN tenants t ON u.tenant_id = t.id
             WHERE u.id = ? AND u.tenant_id = ?",
            [$recipientUserId, $recipientTenantId]
        )->fetch();

        if (!$recipient) {
            return false;
        }

        // Get sender's tenant info for branding
        $senderTenant = Database::query(
            "SELECT name, domain FROM tenants WHERE id = ?",
            [$sender['tenant_id']]
        )->fetch();

        $recipientName = trim($recipient['first_name'] . ' ' . $recipient['last_name']);
        $siteName = $senderTenant['name'] ?? 'Timebank';
        $basePath = '/' . ($senderTenant['domain'] ?? '');
        $siteUrl = $_ENV['APP_URL'] ?? 'http://localhost';

        $subject = "Transfer confirmed: {$amount} hours sent to {$recipientName}";

        $html = self::generateTransactionConfirmationHtml(
            $sender,
            $recipient,
            $amount,
            $description,
            $newBalance,
            $basePath,
            $siteName,
            $siteUrl
        );

        try {
            $mailer = new Mailer();
            return $mailer->send($sender['email'], $subject, $html);
        } catch (\Throwable $e) {
            error_log("Failed to send federation transaction confirmation: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send weekly federated activity digest
     */
    public static function sendWeeklyDigest(int $userId, int $tenantId): bool
    {
        // Get user info (verify tenant_id matches for security)
        $user = Database::query(
            "SELECT u.id, u.email, u.first_name, u.last_name, u.tenant_id,
                    ufs.email_notifications
             FROM users u
             LEFT JOIN federation_user_settings ufs ON u.id = ufs.user_id
             WHERE u.id = ? AND u.tenant_id = ?",
            [$userId, $tenantId]
        )->fetch();

        if (!$user || empty($user['email']) || $user['email_notifications'] === 0) {
            return false;
        }

        $weekAgo = date('Y-m-d H:i:s', strtotime('-7 days'));

        // Get federated activity stats
        $messageCount = Database::query(
            "SELECT COUNT(*) FROM federation_messages
             WHERE ((sender_user_id = ? AND sender_tenant_id = ?)
                OR (receiver_user_id = ? AND receiver_tenant_id = ?))
             AND created_at >= ?",
            [$userId, $tenantId, $userId, $tenantId, $weekAgo]
        )->fetchColumn();

        $transactionCount = Database::query(
            "SELECT COUNT(*) FROM federation_transactions
             WHERE ((sender_user_id = ? AND sender_tenant_id = ?)
                OR (receiver_user_id = ? AND receiver_tenant_id = ?))
             AND created_at >= ?",
            [$userId, $tenantId, $userId, $tenantId, $weekAgo]
        )->fetchColumn();

        $hoursReceived = Database::query(
            "SELECT COALESCE(SUM(amount), 0) FROM federation_transactions
             WHERE receiver_user_id = ? AND receiver_tenant_id = ?
             AND status = 'completed' AND created_at >= ?",
            [$userId, $tenantId, $weekAgo]
        )->fetchColumn();

        // Skip if no activity
        if ($messageCount == 0 && $transactionCount == 0) {
            return false;
        }

        // Get tenant info for branding
        $tenant = Database::query("SELECT name FROM tenants WHERE id = ?", [$tenantId])->fetch();
        $siteName = $tenant['name'] ?? 'Timebank';
        $basePath = '/' . ($tenant['name'] ?? '');
        $siteUrl = $_ENV['APP_URL'] ?? 'http://localhost';

        $subject = "Your Federation Activity This Week - {$siteName}";

        $html = self::generateDigestHtml(
            $user,
            [
                'message_count' => (int) $messageCount,
                'transaction_count' => (int) $transactionCount,
                'hours_received' => (float) $hoursReceived
            ],
            $basePath,
            $siteName,
            $siteUrl
        );

        try {
            $mailer = new Mailer();
            return $mailer->send($user['email'], $subject, $html);
        } catch (\Throwable $e) {
            error_log("Failed to send federation digest: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get email wrapper with consistent styling
     */
    private static function getEmailWrapper(string $content, string $siteName, string $siteUrl, string $basePath, string $previewText = ''): string
    {
        $brandColor = self::BRAND_COLOR;
        $brandColorDark = self::BRAND_COLOR_DARK;
        $bgColor = self::BG_COLOR;
        $year = date('Y');
        $settingsUrl = $siteUrl . $basePath . '/settings#federation';

        return <<<HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{$siteName}</title>
    <style type="text/css">
        body, table, td, p, a, li { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; background-color: {$bgColor}; }
        body, table, td, a { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }
        a { color: {$brandColor}; text-decoration: underline; }
        .button-primary:hover { background-color: {$brandColorDark} !important; }
        @media screen and (max-width: 600px) {
            .email-container { width: 100% !important; max-width: 100% !important; }
            .mobile-padding { padding-left: 20px !important; padding-right: 20px !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: {$bgColor};">
    <div style="display: none; max-height: 0; overflow: hidden;">{$previewText}</div>

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: {$bgColor};">
        <tr>
            <td style="padding: 40px 10px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" align="center" class="email-container" style="margin: auto;">
                    {$content}

                    <tr>
                        <td style="background-color: #f9fafb; padding: 30px 40px; border-radius: 0 0 16px 16px; border-top: 1px solid #e5e7eb;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="text-align: center; padding-bottom: 15px;">
                                        <p style="margin: 0; font-size: 14px; color: #6b7280;">
                                            &copy; {$year} {$siteName}. All rights reserved.
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: center;">
                                        <a href="{$settingsUrl}" style="color: #6b7280; text-decoration: underline; font-size: 13px;">Manage federation email preferences</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    /**
     * Generate new message notification HTML
     */
    private static function generateNewMessageHtml(
        array $recipient,
        array $sender,
        string $messagePreview,
        string $basePath,
        string $siteName,
        string $siteUrl
    ): string {
        $firstName = htmlspecialchars($recipient['first_name']);
        $senderName = htmlspecialchars(trim($sender['first_name'] . ' ' . $sender['last_name']));
        $senderTimebank = htmlspecialchars($sender['tenant_name']);
        $preview = htmlspecialchars(substr($messagePreview, 0, 200));
        if (strlen($messagePreview) > 200) $preview .= '...';

        $brandColor = self::BRAND_COLOR;
        $brandColorDark = self::BRAND_COLOR_DARK;
        $textColor = self::TEXT_COLOR;
        $mutedColor = self::MUTED_COLOR;
        $messagesUrl = $siteUrl . $basePath . '/federation/messages';

        $content = <<<HTML
                    <tr>
                        <td style="padding: 40px; text-align: center; background: linear-gradient(135deg, {$brandColor} 0%, #7c3aed 100%); border-radius: 16px 16px 0 0;">
                            <div style="font-size: 56px; margin-bottom: 16px;">&#128172;</div>
                            <h1 style="margin: 0 0 10px; font-size: 28px; font-weight: 700; color: #ffffff;">New Federated Message</h1>
                            <p style="margin: 0; font-size: 16px; color: rgba(255,255,255,0.9);">Hey {$firstName}, you have a new message!</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color: #ffffff; padding: 40px;" class="mobile-padding">
                            <div style="background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%); border-radius: 16px; padding: 24px; margin-bottom: 24px; border-left: 4px solid {$brandColor};">
                                <p style="margin: 0 0 8px; font-size: 14px; font-weight: 600; color: {$brandColor};">
                                    <span style="margin-right: 8px;">&#127970;</span> From {$senderTimebank}
                                </p>
                                <p style="margin: 0 0 16px; font-size: 18px; font-weight: 700; color: {$textColor};">
                                    {$senderName}
                                </p>
                                <p style="margin: 0; font-size: 15px; color: {$mutedColor}; line-height: 1.6; font-style: italic;">
                                    "{$preview}"
                                </p>
                            </div>

                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="text-align: center;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">
                                            <tr>
                                                <td style="border-radius: 12px; background: linear-gradient(135deg, {$brandColor} 0%, {$brandColorDark} 100%); box-shadow: 0 4px 14px rgba(139, 92, 246, 0.35);" class="button-primary">
                                                    <a href="{$messagesUrl}" style="display: inline-block; padding: 16px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 12px;">View Message</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 24px 0 0; font-size: 14px; color: {$mutedColor}; text-align: center;">
                                This message was sent through the federation network from a partner timebank.
                            </p>
                        </td>
                    </tr>
HTML;

        return self::getEmailWrapper($content, $siteName, $siteUrl, $basePath, "New message from {$senderName}");
    }

    /**
     * Generate transaction notification HTML
     */
    private static function generateTransactionHtml(
        array $recipient,
        array $sender,
        float $amount,
        string $description,
        string $basePath,
        string $siteName,
        string $siteUrl
    ): string {
        $firstName = htmlspecialchars($recipient['first_name']);
        $senderName = htmlspecialchars(trim($sender['first_name'] . ' ' . $sender['last_name']));
        $senderTimebank = htmlspecialchars($sender['tenant_name']);
        $desc = htmlspecialchars(substr($description, 0, 200));

        $brandColor = self::BRAND_COLOR;
        $brandColorDark = self::BRAND_COLOR_DARK;
        $successColor = self::SUCCESS_COLOR;
        $textColor = self::TEXT_COLOR;
        $mutedColor = self::MUTED_COLOR;
        $transactionsUrl = $siteUrl . $basePath . '/federation/transactions';

        $content = <<<HTML
                    <tr>
                        <td style="padding: 40px; text-align: center; background: linear-gradient(135deg, {$successColor} 0%, #059669 100%); border-radius: 16px 16px 0 0;">
                            <div style="font-size: 56px; margin-bottom: 16px;">&#128181;</div>
                            <h1 style="margin: 0 0 10px; font-size: 28px; font-weight: 700; color: #ffffff;">Hours Received!</h1>
                            <p style="margin: 0; font-size: 16px; color: rgba(255,255,255,0.9);">Great news, {$firstName}!</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color: #ffffff; padding: 40px;" class="mobile-padding">
                            <div style="text-align: center; margin-bottom: 30px;">
                                <div style="font-size: 64px; font-weight: 900; color: {$successColor}; line-height: 1;">+{$amount}</div>
                                <p style="margin: 8px 0 0; font-size: 18px; color: {$textColor}; font-weight: 600;">hours credited to your balance</p>
                            </div>

                            <div style="background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%); border-radius: 16px; padding: 24px; margin-bottom: 24px;">
                                <p style="margin: 0 0 8px; font-size: 14px; font-weight: 600; color: {$brandColor};">
                                    <span style="margin-right: 8px;">&#127970;</span> From {$senderTimebank}
                                </p>
                                <p style="margin: 0 0 12px; font-size: 18px; font-weight: 700; color: {$textColor};">
                                    {$senderName}
                                </p>
                                <p style="margin: 0; font-size: 14px; color: {$mutedColor};">
                                    <strong>Note:</strong> {$desc}
                                </p>
                            </div>

                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="text-align: center;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">
                                            <tr>
                                                <td style="border-radius: 12px; background: linear-gradient(135deg, {$brandColor} 0%, {$brandColorDark} 100%); box-shadow: 0 4px 14px rgba(139, 92, 246, 0.35);" class="button-primary">
                                                    <a href="{$transactionsUrl}" style="display: inline-block; padding: 16px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 12px;">View Transaction History</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 24px 0 0; font-size: 14px; color: {$mutedColor}; text-align: center;">
                                This transaction was processed through the federation network.
                            </p>
                        </td>
                    </tr>
HTML;

        return self::getEmailWrapper($content, $siteName, $siteUrl, $basePath, "You received {$amount} hours from {$senderName}");
    }

    /**
     * Generate HTML for transaction confirmation email (sent to sender)
     */
    private static function generateTransactionConfirmationHtml(
        array $sender,
        array $recipient,
        float $amount,
        string $description,
        float $newBalance,
        string $basePath,
        string $siteName,
        string $siteUrl
    ): string {
        $firstName = htmlspecialchars($sender['first_name']);
        $recipientName = htmlspecialchars(trim($recipient['first_name'] . ' ' . $recipient['last_name']));
        $recipientTimebank = htmlspecialchars($recipient['tenant_name']);
        $desc = htmlspecialchars(substr($description, 0, 200));
        $formattedBalance = number_format($newBalance, 2);

        $brandColor = self::BRAND_COLOR;
        $brandColorDark = self::BRAND_COLOR_DARK;
        $successColor = self::SUCCESS_COLOR;
        $textColor = self::TEXT_COLOR;
        $mutedColor = self::MUTED_COLOR;
        $transactionsUrl = $siteUrl . $basePath . '/federation/transactions';

        $content = <<<HTML
                    <tr>
                        <td style="padding: 40px; text-align: center; background: linear-gradient(135deg, {$brandColor} 0%, #7c3aed 100%); border-radius: 16px 16px 0 0;">
                            <div style="font-size: 56px; margin-bottom: 16px;">&#9989;</div>
                            <h1 style="margin: 0 0 10px; font-size: 28px; font-weight: 700; color: #ffffff;">Transfer Complete!</h1>
                            <p style="margin: 0; font-size: 16px; color: rgba(255,255,255,0.9);">Your hours have been sent successfully</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color: #ffffff; padding: 40px;" class="mobile-padding">
                            <div style="text-align: center; margin-bottom: 30px;">
                                <div style="font-size: 64px; font-weight: 900; color: {$brandColor}; line-height: 1;">-{$amount}</div>
                                <p style="margin: 8px 0 0; font-size: 18px; color: {$textColor}; font-weight: 600;">hours sent</p>
                            </div>

                            <div style="background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%); border-radius: 16px; padding: 24px; margin-bottom: 24px;">
                                <p style="margin: 0 0 8px; font-size: 14px; font-weight: 600; color: {$brandColor};">
                                    <span style="margin-right: 8px;">&#127970;</span> To {$recipientTimebank}
                                </p>
                                <p style="margin: 0 0 12px; font-size: 18px; font-weight: 700; color: {$textColor};">
                                    {$recipientName}
                                </p>
                                <p style="margin: 0; font-size: 14px; color: {$mutedColor};">
                                    <strong>Note:</strong> {$desc}
                                </p>
                            </div>

                            <div style="background: #f9fafb; border-radius: 12px; padding: 16px; margin-bottom: 24px; text-align: center;">
                                <p style="margin: 0 0 4px; font-size: 12px; font-weight: 600; color: {$mutedColor}; text-transform: uppercase; letter-spacing: 0.5px;">Your New Balance</p>
                                <p style="margin: 0; font-size: 28px; font-weight: 700; color: {$textColor};">{$formattedBalance} <span style="font-size: 16px; color: {$mutedColor};">hours</span></p>
                            </div>

                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="text-align: center;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">
                                            <tr>
                                                <td style="border-radius: 12px; background: linear-gradient(135deg, {$brandColor} 0%, {$brandColorDark} 100%); box-shadow: 0 4px 14px rgba(139, 92, 246, 0.35);" class="button-primary">
                                                    <a href="{$transactionsUrl}" style="display: inline-block; padding: 16px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 12px;">View Transaction History</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 24px 0 0; font-size: 14px; color: {$mutedColor}; text-align: center;">
                                This federated transfer was processed instantly.
                            </p>
                        </td>
                    </tr>
HTML;

        return self::getEmailWrapper($content, $siteName, $siteUrl, $basePath, "You sent {$amount} hours to {$recipientName}");
    }

    // =========================================================================
    // PARTNERSHIP NOTIFICATIONS
    // =========================================================================

    /**
     * Send notification when a partnership request is received (to target tenant admins)
     */
    public static function sendPartnershipRequestNotification(
        int $targetTenantId,
        int $requestingTenantId,
        string $requestingTenantName,
        int $requestedLevel,
        ?string $notes = null
    ): bool {
        return self::sendPartnershipNotificationToAdmins(
            $targetTenantId,
            'partnership_request',
            [
                'requesting_tenant_id' => $requestingTenantId,
                'requesting_tenant_name' => $requestingTenantName,
                'requested_level' => $requestedLevel,
                'level_name' => self::getLevelName($requestedLevel),
                'notes' => $notes
            ]
        );
    }

    /**
     * Send notification when a partnership is approved (to requesting tenant admins)
     */
    public static function sendPartnershipApprovedNotification(
        int $requestingTenantId,
        int $approverTenantId,
        string $approverTenantName,
        int $approvedLevel
    ): bool {
        return self::sendPartnershipNotificationToAdmins(
            $requestingTenantId,
            'partnership_approved',
            [
                'partner_tenant_id' => $approverTenantId,
                'partner_tenant_name' => $approverTenantName,
                'approved_level' => $approvedLevel,
                'level_name' => self::getLevelName($approvedLevel)
            ]
        );
    }

    /**
     * Send notification when a partnership is rejected (to requesting tenant admins)
     */
    public static function sendPartnershipRejectedNotification(
        int $requestingTenantId,
        int $rejecterTenantId,
        string $rejecterTenantName,
        ?string $reason = null
    ): bool {
        return self::sendPartnershipNotificationToAdmins(
            $requestingTenantId,
            'partnership_rejected',
            [
                'partner_tenant_id' => $rejecterTenantId,
                'partner_tenant_name' => $rejecterTenantName,
                'reason' => $reason
            ]
        );
    }

    /**
     * Send notification when a counter-proposal is made (to original requester tenant admins)
     */
    public static function sendPartnershipCounterProposalNotification(
        int $originalRequesterTenantId,
        int $counterProposerTenantId,
        string $counterProposerTenantName,
        int $originalLevel,
        int $proposedLevel,
        ?string $message = null
    ): bool {
        return self::sendPartnershipNotificationToAdmins(
            $originalRequesterTenantId,
            'partnership_counter_proposal',
            [
                'partner_tenant_id' => $counterProposerTenantId,
                'partner_tenant_name' => $counterProposerTenantName,
                'original_level' => $originalLevel,
                'original_level_name' => self::getLevelName($originalLevel),
                'proposed_level' => $proposedLevel,
                'proposed_level_name' => self::getLevelName($proposedLevel),
                'message' => $message
            ]
        );
    }

    /**
     * Send notification when a partnership is suspended (to partner tenant admins)
     */
    public static function sendPartnershipSuspendedNotification(
        int $partnerTenantId,
        int $suspenderTenantId,
        string $suspenderTenantName,
        ?string $reason = null
    ): bool {
        return self::sendPartnershipNotificationToAdmins(
            $partnerTenantId,
            'partnership_suspended',
            [
                'partner_tenant_id' => $suspenderTenantId,
                'partner_tenant_name' => $suspenderTenantName,
                'reason' => $reason
            ]
        );
    }

    /**
     * Send notification when a partnership is reactivated (to partner tenant admins)
     */
    public static function sendPartnershipReactivatedNotification(
        int $partnerTenantId,
        int $reactivatorTenantId,
        string $reactivatorTenantName
    ): bool {
        return self::sendPartnershipNotificationToAdmins(
            $partnerTenantId,
            'partnership_reactivated',
            [
                'partner_tenant_id' => $reactivatorTenantId,
                'partner_tenant_name' => $reactivatorTenantName
            ]
        );
    }

    /**
     * Send notification when a partnership is terminated (to partner tenant admins)
     */
    public static function sendPartnershipTerminatedNotification(
        int $partnerTenantId,
        int $terminatorTenantId,
        string $terminatorTenantName,
        ?string $reason = null
    ): bool {
        return self::sendPartnershipNotificationToAdmins(
            $partnerTenantId,
            'partnership_terminated',
            [
                'partner_tenant_id' => $terminatorTenantId,
                'partner_tenant_name' => $terminatorTenantName,
                'reason' => $reason
            ]
        );
    }

    /**
     * Send partnership notification to all admins of a tenant
     */
    private static function sendPartnershipNotificationToAdmins(
        int $tenantId,
        string $notificationType,
        array $data
    ): bool {
        // Get tenant info
        $tenant = Database::query(
            "SELECT id, name, domain FROM tenants WHERE id = ?",
            [$tenantId]
        )->fetch();

        if (!$tenant) {
            error_log("FederationEmailService: Tenant {$tenantId} not found for partnership notification");
            return false;
        }

        // Get all admins for this tenant
        $admins = Database::query(
            "SELECT u.id, u.email, u.first_name, u.last_name
             FROM users u
             WHERE u.tenant_id = ?
             AND u.role = 'admin'
             AND u.status = 'active'
             AND u.email IS NOT NULL",
            [$tenantId]
        )->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($admins)) {
            error_log("FederationEmailService: No admins found for tenant {$tenantId}");
            return false;
        }

        $siteName = $tenant['name'];
        $basePath = '/' . $tenant['domain'];
        $siteUrl = $_ENV['APP_URL'] ?? 'http://localhost';

        // Generate email content based on type
        $emailContent = self::generatePartnershipEmailContent($notificationType, $data, $basePath, $siteName, $siteUrl);

        if (!$emailContent) {
            return false;
        }

        $success = true;
        $mailer = new Mailer();

        foreach ($admins as $admin) {
            try {
                $html = self::getEmailWrapper(
                    $emailContent['html'],
                    $siteName,
                    $siteUrl,
                    $basePath,
                    $emailContent['preview']
                );

                if (!$mailer->send($admin['email'], $emailContent['subject'], $html)) {
                    $success = false;
                    error_log("Failed to send partnership notification to {$admin['email']}");
                }
            } catch (\Throwable $e) {
                $success = false;
                error_log("Exception sending partnership notification to {$admin['email']}: " . $e->getMessage());
            }
        }

        return $success;
    }

    /**
     * Generate email content for different partnership notification types
     */
    private static function generatePartnershipEmailContent(
        string $type,
        array $data,
        string $basePath,
        string $siteName,
        string $siteUrl
    ): ?array {
        $brandColor = self::BRAND_COLOR;
        $brandColorDark = self::BRAND_COLOR_DARK;
        $textColor = self::TEXT_COLOR;
        $mutedColor = self::MUTED_COLOR;
        $warningColor = '#f59e0b';
        $dangerColor = '#ef4444';
        $successColor = self::SUCCESS_COLOR;

        $partnershipUrl = $siteUrl . $basePath . '/admin-legacy/federation/partnerships';

        switch ($type) {
            case 'partnership_request':
                $partnerName = htmlspecialchars($data['requesting_tenant_name']);
                $levelName = htmlspecialchars($data['level_name']);
                $notes = !empty($data['notes']) ? htmlspecialchars($data['notes']) : 'No additional notes provided.';

                return [
                    'subject' => "New Federation Partnership Request from {$partnerName}",
                    'preview' => "{$partnerName} wants to partner with your timebank",
                    'html' => <<<HTML
                    <tr>
                        <td style="padding: 40px; text-align: center; background: linear-gradient(135deg, {$brandColor} 0%, #7c3aed 100%); border-radius: 16px 16px 0 0;">
                            <div style="font-size: 56px; margin-bottom: 16px;">&#129309;</div>
                            <h1 style="margin: 0 0 10px; font-size: 28px; font-weight: 700; color: #ffffff;">Partnership Request</h1>
                            <p style="margin: 0; font-size: 16px; color: rgba(255,255,255,0.9);">A timebank wants to connect with you!</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #ffffff; padding: 40px;" class="mobile-padding">
                            <div style="background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%); border-radius: 16px; padding: 24px; margin-bottom: 24px; border-left: 4px solid {$brandColor};">
                                <p style="margin: 0 0 8px; font-size: 14px; font-weight: 600; color: {$brandColor};">
                                    <span style="margin-right: 8px;">&#127970;</span> Requesting Timebank
                                </p>
                                <p style="margin: 0 0 16px; font-size: 22px; font-weight: 700; color: {$textColor};">{$partnerName}</p>
                                <p style="margin: 0 0 8px; font-size: 14px; color: {$mutedColor};">
                                    <strong>Requested Level:</strong> {$levelName}
                                </p>
                                <p style="margin: 0; font-size: 14px; color: {$mutedColor};">
                                    <strong>Notes:</strong> {$notes}
                                </p>
                            </div>
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="text-align: center;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">
                                            <tr>
                                                <td style="border-radius: 12px; background: linear-gradient(135deg, {$brandColor} 0%, {$brandColorDark} 100%); box-shadow: 0 4px 14px rgba(139, 92, 246, 0.35);" class="button-primary">
                                                    <a href="{$partnershipUrl}" style="display: inline-block; padding: 16px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 12px;">Review Request</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin: 24px 0 0; font-size: 14px; color: {$mutedColor}; text-align: center;">
                                You can approve, reject, or counter-propose different terms.
                            </p>
                        </td>
                    </tr>
HTML
                ];

            case 'partnership_approved':
                $partnerName = htmlspecialchars($data['partner_tenant_name']);
                $levelName = htmlspecialchars($data['level_name']);

                return [
                    'subject' => "Partnership Approved - {$partnerName}",
                    'preview' => "Your partnership with {$partnerName} has been approved!",
                    'html' => <<<HTML
                    <tr>
                        <td style="padding: 40px; text-align: center; background: linear-gradient(135deg, {$successColor} 0%, #059669 100%); border-radius: 16px 16px 0 0;">
                            <div style="font-size: 56px; margin-bottom: 16px;">&#127881;</div>
                            <h1 style="margin: 0 0 10px; font-size: 28px; font-weight: 700; color: #ffffff;">Partnership Approved!</h1>
                            <p style="margin: 0; font-size: 16px; color: rgba(255,255,255,0.9);">Great news - you're now connected!</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #ffffff; padding: 40px;" class="mobile-padding">
                            <div style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-radius: 16px; padding: 24px; margin-bottom: 24px; border-left: 4px solid {$successColor};">
                                <p style="margin: 0 0 8px; font-size: 14px; font-weight: 600; color: {$successColor};">
                                    <span style="margin-right: 8px;">&#9989;</span> New Partner
                                </p>
                                <p style="margin: 0 0 16px; font-size: 22px; font-weight: 700; color: {$textColor};">{$partnerName}</p>
                                <p style="margin: 0; font-size: 14px; color: {$mutedColor};">
                                    <strong>Partnership Level:</strong> {$levelName}
                                </p>
                            </div>
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="text-align: center;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">
                                            <tr>
                                                <td style="border-radius: 12px; background: linear-gradient(135deg, {$brandColor} 0%, {$brandColorDark} 100%); box-shadow: 0 4px 14px rgba(139, 92, 246, 0.35);" class="button-primary">
                                                    <a href="{$partnershipUrl}" style="display: inline-block; padding: 16px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 12px;">View Partnerships</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin: 24px 0 0; font-size: 14px; color: {$mutedColor}; text-align: center;">
                                Your members can now interact with {$partnerName} based on the partnership level.
                            </p>
                        </td>
                    </tr>
HTML
                ];

            case 'partnership_rejected':
                $partnerName = htmlspecialchars($data['partner_tenant_name']);
                $reason = !empty($data['reason']) ? htmlspecialchars($data['reason']) : 'No reason provided.';

                return [
                    'subject' => "Partnership Request Declined - {$partnerName}",
                    'preview' => "Your partnership request to {$partnerName} was declined",
                    'html' => <<<HTML
                    <tr>
                        <td style="padding: 40px; text-align: center; background: linear-gradient(135deg, {$mutedColor} 0%, #4b5563 100%); border-radius: 16px 16px 0 0;">
                            <div style="font-size: 56px; margin-bottom: 16px;">&#128532;</div>
                            <h1 style="margin: 0 0 10px; font-size: 28px; font-weight: 700; color: #ffffff;">Request Declined</h1>
                            <p style="margin: 0; font-size: 16px; color: rgba(255,255,255,0.9);">Your partnership request was not accepted</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #ffffff; padding: 40px;" class="mobile-padding">
                            <div style="background: #f9fafb; border-radius: 16px; padding: 24px; margin-bottom: 24px; border-left: 4px solid {$mutedColor};">
                                <p style="margin: 0 0 8px; font-size: 14px; font-weight: 600; color: {$mutedColor};">Timebank</p>
                                <p style="margin: 0 0 16px; font-size: 22px; font-weight: 700; color: {$textColor};">{$partnerName}</p>
                                <p style="margin: 0; font-size: 14px; color: {$mutedColor};">
                                    <strong>Reason:</strong> {$reason}
                                </p>
                            </div>
                            <p style="margin: 0; font-size: 14px; color: {$mutedColor}; text-align: center;">
                                You can try reaching out to this timebank directly or explore other partnership opportunities.
                            </p>
                        </td>
                    </tr>
HTML
                ];

            case 'partnership_counter_proposal':
                $partnerName = htmlspecialchars($data['partner_tenant_name']);
                $originalLevel = htmlspecialchars($data['original_level_name']);
                $proposedLevel = htmlspecialchars($data['proposed_level_name']);
                $message = !empty($data['message']) ? htmlspecialchars($data['message']) : 'No additional message.';

                return [
                    'subject' => "Counter-Proposal Received from {$partnerName}",
                    'preview' => "{$partnerName} has proposed different partnership terms",
                    'html' => <<<HTML
                    <tr>
                        <td style="padding: 40px; text-align: center; background: linear-gradient(135deg, {$warningColor} 0%, #d97706 100%); border-radius: 16px 16px 0 0;">
                            <div style="font-size: 56px; margin-bottom: 16px;">&#128172;</div>
                            <h1 style="margin: 0 0 10px; font-size: 28px; font-weight: 700; color: #ffffff;">Counter-Proposal</h1>
                            <p style="margin: 0; font-size: 16px; color: rgba(255,255,255,0.9);">Different terms have been proposed</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #ffffff; padding: 40px;" class="mobile-padding">
                            <div style="background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); border-radius: 16px; padding: 24px; margin-bottom: 24px; border-left: 4px solid {$warningColor};">
                                <p style="margin: 0 0 8px; font-size: 14px; font-weight: 600; color: {$warningColor};">
                                    <span style="margin-right: 8px;">&#127970;</span> From {$partnerName}
                                </p>
                                <p style="margin: 16px 0 8px; font-size: 14px; color: {$mutedColor};">
                                    <strong>Your Request:</strong> {$originalLevel} <br>
                                    <strong>Their Proposal:</strong> <span style="color: {$warningColor}; font-weight: 600;">{$proposedLevel}</span>
                                </p>
                                <p style="margin: 16px 0 0; font-size: 14px; color: {$mutedColor};">
                                    <strong>Message:</strong> {$message}
                                </p>
                            </div>
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="text-align: center;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">
                                            <tr>
                                                <td style="border-radius: 12px; background: linear-gradient(135deg, {$brandColor} 0%, {$brandColorDark} 100%); box-shadow: 0 4px 14px rgba(139, 92, 246, 0.35);" class="button-primary">
                                                    <a href="{$partnershipUrl}" style="display: inline-block; padding: 16px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 12px;">Review Proposal</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin: 24px 0 0; font-size: 14px; color: {$mutedColor}; text-align: center;">
                                You can accept the counter-proposal or continue negotiating.
                            </p>
                        </td>
                    </tr>
HTML
                ];

            case 'partnership_suspended':
                $partnerName = htmlspecialchars($data['partner_tenant_name']);
                $reason = !empty($data['reason']) ? htmlspecialchars($data['reason']) : 'No reason provided.';

                return [
                    'subject' => "Partnership Suspended - {$partnerName}",
                    'preview' => "Your partnership with {$partnerName} has been suspended",
                    'html' => <<<HTML
                    <tr>
                        <td style="padding: 40px; text-align: center; background: linear-gradient(135deg, {$warningColor} 0%, #d97706 100%); border-radius: 16px 16px 0 0;">
                            <div style="font-size: 56px; margin-bottom: 16px;">&#9888;&#65039;</div>
                            <h1 style="margin: 0 0 10px; font-size: 28px; font-weight: 700; color: #ffffff;">Partnership Suspended</h1>
                            <p style="margin: 0; font-size: 16px; color: rgba(255,255,255,0.9);">Temporary pause on federation activities</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #ffffff; padding: 40px;" class="mobile-padding">
                            <div style="background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); border-radius: 16px; padding: 24px; margin-bottom: 24px; border-left: 4px solid {$warningColor};">
                                <p style="margin: 0 0 8px; font-size: 14px; font-weight: 600; color: {$warningColor};">Suspended Partner</p>
                                <p style="margin: 0 0 16px; font-size: 22px; font-weight: 700; color: {$textColor};">{$partnerName}</p>
                                <p style="margin: 0; font-size: 14px; color: {$mutedColor};">
                                    <strong>Reason:</strong> {$reason}
                                </p>
                            </div>
                            <p style="margin: 0; font-size: 14px; color: {$mutedColor}; text-align: center; line-height: 1.6;">
                                Federation activities with this partner are temporarily paused.<br>
                                The partnership can be reactivated when both parties are ready.
                            </p>
                        </td>
                    </tr>
HTML
                ];

            case 'partnership_reactivated':
                $partnerName = htmlspecialchars($data['partner_tenant_name']);

                return [
                    'subject' => "Partnership Reactivated - {$partnerName}",
                    'preview' => "Your partnership with {$partnerName} is active again!",
                    'html' => <<<HTML
                    <tr>
                        <td style="padding: 40px; text-align: center; background: linear-gradient(135deg, {$successColor} 0%, #059669 100%); border-radius: 16px 16px 0 0;">
                            <div style="font-size: 56px; margin-bottom: 16px;">&#128994;</div>
                            <h1 style="margin: 0 0 10px; font-size: 28px; font-weight: 700; color: #ffffff;">Partnership Reactivated!</h1>
                            <p style="margin: 0; font-size: 16px; color: rgba(255,255,255,0.9);">You're connected again</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #ffffff; padding: 40px;" class="mobile-padding">
                            <div style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-radius: 16px; padding: 24px; margin-bottom: 24px; border-left: 4px solid {$successColor};">
                                <p style="margin: 0 0 8px; font-size: 14px; font-weight: 600; color: {$successColor};">
                                    <span style="margin-right: 8px;">&#9989;</span> Active Partner
                                </p>
                                <p style="margin: 0; font-size: 22px; font-weight: 700; color: {$textColor};">{$partnerName}</p>
                            </div>
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="text-align: center;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">
                                            <tr>
                                                <td style="border-radius: 12px; background: linear-gradient(135deg, {$brandColor} 0%, {$brandColorDark} 100%); box-shadow: 0 4px 14px rgba(139, 92, 246, 0.35);" class="button-primary">
                                                    <a href="{$partnershipUrl}" style="display: inline-block; padding: 16px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 12px;">View Partnerships</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin: 24px 0 0; font-size: 14px; color: {$mutedColor}; text-align: center;">
                                Your members can resume federation activities with {$partnerName}.
                            </p>
                        </td>
                    </tr>
HTML
                ];

            case 'partnership_terminated':
                $partnerName = htmlspecialchars($data['partner_tenant_name']);
                $reason = !empty($data['reason']) ? htmlspecialchars($data['reason']) : 'No reason provided.';

                return [
                    'subject' => "Partnership Terminated - {$partnerName}",
                    'preview' => "Your partnership with {$partnerName} has been terminated",
                    'html' => <<<HTML
                    <tr>
                        <td style="padding: 40px; text-align: center; background: linear-gradient(135deg, {$dangerColor} 0%, #dc2626 100%); border-radius: 16px 16px 0 0;">
                            <div style="font-size: 56px; margin-bottom: 16px;">&#128683;</div>
                            <h1 style="margin: 0 0 10px; font-size: 28px; font-weight: 700; color: #ffffff;">Partnership Terminated</h1>
                            <p style="margin: 0; font-size: 16px; color: rgba(255,255,255,0.9);">Federation connection has ended</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #ffffff; padding: 40px;" class="mobile-padding">
                            <div style="background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); border-radius: 16px; padding: 24px; margin-bottom: 24px; border-left: 4px solid {$dangerColor};">
                                <p style="margin: 0 0 8px; font-size: 14px; font-weight: 600; color: {$dangerColor};">Former Partner</p>
                                <p style="margin: 0 0 16px; font-size: 22px; font-weight: 700; color: {$textColor};">{$partnerName}</p>
                                <p style="margin: 0; font-size: 14px; color: {$mutedColor};">
                                    <strong>Reason:</strong> {$reason}
                                </p>
                            </div>
                            <p style="margin: 0; font-size: 14px; color: {$mutedColor}; text-align: center; line-height: 1.6;">
                                All federation activities with this partner have ended.<br>
                                You can request a new partnership in the future if desired.
                            </p>
                        </td>
                    </tr>
HTML
                ];

            default:
                error_log("FederationEmailService: Unknown partnership notification type: {$type}");
                return null;
        }
    }

    /**
     * Get human-readable level name for emails
     */
    private static function getLevelName(int $level): string
    {
        $names = [
            1 => 'Discovery (Basic visibility)',
            2 => 'Social (Messaging & profiles)',
            3 => 'Economic (Full trading)',
            4 => 'Integrated (All features)',
        ];

        return $names[$level] ?? 'Unknown';
    }

    /**
     * Generate weekly digest HTML
     */
    private static function generateDigestHtml(
        array $user,
        array $stats,
        string $basePath,
        string $siteName,
        string $siteUrl
    ): string {
        $firstName = htmlspecialchars($user['first_name']);
        $messageCount = $stats['message_count'];
        $transactionCount = $stats['transaction_count'];
        $hoursReceived = $stats['hours_received'];

        $brandColor = self::BRAND_COLOR;
        $brandColorDark = self::BRAND_COLOR_DARK;
        $successColor = self::SUCCESS_COLOR;
        $textColor = self::TEXT_COLOR;
        $mutedColor = self::MUTED_COLOR;
        $federationUrl = $siteUrl . $basePath . '/federation';

        $content = <<<HTML
                    <tr>
                        <td style="padding: 40px; text-align: center; background: linear-gradient(135deg, {$brandColor} 0%, #7c3aed 100%); border-radius: 16px 16px 0 0;">
                            <div style="font-size: 56px; margin-bottom: 16px;">&#127760;</div>
                            <h1 style="margin: 0 0 10px; font-size: 28px; font-weight: 700; color: #ffffff;">Your Federation Activity</h1>
                            <p style="margin: 0; font-size: 16px; color: rgba(255,255,255,0.9);">Weekly digest for {$firstName}</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color: #ffffff; padding: 40px;" class="mobile-padding">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td width="33%" style="padding: 10px; text-align: center;">
                                        <div style="background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%); border-radius: 16px; padding: 24px;">
                                            <div style="font-size: 36px; font-weight: 900; color: {$brandColor}; line-height: 1;">{$messageCount}</div>
                                            <p style="margin: 8px 0 0; font-size: 14px; color: {$mutedColor};">Messages</p>
                                        </div>
                                    </td>
                                    <td width="33%" style="padding: 10px; text-align: center;">
                                        <div style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-radius: 16px; padding: 24px;">
                                            <div style="font-size: 36px; font-weight: 900; color: {$successColor}; line-height: 1;">{$transactionCount}</div>
                                            <p style="margin: 8px 0 0; font-size: 14px; color: {$mutedColor};">Transactions</p>
                                        </div>
                                    </td>
                                    <td width="33%" style="padding: 10px; text-align: center;">
                                        <div style="background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%); border-radius: 16px; padding: 24px;">
                                            <div style="font-size: 36px; font-weight: 900; color: #f59e0b; line-height: 1;">+{$hoursReceived}</div>
                                            <p style="margin: 8px 0 0; font-size: 14px; color: {$mutedColor};">Hours Received</p>
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-top: 30px;">
                                <tr>
                                    <td style="text-align: center;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">
                                            <tr>
                                                <td style="border-radius: 12px; background: linear-gradient(135deg, {$brandColor} 0%, {$brandColorDark} 100%); box-shadow: 0 4px 14px rgba(139, 92, 246, 0.35);" class="button-primary">
                                                    <a href="{$federationUrl}" style="display: inline-block; padding: 16px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 12px;">View Federation Dashboard</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 24px 0 0; font-size: 14px; color: {$mutedColor}; text-align: center; line-height: 1.6;">
                                You're connecting with partner timebanks through the federation network.<br>
                                Keep exchanging services and building community!
                            </p>
                        </td>
                    </tr>
HTML;

        return self::getEmailWrapper($content, $siteName, $siteUrl, $basePath, "Your federation activity this week");
    }
}

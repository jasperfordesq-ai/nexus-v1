<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\Mailer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * EmailService — Laravel DI-based service for email operations.
 *
 * Manages email sending, template configuration, and delivery settings.
 * Uses the custom Mailer class which supports SendGrid, Gmail API, and SMTP
 * with per-tenant configuration.
 */
class EmailService
{
    /**
     * Send an email using the tenant-aware Mailer (SendGrid/Gmail API/SMTP).
     */
    public function send(string $to, string $subject, string $body, array $options = []): bool
    {
        try {
            $mailer = Mailer::forCurrentTenant();
            return $mailer->send(
                $to,
                $subject,
                $body,
                $options['cc']             ?? null,
                $options['replyTo']        ?? null,
                $options['unsubscribeUrl'] ?? null,
            );
        } catch (\Throwable $e) {
            Log::error('EmailService::send failed', ['to' => self::maskEmail($to), 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Mask an email address for safe logging (e.g., "j***@example.com").
     */
    private static function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return '***';
        }
        $local = $parts[0];
        $masked = strlen($local) > 1 ? $local[0] . str_repeat('*', min(strlen($local) - 1, 5)) : '*';
        return $masked . '@' . $parts[1];
    }

    /**
     * Get email settings for a tenant.
     */
    public function getSettings(int $tenantId): array
    {
        $settings = DB::table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->whereIn('setting_key', ['email_from', 'email_reply_to', 'email_driver', 'email_footer'])
            ->pluck('setting_value', 'setting_key')
            ->all();

        return [
            'from'     => $settings['email_from'] ?? config('mail.from.address'),
            'reply_to' => $settings['email_reply_to'] ?? null,
            'driver'   => $settings['email_driver'] ?? config('mail.default'),
            'footer'   => $settings['email_footer'] ?? '',
        ];
    }

    /**
     * Update email settings for a tenant.
     */
    public function updateSettings(int $tenantId, array $data): bool
    {
        $allowedKeys = ['email_from', 'email_reply_to', 'email_driver', 'email_footer'];

        foreach ($data as $key => $value) {
            if (! in_array($key, $allowedKeys, true)) {
                continue;
            }
            DB::table('tenant_settings')->updateOrInsert(
                ['tenant_id' => $tenantId, 'setting_key' => $key],
                ['setting_value' => $value, 'updated_at' => now()]
            );
        }

        return true;
    }
}

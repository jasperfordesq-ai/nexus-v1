<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

/**
 * EmailService — Laravel DI-based service for email operations.
 *
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\EmailTemplateService.
 * Manages email sending, template configuration, and delivery settings.
 */
class EmailService
{
    /**
     * Send an email using the configured mail driver.
     */
    public function send(string $to, string $subject, string $body, array $options = []): bool
    {
        try {
            Mail::raw($body, function ($message) use ($to, $subject, $options) {
                $message->to($to)->subject($subject);
                if (! empty($options['from'])) {
                    $message->from($options['from']);
                }
                if (! empty($options['reply_to'])) {
                    $message->replyTo($options['reply_to']);
                }
            });
            return true;
        } catch (\Throwable $e) {
            Log::error('EmailService::send failed', ['to' => $to, 'error' => $e->getMessage()]);
            return false;
        }
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

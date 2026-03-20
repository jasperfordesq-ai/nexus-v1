<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use Illuminate\Support\Facades\DB;

/**
 * Email Settings service model.
 *
 * Manages per-tenant email provider configuration stored in the email_settings
 * table. Supports encryption for sensitive values like API keys.
 *
 */
class EmailSettings
{
    private static ?string $encryptionKey = null;

    /**
     * Keys that should be encrypted in the database.
     */
    private static array $sensitiveKeys = [
        'sendgrid_api_key',
        'sendgrid_webhook_signing_key',
        'smtp_password',
        'gmail_client_secret',
        'gmail_refresh_token',
    ];

    /**
     * Get a setting value.
     */
    public static function get(int $tenantId, string $key): ?string
    {
        $row = DB::table('email_settings')
            ->where('tenant_id', $tenantId)
            ->where('setting_key', $key)
            ->select(['setting_value', 'is_encrypted'])
            ->first();

        if (!$row) {
            return null;
        }

        $value = $row->setting_value;

        if ($row->is_encrypted && $value) {
            $value = self::decrypt($value);
        }

        return $value;
    }

    /**
     * Set a setting value.
     */
    public static function set(int $tenantId, string $key, ?string $value): bool
    {
        $isEncrypted = in_array($key, self::$sensitiveKeys);

        $storedValue = $value;
        if ($isEncrypted && $value) {
            $storedValue = self::encrypt($value);
        }

        DB::statement(
            "INSERT INTO email_settings (tenant_id, setting_key, setting_value, is_encrypted, created_at, updated_at)
             VALUES (?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                 setting_value = VALUES(setting_value),
                 is_encrypted = VALUES(is_encrypted),
                 updated_at = NOW()",
            [$tenantId, $key, $storedValue, $isEncrypted ? 1 : 0]
        );

        return true;
    }

    /**
     * Delete a setting.
     */
    public static function delete(int $tenantId, string $key): bool
    {
        return DB::table('email_settings')
            ->where('tenant_id', $tenantId)
            ->where('setting_key', $key)
            ->delete() > 0;
    }

    /**
     * Get all settings for a tenant.
     */
    public static function getAllForTenant(int $tenantId): array
    {
        $rows = DB::table('email_settings')
            ->where('tenant_id', $tenantId)
            ->select(['setting_key', 'setting_value', 'is_encrypted'])
            ->get();

        $settings = [];
        foreach ($rows as $row) {
            $value = $row->setting_value;
            if ($row->is_encrypted && $value) {
                $value = self::decrypt($value);
            }
            $settings[$row->setting_key] = $value;
        }

        return $settings;
    }

    /**
     * Set multiple settings at once.
     */
    public static function setMultiple(int $tenantId, array $settings): bool
    {
        DB::beginTransaction();

        try {
            foreach ($settings as $key => $value) {
                self::set($tenantId, $key, $value);
            }
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get masked value for display (shows only last 4 chars of API keys).
     */
    public static function getMasked(int $tenantId, string $key): ?string
    {
        $value = self::get($tenantId, $key);

        if (!$value || !in_array($key, self::$sensitiveKeys)) {
            return $value;
        }

        if (strlen($value) <= 8) {
            return str_repeat('*', strlen($value));
        }

        return str_repeat('*', strlen($value) - 4) . substr($value, -4);
    }

    /**
     * Check if a setting has a value set.
     */
    public static function has(int $tenantId, string $key): bool
    {
        $value = self::get($tenantId, $key);
        return $value !== null && $value !== '';
    }

    /**
     * Encrypt a value.
     */
    private static function encrypt(string $value): string
    {
        $key = self::getEncryptionKey();
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt a value.
     */
    private static function decrypt(string $value): ?string
    {
        $key = self::getEncryptionKey();
        $data = base64_decode($value);

        if ($data === false || strlen($data) < 17) {
            return $value;
        }

        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);

        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        if ($decrypted === false) {
            error_log("EmailSettings: decryption failed (possible key mismatch)");
            return null;
        }

        return $decrypted;
    }

    /**
     * Get encryption key from environment or generate a default.
     */
    private static function getEncryptionKey(): string
    {
        if (self::$encryptionKey === null) {
            $key = env('EMAIL_ENCRYPTION_KEY');

            if (!$key) {
                $key = env('APP_KEY');

                if (!$key) {
                    $dbPass = env('DB_PASS', '');
                    $dbName = env('DB_NAME', 'nexus');
                    $key = hash('sha256', $dbPass . $dbName . 'email_settings_key');
                }
            }

            self::$encryptionKey = hash('sha256', $key, true);
        }

        return self::$encryptionKey;
    }
}

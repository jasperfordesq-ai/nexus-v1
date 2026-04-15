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
     * Encrypt a value using AES-256-GCM (authenticated encryption).
     *
     * Wire format: base64(iv[12] + tag[16] + ciphertext)
     * Replaces legacy AES-256-CBC (no HMAC) — decrypt() handles both formats.
     */
    private static function encrypt(string $value): string
    {
        $key = self::getEncryptionKey();
        $iv = random_bytes(12);
        $tag = '';
        $encrypted = openssl_encrypt($value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($encrypted === false) {
            throw new \RuntimeException('EmailSettings: encryption failed: ' . openssl_error_string());
        }

        return base64_encode($iv . $tag . $encrypted);
    }

    /**
     * Decrypt a value. Accepts both GCM (new) and CBC (legacy) wire formats.
     *
     * GCM format: base64(iv[12] + tag[16] + ciphertext) — total raw >= 29 bytes
     * CBC format: base64(iv[16] + ciphertext) — total raw >= 17 bytes, (len-16) % 16 == 0
     */
    private static function decrypt(string $value): ?string
    {
        $key = self::getEncryptionKey();
        $data = base64_decode($value);

        if ($data === false || strlen($data) < 17) {
            return $value;
        }

        // Detect format: CBC has iv[16] + ciphertext where ciphertext is a multiple of 16.
        // GCM has iv[12] + tag[16] + ciphertext. We distinguish by checking if
        // (length - 16) is a multiple of 16 (CBC) vs trying GCM first.
        $rawLen = strlen($data);

        // Try GCM first (new format): iv[12] + tag[16] + ciphertext
        if ($rawLen >= 29) {
            $gcmIv = substr($data, 0, 12);
            $gcmTag = substr($data, 12, 16);
            $gcmCiphertext = substr($data, 28);

            $decrypted = openssl_decrypt($gcmCiphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $gcmIv, $gcmTag);
            if ($decrypted !== false) {
                return $decrypted;
            }
        }

        // Fall back to legacy CBC format: iv[16] + ciphertext
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);

        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        if ($decrypted === false) {
            \Illuminate\Support\Facades\Log::warning("EmailSettings: decryption failed (possible key mismatch)");
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

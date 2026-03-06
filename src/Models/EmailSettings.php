<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Core\Env;

/**
 * Email Settings Model
 *
 * Manages per-tenant email provider configuration stored in the database.
 * Supports encryption for sensitive values like API keys.
 * Mirrors AiSettings pattern.
 */
class EmailSettings
{
    private static ?string $encryptionKey = null;

    /**
     * Keys that should be encrypted in the database
     */
    private static array $sensitiveKeys = [
        'sendgrid_api_key',
        'sendgrid_webhook_signing_key',
        'smtp_password',
        'gmail_client_secret',
        'gmail_refresh_token',
    ];

    /**
     * Get a setting value
     */
    public static function get(int $tenantId, string $key): ?string
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT setting_value, is_encrypted
            FROM email_settings
            WHERE tenant_id = ? AND setting_key = ?
        ");
        $stmt->execute([$tenantId, $key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $value = $row['setting_value'];

        if ($row['is_encrypted'] && $value) {
            $value = self::decrypt($value);
        }

        return $value;
    }

    /**
     * Set a setting value
     */
    public static function set(int $tenantId, string $key, ?string $value): bool
    {
        $db = Database::getConnection();
        $isEncrypted = in_array($key, self::$sensitiveKeys);

        $storedValue = $value;
        if ($isEncrypted && $value) {
            $storedValue = self::encrypt($value);
        }

        $stmt = $db->prepare("
            INSERT INTO email_settings (tenant_id, setting_key, setting_value, is_encrypted, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                is_encrypted = VALUES(is_encrypted),
                updated_at = NOW()
        ");

        return $stmt->execute([$tenantId, $key, $storedValue, $isEncrypted ? 1 : 0]);
    }

    /**
     * Delete a setting
     */
    public static function delete(int $tenantId, string $key): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM email_settings WHERE tenant_id = ? AND setting_key = ?");
        return $stmt->execute([$tenantId, $key]);
    }

    /**
     * Get all settings for a tenant
     */
    public static function getAllForTenant(int $tenantId): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT setting_key, setting_value, is_encrypted FROM email_settings WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $settings = [];
        foreach ($rows as $row) {
            $value = $row['setting_value'];
            if ($row['is_encrypted'] && $value) {
                $value = self::decrypt($value);
            }
            $settings[$row['setting_key']] = $value;
        }

        return $settings;
    }

    /**
     * Set multiple settings at once
     */
    public static function setMultiple(int $tenantId, array $settings): bool
    {
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            foreach ($settings as $key => $value) {
                self::set($tenantId, $key, $value);
            }
            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Get masked value for display (shows only last 4 chars of API keys)
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
     * Check if a setting has a value set
     */
    public static function has(int $tenantId, string $key): bool
    {
        $value = self::get($tenantId, $key);
        return $value !== null && $value !== '';
    }

    /**
     * Encrypt a value
     */
    private static function encrypt(string $value): string
    {
        $key = self::getEncryptionKey();
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt a value
     */
    private static function decrypt(string $value): string
    {
        $key = self::getEncryptionKey();
        $data = base64_decode($value);

        if ($data === false || strlen($data) < 17) {
            return $value;
        }

        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);

        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        return $decrypted !== false ? $decrypted : $value;
    }

    /**
     * Get encryption key from environment or generate a default
     */
    private static function getEncryptionKey(): string
    {
        if (self::$encryptionKey === null) {
            $key = Env::get('EMAIL_ENCRYPTION_KEY');

            if (!$key) {
                $key = Env::get('APP_KEY');

                if (!$key) {
                    $dbPass = Env::get('DB_PASS', '');
                    $dbName = Env::get('DB_NAME', 'nexus');
                    $key = hash('sha256', $dbPass . $dbName . 'email_settings_key');
                }
            }

            self::$encryptionKey = hash('sha256', $key, true);
        }

        return self::$encryptionKey;
    }
}

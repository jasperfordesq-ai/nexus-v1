<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Core;

/**
 * TOTP secret encryption/decryption using AES-256-CBC via OpenSSL.
 *
 * NOTE: This uses a custom key derivation (SHA-256 of APP_KEY) and wire format
 * (IV prepended to ciphertext, base64-encoded) that is NOT compatible with
 * Laravel's Crypt facade. Existing TOTP secrets in the database depend on this
 * exact format — do NOT switch to Crypt::encrypt/decrypt without migrating data.
 */
class TotpEncryption
{
    private const CIPHER = 'aes-256-cbc';

    /**
     * Derive the encryption key from APP_KEY.
     *
     * Uses SHA-256 hash of the raw APP_KEY value (including any "base64:" prefix)
     * to ensure a consistent 32-byte key. This matches the original implementation
     * — do NOT strip the base64: prefix or existing encrypted secrets will break.
     */
    private static function getKey(): string
    {
        // Use config() which reads from Laravel's config/app.php
        // Note: config('app.key') returns the raw string (e.g. "base64:ABC...")
        // We must hash the raw string to stay compatible with existing data.
        $key = config('app.key', '');

        if (empty($key)) {
            throw new \RuntimeException('APP_KEY is not set — cannot encrypt/decrypt TOTP secrets.');
        }

        // SHA-256 hash ensures consistent 32-byte key
        return hash('sha256', $key, true);
    }

    /**
     * Encrypt a TOTP secret.
     *
     * @param string $data Plaintext TOTP secret
     * @return string Base64-encoded ciphertext (IV prepended)
     */
    public static function encrypt(string $data): string
    {
        $key = self::getKey();
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::CIPHER));
        $encrypted = openssl_encrypt($data, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            throw new \RuntimeException('TOTP encryption failed: ' . openssl_error_string());
        }
        // Prepend IV to ciphertext, then base64-encode
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt a TOTP secret.
     *
     * @param string $data Base64-encoded ciphertext (IV prepended)
     * @return string Plaintext TOTP secret
     */
    public static function decrypt(string $data): string
    {
        $key = self::getKey();
        $raw = base64_decode($data, true);
        if ($raw === false) {
            throw new \RuntimeException('TOTP decryption failed: invalid base64.');
        }
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        if (strlen($raw) < $ivLength) {
            throw new \RuntimeException('TOTP decryption failed: data too short.');
        }
        $iv = substr($raw, 0, $ivLength);
        $ciphertext = substr($raw, $ivLength);
        $decrypted = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            throw new \RuntimeException('TOTP decryption failed: ' . openssl_error_string());
        }
        return $decrypted;
    }
}

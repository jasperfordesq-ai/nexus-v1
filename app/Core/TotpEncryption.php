<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Core;

/**
 * TOTP secret encryption/decryption using AES-256-CBC + HMAC-SHA256 via OpenSSL.
 *
 * Wire format v2: base64(IV + ciphertext + HMAC)
 * Wire format v1 (legacy): base64(IV + ciphertext) — no HMAC, accepted on decrypt only
 *
 * NOTE: This uses a custom key derivation (SHA-256 of APP_KEY) and wire format
 * that is NOT compatible with Laravel's Crypt facade. Existing TOTP secrets in
 * the database depend on this exact format — do NOT switch to Crypt::encrypt/decrypt
 * without migrating data.
 */
class TotpEncryption
{
    private const CIPHER = 'aes-256-cbc';
    private const HMAC_LENGTH = 32; // SHA-256 produces 32 bytes

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
     * Derive a separate HMAC key from the encryption key.
     * Uses a different derivation to avoid key reuse between encryption and authentication.
     */
    private static function getHmacKey(): string
    {
        return hash_hmac('sha256', 'totp-hmac-key', self::getKey(), true);
    }

    /**
     * Encrypt a TOTP secret.
     *
     * @param string $data Plaintext TOTP secret
     * @return string Base64-encoded ciphertext (IV + ciphertext + HMAC)
     */
    public static function encrypt(string $data): string
    {
        $key = self::getKey();
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::CIPHER));
        $encrypted = openssl_encrypt($data, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            throw new \RuntimeException('TOTP encryption failed: ' . openssl_error_string());
        }
        // Compute HMAC over IV + ciphertext for authenticated encryption
        $payload = $iv . $encrypted;
        $hmac = hash_hmac('sha256', $payload, self::getHmacKey(), true);
        // Wire format: base64(IV + ciphertext + HMAC)
        return base64_encode($payload . $hmac);
    }

    /**
     * Decrypt a TOTP secret.
     *
     * Accepts both v2 (with HMAC) and v1 (legacy, without HMAC) wire formats.
     * v1 secrets will be decrypted but should be re-encrypted on next write to
     * upgrade them to v2 format.
     *
     * @param string $data Base64-encoded ciphertext
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

        // Determine if this is v2 (with HMAC) or v1 (legacy without HMAC).
        // AES-CBC block size is 16 bytes. Valid v1 payload: IV (16) + ciphertext (multiple of 16).
        // Valid v2 payload: IV (16) + ciphertext (multiple of 16) + HMAC (32).
        // If (len - IV) % 16 == 0, it's v1. If ((len - IV - 32) % 16 == 0 && len > IV + 32), it's v2.
        $payloadWithoutIv = strlen($raw) - $ivLength;
        $isV2 = ($payloadWithoutIv > self::HMAC_LENGTH)
            && (($payloadWithoutIv - self::HMAC_LENGTH) % 16 === 0);

        if ($isV2) {
            // Extract HMAC from the end
            $hmac = substr($raw, -self::HMAC_LENGTH);
            $payload = substr($raw, 0, -self::HMAC_LENGTH);

            // Verify HMAC before decrypting (Encrypt-then-MAC)
            $expectedHmac = hash_hmac('sha256', $payload, self::getHmacKey(), true);
            if (!hash_equals($expectedHmac, $hmac)) {
                throw new \RuntimeException('TOTP decryption failed: HMAC verification failed (data tampered).');
            }

            $iv = substr($payload, 0, $ivLength);
            $ciphertext = substr($payload, $ivLength);
        } else {
            // Legacy v1 format: no HMAC
            $iv = substr($raw, 0, $ivLength);
            $ciphertext = substr($raw, $ivLength);
        }

        $decrypted = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            throw new \RuntimeException('TOTP decryption failed: ' . openssl_error_string());
        }
        return $decrypted;
    }
}

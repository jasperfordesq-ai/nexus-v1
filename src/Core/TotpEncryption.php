<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Core;

/**
 * TotpEncryption - AES-256-GCM encryption for TOTP secrets
 *
 * Provides secure encryption/decryption of TOTP secrets stored in the database.
 * Uses AES-256-GCM which provides both confidentiality and authenticity.
 */
class TotpEncryption
{
    private const CIPHER = 'aes-256-gcm';
    private const TAG_LENGTH = 16;

    /**
     * Encrypt a TOTP secret
     *
     * @param string $plaintext The TOTP secret to encrypt
     * @return string Base64-encoded ciphertext (format: iv:ciphertext:tag)
     * @throws \RuntimeException If encryption fails
     */
    public static function encrypt(string $plaintext): string
    {
        $key = self::getKey();
        $iv = random_bytes(openssl_cipher_iv_length(self::CIPHER));
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('TOTP encryption failed');
        }

        // Combine IV, ciphertext, and tag
        return base64_encode($iv . $ciphertext . $tag);
    }

    /**
     * Decrypt a TOTP secret
     *
     * @param string $encrypted Base64-encoded ciphertext
     * @return string The decrypted TOTP secret
     * @throws \RuntimeException If decryption fails
     */
    public static function decrypt(string $encrypted): string
    {
        $key = self::getKey();
        $data = base64_decode($encrypted);

        if ($data === false) {
            throw new \RuntimeException('Invalid encrypted data format');
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = substr($data, 0, $ivLength);
        $tag = substr($data, -self::TAG_LENGTH);
        $ciphertext = substr($data, $ivLength, -self::TAG_LENGTH);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new \RuntimeException('TOTP decryption failed - data may be corrupted or key mismatch');
        }

        return $plaintext;
    }

    /**
     * Get the encryption key from environment
     *
     * @return string 32-byte encryption key
     * @throws \RuntimeException If key is not configured or invalid
     */
    private static function getKey(): string
    {
        $key = $_ENV['TOTP_ENCRYPTION_KEY'] ?? getenv('TOTP_ENCRYPTION_KEY');

        if (empty($key)) {
            throw new \RuntimeException('TOTP_ENCRYPTION_KEY not configured in environment');
        }

        // If key is base64 encoded (recommended for binary keys)
        if (strlen($key) !== 32) {
            $decoded = base64_decode($key, true);
            if ($decoded !== false && strlen($decoded) === 32) {
                return $decoded;
            }

            // Try hex decoding
            $decoded = hex2bin($key);
            if ($decoded !== false && strlen($decoded) === 32) {
                return $decoded;
            }

            // Hash the key to get consistent 32 bytes
            return hash('sha256', $key, true);
        }

        return $key;
    }

    /**
     * Generate a new encryption key (for initial setup)
     *
     * @return string Base64-encoded 32-byte key
     */
    public static function generateKey(): string
    {
        return base64_encode(random_bytes(32));
    }
}

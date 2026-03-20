<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Core;

/**
 * TOTP secret encryption/decryption using AES-256-CBC via OpenSSL.
 */
class TotpEncryption
{
    private const CIPHER = 'aes-256-cbc';

    private static function getKey(): string
    {
        $key = env('APP_KEY', getenv('APP_KEY') ?: '');
        if (empty($key)) {
            throw new \RuntimeException('APP_KEY is not set — cannot encrypt/decrypt TOTP secrets.');
        }
        // Use first 32 bytes of the key (SHA-256 hash ensures consistent length)
        return hash('sha256', $key, true);
    }

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
<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Core;

use App\Core\TotpEncryption;
use Tests\Laravel\TestCase;

class TotpEncryptionTest extends TestCase
{
    // -------------------------------------------------------
    // encrypt()
    // -------------------------------------------------------

    public function test_encrypt_returns_base64_string(): void
    {
        $encrypted = TotpEncryption::encrypt('JBSWY3DPEHPK3PXP');
        $this->assertIsString($encrypted);
        // Must be valid base64
        $this->assertNotFalse(base64_decode($encrypted, true));
    }

    public function test_encrypt_produces_different_ciphertexts_for_same_input(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $enc1 = TotpEncryption::encrypt($secret);
        $enc2 = TotpEncryption::encrypt($secret);
        // Different IVs should produce different ciphertexts
        $this->assertNotSame($enc1, $enc2);
    }

    // -------------------------------------------------------
    // decrypt()
    // -------------------------------------------------------

    public function test_decrypt_recovers_original_plaintext(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $encrypted = TotpEncryption::encrypt($secret);
        $decrypted = TotpEncryption::decrypt($encrypted);
        $this->assertSame($secret, $decrypted);
    }

    public function test_decrypt_with_empty_string_secret(): void
    {
        $secret = '';
        $encrypted = TotpEncryption::encrypt($secret);
        $decrypted = TotpEncryption::decrypt($encrypted);
        $this->assertSame($secret, $decrypted);
    }

    public function test_decrypt_with_long_secret(): void
    {
        $secret = str_repeat('ABCDEFGH', 50);
        $encrypted = TotpEncryption::encrypt($secret);
        $decrypted = TotpEncryption::decrypt($encrypted);
        $this->assertSame($secret, $decrypted);
    }

    public function test_decrypt_invalid_base64_throws_exception(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid base64');
        TotpEncryption::decrypt('!!!not-valid-base64!!!');
    }

    public function test_decrypt_too_short_data_throws_exception(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('data too short');
        // Base64 of just a few bytes (less than IV length of 16)
        TotpEncryption::decrypt(base64_encode('short'));
    }

    public function test_decrypt_corrupted_ciphertext_throws_exception(): void
    {
        $encrypted = TotpEncryption::encrypt('test-secret');
        // Corrupt the ciphertext by replacing part of it
        $raw = base64_decode($encrypted);
        $corrupted = substr($raw, 0, 16) . str_repeat("\x00", strlen($raw) - 16);
        $this->expectException(\RuntimeException::class);
        TotpEncryption::decrypt(base64_encode($corrupted));
    }
}

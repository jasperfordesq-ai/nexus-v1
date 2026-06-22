<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Support;

use App\Support\CursorSigner;
use Tests\Laravel\TestCase;

class CursorSignerTest extends TestCase
{
    public function testEncodeDecodeRoundTrip(): void
    {
        $payload = ['id' => 42, 'ts' => '2026-01-01 00:00:00', 'dir' => 'next'];
        $cursor = CursorSigner::encode($payload);

        $this->assertIsString($cursor);
        $this->assertNotSame('', $cursor);
        $this->assertSame($payload, CursorSigner::decode($cursor));
    }

    public function testDecodeReturnsNullForNullOrEmpty(): void
    {
        $this->assertNull(CursorSigner::decode(null));
        $this->assertNull(CursorSigner::decode(''));
    }

    public function testDecodeReturnsNullForInvalidBase64(): void
    {
        $this->assertNull(CursorSigner::decode('@@@not-base64@@@'));
    }

    public function testDecodeReturnsNullWhenSeparatorMissing(): void
    {
        $this->assertNull(CursorSigner::decode(base64_encode('no-dot-separator')));
    }

    public function testDecodeRejectsTamperedPayload(): void
    {
        $cursor = CursorSigner::encode(['id' => 1]);
        $raw = base64_decode($cursor, true);
        [$sig, $json] = explode('.', (string) $raw, 2);

        // Keep the original signature but mutate the payload — must fail HMAC.
        $tampered = base64_encode($sig . '.' . str_replace('1', '999', $json));
        $this->assertNull(CursorSigner::decode($tampered));
    }

    public function testDecodeRejectsForgedSignature(): void
    {
        $json = json_encode(['id' => 7]);
        $forged = base64_encode(str_repeat('0', 64) . '.' . $json);
        $this->assertNull(CursorSigner::decode($forged));
    }

    public function testValidSignatureButNonArrayPayloadReturnsNull(): void
    {
        // A correctly-signed scalar JSON must still decode to null (not an array).
        $json = json_encode('a bare string');
        $sig = hash_hmac('sha256', (string) $json, (string) config('app.key'));
        $cursor = base64_encode($sig . '.' . $json);

        $this->assertNull(CursorSigner::decode($cursor));
    }

    public function testDifferentPayloadsProduceDifferentCursors(): void
    {
        $this->assertNotSame(
            CursorSigner::encode(['id' => 1]),
            CursorSigner::encode(['id' => 2]),
        );
    }
}

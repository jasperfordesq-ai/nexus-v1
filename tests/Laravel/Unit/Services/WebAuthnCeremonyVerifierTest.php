<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use App\Services\WebAuthnCeremonyVerifier;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\Laravel\TestCase;

final class WebAuthnCeremonyVerifierTest extends TestCase
{
    /** @return array<string, array{int, string}> */
    public static function credentialAlgorithmProvider(): array
    {
        return [
            'ES256' => [-7, "\x26"],
            'EdDSA' => [-8, "\x27"],
            'RS256' => [-257, "\x39\x01\x00"],
            'unoffered ES384' => [-35, "\x38\x22"],
        ];
    }

    #[DataProvider('credentialAlgorithmProvider')]
    public function test_extracts_the_signed_cose_algorithm_from_attested_credential_data(
        int $expectedAlgorithm,
        string $encodedAlgorithm
    ): void {
        $credentialKey = "\xA1\x03" . $encodedAlgorithm;
        $authenticatorData = str_repeat("\0", 32)
            . "\x45"
            . pack('N', 0)
            . str_repeat("\0", 16)
            . pack('n', 1)
            . "\x01"
            . $credentialKey;
        $attestationObject = "\xA3"
            . "\x63fmt\x64none"
            . "\x67attStmt\xA0"
            . "\x68authData\x58"
            . chr(strlen($authenticatorData))
            . $authenticatorData;

        $method = new ReflectionMethod(WebAuthnCeremonyVerifier::class, 'credentialAlgorithm');
        $algorithm = $method->invoke(new WebAuthnCeremonyVerifier(), $attestationObject);

        $this->assertSame($expectedAlgorithm, $algorithm);
        $this->assertSame(
            in_array($expectedAlgorithm, [-7, -8, -257], true),
            in_array($algorithm, [-7, -8, -257], true)
        );
    }
}

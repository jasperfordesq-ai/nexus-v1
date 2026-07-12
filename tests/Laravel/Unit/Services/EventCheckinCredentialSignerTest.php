<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use App\Exceptions\EventOfflineCheckinException;
use App\Services\EventCheckinCredentialSigner;
use Carbon\CarbonImmutable;
use Tests\Laravel\TestCase;

final class EventCheckinCredentialSignerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('event_checkin.signing_seed', base64_encode(str_repeat("\x4a", 32)));
        config()->set('event_checkin.verification_keys_json', '{}');
    }

    public function test_signed_credential_is_pii_free_scoped_expiring_and_publicly_verifiable(): void
    {
        $signer = new EventCheckinCredentialSigner();
        $issuedAt = CarbonImmutable::parse('2027-05-01T10:00:00Z');
        $issued = $signer->issue(
            2,
            91,
            'event:91:2027-05-01T10:00:00Z',
            3,
            $issuedAt,
            $issuedAt->addHour(),
        );

        self::assertStringStartsWith('nqx2_', $issued['token']);
        $serialized = json_encode($issued, JSON_THROW_ON_ERROR);
        foreach (['name', 'email', 'phone', 'user_id', 'registration_id'] as $piiKey) {
            self::assertStringNotContainsString($piiKey, $serialized);
        }

        $claims = $signer->verify(
            $issued['token'],
            2,
            91,
            'event:91:2027-05-01T10:00:00Z',
            $issuedAt->addMinute(),
        );
        self::assertSame(2, $claims['v']);
        self::assertSame(3, $claims['ver']);
        self::assertSame(2, $claims['ten']);
        self::assertSame(91, $claims['evt']);
        self::assertSame($issued['kid'], $claims['kid']);

        $keys = $signer->publicKeySet();
        self::assertCount(1, $keys);
        self::assertSame($issued['kid'], $keys[0]['kid']);
        self::assertSame('Ed25519', $keys[0]['alg']);
        self::assertArrayNotHasKey('private_key', $keys[0]);
    }

    public function test_tampered_cross_scope_and_expired_credentials_fail_closed(): void
    {
        $signer = new EventCheckinCredentialSigner();
        $issuedAt = CarbonImmutable::parse('2027-05-01T10:00:00Z');
        $issued = $signer->issue(
            2,
            91,
            'event:91',
            1,
            $issuedAt,
            $issuedAt->addMinutes(30),
        );
        $tampered = substr($issued['token'], 0, -1)
            . (str_ends_with($issued['token'], 'A') ? 'B' : 'A');

        $this->assertReason(
            fn () => $signer->verify($tampered, 2, 91, 'event:91', $issuedAt),
            'event_qr_credential_signature_invalid',
        );
        $this->assertReason(
            fn () => $signer->verify($issued['token'], 3, 91, 'event:91', $issuedAt),
            'event_qr_credential_invalid',
        );
        $this->assertReason(
            fn () => $signer->verify($issued['token'], 2, 92, 'event:91', $issuedAt),
            'event_qr_credential_invalid',
        );
        $this->assertReason(
            fn () => $signer->verify($issued['token'], 2, 91, 'event:92', $issuedAt),
            'event_qr_credential_invalid',
        );
        $this->assertReason(
            fn () => $signer->verify(
                $issued['token'],
                2,
                91,
                'event:91',
                $issuedAt->addHour(),
            ),
            'event_qr_credential_expired',
        );
    }

    /** @param callable():mixed $operation */
    private function assertReason(callable $operation, string $reason): void
    {
        try {
            $operation();
            self::fail("Expected {$reason}.");
        } catch (EventOfflineCheckinException $exception) {
            self::assertSame($reason, $exception->reasonCode);
        }
    }
}

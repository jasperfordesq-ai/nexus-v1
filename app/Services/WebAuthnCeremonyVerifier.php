<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use lbuchs\WebAuthn\Binary\ByteBuffer;
use lbuchs\WebAuthn\CBOR\CborDecoder;
use lbuchs\WebAuthn\WebAuthn;

/**
 * Thin, injectable boundary around the WebAuthn verification library.
 *
 * Keeping the cryptographic verifier behind this service lets controller tests
 * exercise every account, tenant, challenge, and token gate without weakening
 * production verification or relying on browser-generated fixtures.
 */
class WebAuthnCeremonyVerifier
{
    /**
     * @return array{
     *   credential_id: string,
     *   public_key: string,
     *   sign_count: int,
     *   attestation_format: string,
     *   aaguid: ?string,
     *   user_verified: bool,
     *   backup_eligible: bool,
     *   backup_state: bool
     * }
     */
    public function verifyRegistration(
        string $rpName,
        string $rpId,
        string $clientDataJson,
        string $attestationObject,
        string $challenge
    ): array {
        $webAuthn = new WebAuthn($rpName, $rpId, ['none'], true);
        $data = $webAuthn->processCreate(
            $clientDataJson,
            $attestationObject,
            $challenge,
            true,  // Passwordless passkeys must prove local user verification.
            true,  // User presence remains mandatory.
            false, // Attestation is "none"; no trust-chain policy is asserted.
            false
        );
        $credentialAlgorithm = $this->credentialAlgorithm($attestationObject);
        if (!in_array($credentialAlgorithm, [-7, -8, -257], true)) {
            throw new \RuntimeException('Authenticator returned an unoffered credential algorithm.');
        }

        return [
            'credential_id' => (string) $data->credentialId,
            'public_key' => (string) $data->credentialPublicKey,
            'sign_count' => max(0, (int) ($data->signatureCounter ?? 0)),
            'attestation_format' => (string) ($data->attestationFormat ?? 'none'),
            'aaguid' => $this->formatAaguid($data->AAGUID ?? null),
            'user_verified' => (bool) ($data->userVerified ?? false),
            'backup_eligible' => (bool) ($data->isBackupEligible ?? false),
            'backup_state' => (bool) ($data->isBackedUp ?? false),
        ];
    }

    /**
     * Verify an assertion and return the authenticator's new signature counter.
     */
    public function verifyAuthentication(
        string $rpName,
        string $rpId,
        string $clientDataJson,
        string $authenticatorData,
        string $signature,
        string $publicKey,
        string $challenge,
        int $previousSignCount
    ): int {
        $webAuthn = new WebAuthn($rpName, $rpId, ['none'], true);
        $webAuthn->processGet(
            $clientDataJson,
            $authenticatorData,
            $signature,
            $publicKey,
            $challenge,
            max(0, $previousSignCount),
            true, // Reject UP-only assertions; this is a passwordless MFA path.
            true
        );

        return max(0, (int) ($webAuthn->getSignatureCounter() ?? 0));
    }

    private function formatAaguid(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        if (strlen($value) === 16) {
            $hex = bin2hex($value);

            return sprintf(
                '%s-%s-%s-%s-%s',
                substr($hex, 0, 8),
                substr($hex, 8, 4),
                substr($hex, 12, 4),
                substr($hex, 16, 4),
                substr($hex, 20, 12)
            );
        }

        $value = strtolower(trim($value));

        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $value)
            ? $value
            : null;
    }

    private function credentialAlgorithm(string $attestationObject): int
    {
        $decoded = CborDecoder::decode($attestationObject);
        $authData = is_array($decoded) ? ($decoded['authData'] ?? null) : null;
        if (!$authData instanceof ByteBuffer) {
            throw new \RuntimeException('Attestation did not contain authenticator data.');
        }

        $binary = $authData->getBinaryString();
        if (strlen($binary) < 55) {
            throw new \RuntimeException('Authenticator data did not contain a credential key.');
        }
        $lengthData = unpack('nlength', substr($binary, 53, 2));
        $credentialIdLength = (int) ($lengthData['length'] ?? -1);
        $coseOffset = 55 + $credentialIdLength;
        if ($credentialIdLength < 1 || $coseOffset >= strlen($binary)) {
            throw new \RuntimeException('Authenticator credential key offset was invalid.');
        }

        $endOffset = null;
        $credentialKey = CborDecoder::decodeInPlace($binary, $coseOffset, $endOffset);
        $algorithm = is_array($credentialKey) ? ($credentialKey[3] ?? null) : null;
        if (!is_int($algorithm)) {
            throw new \RuntimeException('Authenticator credential algorithm was missing.');
        }

        return $algorithm;
    }
}

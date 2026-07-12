<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Core\TenantContext;
use App\Enums\EventGuardianConsentStatus;
use App\Exceptions\EventSafetyException;
use App\Models\EventGuardianConsent;
use App\Models\EventGuardianConsentDeliveryEnvelope;
use App\Support\Events\EventGuardianConsentDeliveryClaim;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use JsonException;

/** AES-GCM vault for one-use guardian tokens; canonical consent stays hash-only. */
final class EventGuardianConsentDeliveryEnvelopeService
{
    private const CIPHER = 'aes-256-gcm';
    private const CIPHER_VERSION = 'aes-256-gcm-v1';
    private const ACTION = 'event.safety.guardian_consent.requested';
    private const STATUS_SEALED = 'sealed';
    private const STATUS_CLAIMED = 'claimed';
    private const STATUS_HANDED_OFF = 'handed_off';
    private const STATUS_EXPIRED = 'expired';

    public function __construct(
        private readonly ?\App\Support\Events\EventSafetyFoundationSupport $support = null,
    ) {}

    public function assertCryptoAvailable(): void
    {
        [, $key] = $this->activeKey();
        $aad = 'event-guardian-consent-delivery-readiness-v1';
        $probe = bin2hex(random_bytes(16));
        $ciphertext = $this->encrypt($probe, $key, $aad);
        if (! hash_equals($probe, $this->decrypt($ciphertext, $key, $aad))) {
            throw new EventSafetyException('event_guardian_delivery_cipher_unavailable');
        }
    }

    public function seal(
        EventGuardianConsent $consent,
        int $outboxId,
        string $guardianToken,
    ): EventGuardianConsentDeliveryEnvelope {
        $this->assertTransaction();
        $tenantId = $this->tenantId();
        $eventId = (int) $consent->event_id;
        $consentId = (int) $consent->getKey();
        $consentVersion = (int) $consent->consent_version;
        $expiresAt = $this->carbon($consent->getRawOriginal('expires_at'));
        $tokenHash = $this->foundation()->tokenHash($tenantId, $guardianToken);
        $storedTokenHash = $consent->getRawOriginal('token_hash');
        if ((int) $consent->tenant_id !== $tenantId
            || $eventId <= 0
            || $consentId <= 0
            || $consentVersion <= 0
            || $outboxId <= 0
            || (string) $consent->getRawOriginal('status') !== EventGuardianConsentStatus::Pending->value
            || $expiresAt === null
            || ! $expiresAt->isFuture()
            || ! is_string($storedTokenHash)
            || ! hash_equals($storedTokenHash, $tokenHash)) {
            throw new EventSafetyException('event_guardian_delivery_envelope_scope_invalid');
        }
        $outbox = DB::table('event_domain_outbox')
            ->where('id', $outboxId)
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('action', self::ACTION)
            ->where('aggregate_version', $consentVersion)
            ->lockForUpdate()
            ->first();
        if ($outbox === null) {
            throw new EventSafetyException('event_guardian_delivery_envelope_outbox_invalid');
        }

        [$keyVersion, $key, $keyFingerprint] = $this->activeKey();
        $aad = $this->aad(
            $tenantId,
            $eventId,
            $consentId,
            $outboxId,
            $consentVersion,
            self::CIPHER_VERSION,
            $keyVersion,
        );
        $now = now();
        try {
            $id = (int) DB::table('event_guardian_consent_delivery_envelopes')->insertGetId([
                'tenant_id' => $tenantId,
                'event_id' => $eventId,
                'consent_id' => $consentId,
                'outbox_id' => $outboxId,
                'consent_version' => $consentVersion,
                'action' => self::ACTION,
                'cipher_version' => self::CIPHER_VERSION,
                'key_version' => $keyVersion,
                'key_fingerprint' => $keyFingerprint,
                'aad_hash' => hash('sha256', $aad),
                'token_ciphertext' => $this->encrypt($guardianToken, $key, $aad),
                'status' => self::STATUS_SEALED,
                'envelope_version' => 1,
                'expires_at' => $expiresAt,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (QueryException $exception) {
            if ($this->isUniqueConflict($exception)) {
                throw new EventSafetyException('event_guardian_delivery_envelope_conflict');
            }
            throw $exception;
        }
        $envelope = $this->envelope($tenantId, $id, true);
        $this->recordAccess(
            $envelope,
            'sealed',
            null,
            null,
            self::STATUS_SEALED,
            "seal:{$outboxId}",
            null,
            ['cipher_version' => self::CIPHER_VERSION, 'key_version' => $keyVersion],
            $now,
        );

        return $envelope;
    }

    public function claimForDelivery(
        int $outboxId,
        string $consumer,
        string $idempotencyKey,
    ): EventGuardianConsentDeliveryClaim {
        $tenantId = $this->tenantId();
        $consumer = $this->consumer($consumer);
        $auditKey = $this->idempotencyKey(
            $idempotencyKey,
            "claim:{$tenantId}:{$outboxId}:{$consumer}",
        );

        return DB::transaction(function () use (
            $tenantId,
            $outboxId,
            $consumer,
            $auditKey,
        ): EventGuardianConsentDeliveryClaim {
            if (DB::table('event_guardian_consent_delivery_access')
                ->where('tenant_id', $tenantId)
                ->where('idempotency_key', $auditKey)
                ->lockForUpdate()
                ->exists()) {
                throw new EventSafetyException('event_guardian_delivery_envelope_already_claimed');
            }
            $envelope = $this->envelopeForOutbox($tenantId, $outboxId, true);
            if ((string) $envelope->status !== self::STATUS_SEALED
                || $envelope->claimed_at !== null
                || $envelope->claim_token_hash !== null) {
                throw new EventSafetyException('event_guardian_delivery_envelope_already_claimed');
            }
            if ($envelope->expires_at === null || ! $envelope->expires_at->isFuture()) {
                $this->expireLocked($envelope, 'claim_expired', $auditKey);
                throw new EventSafetyException('event_guardian_delivery_envelope_expired');
            }
            $guardianToken = $this->decryptEnvelope($envelope);
            $this->assertConsentStillPending($envelope, $guardianToken);
            $claimToken = $this->recoverableClaimToken($envelope, $auditKey);
            $claimHash = hash('sha256', $claimToken);
            $now = now();
            $from = (string) $envelope->status;
            $envelope->forceFill([
                'status' => self::STATUS_CLAIMED,
                'envelope_version' => (int) $envelope->envelope_version + 1,
                'claim_token_hash' => $claimHash,
                'claimed_by' => $consumer,
                'claimed_at' => $now,
            ])->save();
            $this->recordAccess(
                $envelope,
                'claimed',
                $consumer,
                $from,
                self::STATUS_CLAIMED,
                $auditKey,
                $claimHash,
                [],
                $now,
                true,
            );
            $envelope->refresh();

            return new EventGuardianConsentDeliveryClaim(
                $envelope,
                $guardianToken,
                $claimToken,
            );
        }, 5);
    }

    public function resumeClaimForDelivery(
        int $outboxId,
        string $consumer,
        string $idempotencyKey,
    ): EventGuardianConsentDeliveryClaim {
        $tenantId = $this->tenantId();
        $consumer = $this->consumer($consumer);
        $claimKey = $this->idempotencyKey(
            $idempotencyKey,
            "claim:{$tenantId}:{$outboxId}:{$consumer}",
        );
        $resumeKey = hash('sha256', "event-guardian-delivery:v1:resume:{$claimKey}");

        return DB::transaction(function () use (
            $tenantId,
            $outboxId,
            $consumer,
            $claimKey,
            $resumeKey,
        ): EventGuardianConsentDeliveryClaim {
            $original = DB::table('event_guardian_consent_delivery_access')
                ->where('tenant_id', $tenantId)
                ->where('idempotency_key', $claimKey)
                ->lockForUpdate()
                ->first();
            if ($original === null
                || (string) $original->operation !== 'claimed'
                || (string) $original->consumer !== $consumer
                || (int) $original->outbox_id !== $outboxId) {
                throw new EventSafetyException('event_guardian_delivery_envelope_resume_denied');
            }
            $envelope = $this->envelope($tenantId, (int) $original->envelope_id, true);
            if ((string) $envelope->status !== self::STATUS_CLAIMED
                || (string) $envelope->claimed_by !== $consumer
                || $envelope->getRawOriginal('token_ciphertext') === null) {
                throw new EventSafetyException('event_guardian_delivery_envelope_resume_denied');
            }
            if ($envelope->expires_at === null || ! $envelope->expires_at->isFuture()) {
                $this->expireLocked($envelope, 'resume_expired', $resumeKey, true);
                throw new EventSafetyException('event_guardian_delivery_envelope_expired');
            }
            $guardianToken = $this->decryptEnvelope($envelope);
            $this->assertConsentStillPending($envelope, $guardianToken);
            $claimToken = $this->recoverableClaimToken($envelope, $claimKey);
            $claimHash = hash('sha256', $claimToken);
            $storedHash = $envelope->getRawOriginal('claim_token_hash');
            if (! is_string($storedHash) || ! hash_equals($storedHash, $claimHash)) {
                throw new EventSafetyException('event_guardian_delivery_envelope_claim_invalid');
            }
            if (! DB::table('event_guardian_consent_delivery_access')
                ->where('tenant_id', $tenantId)
                ->where('idempotency_key', $resumeKey)
                ->exists()) {
                $this->recordAccess(
                    $envelope,
                    'claim_resumed',
                    $consumer,
                    self::STATUS_CLAIMED,
                    self::STATUS_CLAIMED,
                    $resumeKey,
                    $claimHash,
                    [],
                    now(),
                    true,
                );
            }

            return new EventGuardianConsentDeliveryClaim(
                $envelope,
                $guardianToken,
                $claimToken,
            );
        }, 5);
    }

    public function completeHandoff(
        int $envelopeId,
        string $claimToken,
        string $consumer,
        string $idempotencyKey,
    ): EventGuardianConsentDeliveryEnvelope {
        $tenantId = $this->tenantId();
        $consumer = $this->consumer($consumer);
        $auditKey = $this->idempotencyKey(
            $idempotencyKey,
            "handoff:{$tenantId}:{$envelopeId}:{$consumer}",
        );

        return DB::transaction(function () use (
            $tenantId,
            $envelopeId,
            $claimToken,
            $consumer,
            $auditKey,
        ): EventGuardianConsentDeliveryEnvelope {
            $envelope = $this->envelope($tenantId, $envelopeId, true);
            if ((string) $envelope->status === self::STATUS_HANDED_OFF) {
                return $envelope;
            }
            $storedHash = $envelope->getRawOriginal('claim_token_hash');
            if ((string) $envelope->status !== self::STATUS_CLAIMED
                || (string) $envelope->claimed_by !== $consumer
                || ! is_string($storedHash)
                || ! hash_equals($storedHash, hash('sha256', $claimToken))) {
                throw new EventSafetyException('event_guardian_delivery_envelope_claim_invalid');
            }
            $now = now();
            $from = (string) $envelope->status;
            $envelope->forceFill([
                'status' => self::STATUS_HANDED_OFF,
                'envelope_version' => (int) $envelope->envelope_version + 1,
                'token_ciphertext' => null,
                'claim_token_hash' => null,
                'handed_off_at' => $now,
                'erased_at' => $now,
            ])->save();
            $this->recordAccess(
                $envelope,
                'handed_off',
                $consumer,
                $from,
                self::STATUS_HANDED_OFF,
                $auditKey,
                hash('sha256', $claimToken),
                ['ciphertext_erased' => true],
                $now,
            );
            $envelope->refresh();

            return $envelope;
        }, 5);
    }

    public function expireForOutbox(int $outboxId, string $reason): void
    {
        $tenantId = $this->tenantId();
        DB::transaction(function () use ($tenantId, $outboxId, $reason): void {
            $envelope = $this->envelopeForOutbox($tenantId, $outboxId, true);
            if (in_array((string) $envelope->status, [self::STATUS_HANDED_OFF, self::STATUS_EXPIRED], true)) {
                return;
            }
            $this->expireLocked(
                $envelope,
                $reason,
                "expire:{$tenantId}:{$outboxId}:{$reason}",
            );
        }, 5);
    }

    private function expireLocked(
        EventGuardianConsentDeliveryEnvelope $envelope,
        string $reason,
        string $idempotencyKey,
        bool $normalized = false,
    ): void {
        $now = now();
        $from = (string) $envelope->status;
        $claimHash = is_string($envelope->getRawOriginal('claim_token_hash'))
            ? $envelope->getRawOriginal('claim_token_hash')
            : null;
        $envelope->forceFill([
            'status' => self::STATUS_EXPIRED,
            'envelope_version' => (int) $envelope->envelope_version + 1,
            'token_ciphertext' => null,
            'claim_token_hash' => null,
            'erased_at' => $now,
        ])->save();
        $this->recordAccess(
            $envelope,
            'expired',
            'system',
            $from,
            self::STATUS_EXPIRED,
            $idempotencyKey,
            is_string($claimHash) ? $claimHash : null,
            ['ciphertext_erased' => true, 'reason_code' => mb_substr($reason, 0, 100)],
            $now,
            $normalized,
        );
    }

    private function decryptEnvelope(EventGuardianConsentDeliveryEnvelope $envelope): string
    {
        if ((string) $envelope->cipher_version !== self::CIPHER_VERSION) {
            throw new EventSafetyException('event_guardian_delivery_cipher_unsupported');
        }
        [$key, $fingerprint] = $this->keyForVersion((string) $envelope->key_version);
        if (! hash_equals((string) $envelope->key_fingerprint, $fingerprint)) {
            throw new EventSafetyException('event_guardian_delivery_key_unavailable');
        }
        $aad = $this->aad(
            (int) $envelope->tenant_id,
            (int) $envelope->event_id,
            (int) $envelope->consent_id,
            (int) $envelope->outbox_id,
            (int) $envelope->consent_version,
            (string) $envelope->cipher_version,
            (string) $envelope->key_version,
        );
        if (! hash_equals((string) $envelope->aad_hash, hash('sha256', $aad))) {
            throw new EventSafetyException('event_guardian_delivery_envelope_scope_invalid');
        }
        $ciphertext = $envelope->getRawOriginal('token_ciphertext');
        if (! is_string($ciphertext) || $ciphertext === '') {
            throw new EventSafetyException('event_guardian_delivery_envelope_erased');
        }

        return $this->decrypt($ciphertext, $key, $aad);
    }

    private function assertConsentStillPending(
        EventGuardianConsentDeliveryEnvelope $envelope,
        string $guardianToken,
    ): void {
        $consent = EventGuardianConsent::withoutGlobalScopes()
            ->where('tenant_id', (int) $envelope->tenant_id)
            ->where('event_id', (int) $envelope->event_id)
            ->whereKey((int) $envelope->consent_id)
            ->where('status', EventGuardianConsentStatus::Pending->value)
            ->where('consent_version', (int) $envelope->consent_version)
            ->where('expires_at', '>', now())
            ->first();
        if ($consent === null) {
            throw new EventSafetyException('event_guardian_delivery_envelope_scope_invalid');
        }
        $hash = $this->foundation()->tokenHash((int) $envelope->tenant_id, $guardianToken);
        $stored = $consent->getRawOriginal('token_hash');
        if (! is_string($stored) || ! hash_equals($stored, $hash)) {
            throw new EventSafetyException('event_guardian_delivery_envelope_scope_invalid');
        }
    }

    private function recoverableClaimToken(
        EventGuardianConsentDeliveryEnvelope $envelope,
        string $claimAuditKey,
    ): string {
        [$key, $fingerprint] = $this->keyForVersion((string) $envelope->key_version);
        if (! hash_equals((string) $envelope->key_fingerprint, $fingerprint)) {
            throw new EventSafetyException('event_guardian_delivery_key_unavailable');
        }

        return hash_hmac('sha256', implode(':', [
            'event-guardian-delivery-claim-v1',
            (string) $envelope->tenant_id,
            (string) $envelope->event_id,
            (string) $envelope->getKey(),
            $claimAuditKey,
        ]), $key);
    }

    /** @return array{string,string,string} */
    private function activeKey(): array
    {
        $version = trim((string) config(
            'events.safety.guardian_delivery_envelope.active_key_version',
            'app-key-v1',
        ));
        if ($version === '' || mb_strlen($version) > 64) {
            throw new EventSafetyException('event_guardian_delivery_key_unavailable');
        }
        [$key, $fingerprint] = $this->keyForVersion($version);

        return [$version, $key, $fingerprint];
    }

    /** @return array{string,string} */
    private function keyForVersion(string $version): array
    {
        $active = trim((string) config(
            'events.safety.guardian_delivery_envelope.active_key_version',
            'app-key-v1',
        ));
        $material = null;
        if ($version === $active) {
            $configured = config('events.safety.guardian_delivery_envelope.active_key');
            if (is_string($configured) && trim($configured) !== '') {
                $material = trim($configured);
            } elseif ((bool) config(
                'events.safety.guardian_delivery_envelope.fallback_to_app_key',
                true,
            )) {
                $appKey = config('app.key');
                $material = is_string($appKey) ? trim($appKey) : null;
            }
        } else {
            $previous = config('events.safety.guardian_delivery_envelope.previous_keys', []);
            $candidate = is_array($previous) ? ($previous[$version] ?? null) : null;
            $material = is_string($candidate) ? trim($candidate) : null;
        }
        $key = $this->normalizeKey($material);
        if ($key === null) {
            throw new EventSafetyException('event_guardian_delivery_key_unavailable');
        }

        return [$key, hash('sha256', $key)];
    }

    private function normalizeKey(?string $material): ?string
    {
        if ($material === null || $material === '') {
            return null;
        }
        if (str_starts_with($material, 'base64:')) {
            $decoded = base64_decode(substr($material, 7), true);
            return is_string($decoded) && strlen($decoded) === 32 ? $decoded : null;
        }
        $decoded = base64_decode($material, true);
        if (is_string($decoded) && strlen($decoded) === 32) {
            return $decoded;
        }

        return strlen($material) === 32 ? $material : null;
    }

    private function encrypt(string $plaintext, string $key, string $aad): string
    {
        if (! function_exists('openssl_encrypt')) {
            throw new EventSafetyException('event_guardian_delivery_cipher_unavailable');
        }
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $aad,
            16,
        );
        if (! is_string($ciphertext) || strlen($tag) !== 16) {
            throw new EventSafetyException('event_guardian_delivery_cipher_unavailable');
        }

        return json_encode([
            'v' => 1,
            'n' => base64_encode($nonce),
            'c' => base64_encode($ciphertext),
            't' => base64_encode($tag),
        ], JSON_THROW_ON_ERROR);
    }

    private function decrypt(string $payload, string $key, string $aad): string
    {
        if (! function_exists('openssl_decrypt')) {
            throw new EventSafetyException('event_guardian_delivery_cipher_unavailable');
        }
        try {
            $decoded = json_decode($payload, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new EventSafetyException('event_guardian_delivery_ciphertext_invalid');
        }
        if (! is_array($decoded) || ($decoded['v'] ?? null) !== 1) {
            throw new EventSafetyException('event_guardian_delivery_ciphertext_invalid');
        }
        $nonce = $this->base64Field($decoded, 'n', 12);
        $ciphertext = $this->base64Field($decoded, 'c');
        $tag = $this->base64Field($decoded, 't', 16);
        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $aad,
        );
        if (! is_string($plaintext) || $plaintext === '') {
            throw new EventSafetyException('event_guardian_delivery_decryption_failed');
        }

        return $plaintext;
    }

    /** @param array<string,mixed> $payload */
    private function base64Field(array $payload, string $field, ?int $length = null): string
    {
        $encoded = $payload[$field] ?? null;
        $decoded = is_string($encoded) ? base64_decode($encoded, true) : false;
        if (! is_string($decoded) || ($length !== null && strlen($decoded) !== $length)) {
            throw new EventSafetyException('event_guardian_delivery_ciphertext_invalid');
        }

        return $decoded;
    }

    private function aad(
        int $tenantId,
        int $eventId,
        int $consentId,
        int $outboxId,
        int $consentVersion,
        string $cipherVersion,
        string $keyVersion,
    ): string {
        return implode('|', [
            'event-guardian-consent-delivery',
            'v1',
            "tenant={$tenantId}",
            "event={$eventId}",
            "consent={$consentId}",
            "outbox={$outboxId}",
            "consent_version={$consentVersion}",
            'action=' . self::ACTION,
            "cipher={$cipherVersion}",
            "key={$keyVersion}",
        ]);
    }

    /** @param array<string,mixed> $metadata */
    private function recordAccess(
        EventGuardianConsentDeliveryEnvelope $envelope,
        string $operation,
        ?string $consumer,
        ?string $from,
        string $to,
        string $idempotencyKey,
        ?string $claimHash,
        array $metadata,
        Carbon $now,
        bool $keyIsNormalized = false,
    ): int {
        $tenantId = (int) $envelope->tenant_id;
        $key = $keyIsNormalized
            ? $idempotencyKey
            : $this->idempotencyKey(
                $idempotencyKey,
                "access:{$tenantId}:{$envelope->getKey()}:{$operation}",
            );
        try {
            return (int) DB::table('event_guardian_consent_delivery_access')->insertGetId([
                'tenant_id' => $tenantId,
                'event_id' => (int) $envelope->event_id,
                'envelope_id' => (int) $envelope->getKey(),
                'consent_id' => (int) $envelope->consent_id,
                'outbox_id' => (int) $envelope->outbox_id,
                'consent_version' => (int) $envelope->consent_version,
                'operation' => $operation,
                'consumer' => $consumer,
                'claim_id_hash' => $claimHash,
                'from_status' => $from,
                'to_status' => $to,
                'idempotency_key' => $key,
                'metadata' => json_encode(array_merge([
                    'schema_version' => 1,
                    'envelope_version' => (int) $envelope->envelope_version,
                ], $metadata), JSON_THROW_ON_ERROR),
                'created_at' => $now,
            ]);
        } catch (QueryException $exception) {
            if ($this->isUniqueConflict($exception)) {
                throw new EventSafetyException('event_guardian_delivery_envelope_conflict');
            }
            throw $exception;
        }
    }

    private function envelope(int $tenantId, int $id, bool $lock): EventGuardianConsentDeliveryEnvelope
    {
        $query = EventGuardianConsentDeliveryEnvelope::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereKey($id);
        if ($lock) {
            $query->lockForUpdate();
        }
        $envelope = $query->first();
        if ($envelope === null) {
            throw new EventSafetyException('event_guardian_delivery_envelope_not_found');
        }

        return $envelope;
    }

    private function envelopeForOutbox(
        int $tenantId,
        int $outboxId,
        bool $lock,
    ): EventGuardianConsentDeliveryEnvelope {
        $query = EventGuardianConsentDeliveryEnvelope::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('outbox_id', $outboxId);
        if ($lock) {
            $query->lockForUpdate();
        }
        $envelope = $query->first();
        if ($envelope === null) {
            throw new EventSafetyException('event_guardian_delivery_envelope_not_found');
        }

        return $envelope;
    }

    private function consumer(string $consumer): string
    {
        $consumer = trim($consumer);
        if ($consumer === '' || mb_strlen($consumer) > 191
            || preg_match('/^[A-Za-z0-9._:-]+$/', $consumer) !== 1) {
            throw new EventSafetyException('event_guardian_delivery_consumer_invalid');
        }

        return $consumer;
    }

    private function idempotencyKey(string $key, string $scope): string
    {
        $key = trim($key);
        if ($key === '' || mb_strlen($key) > 191) {
            throw new EventSafetyException('event_guardian_delivery_idempotency_invalid');
        }

        return hash('sha256', "event-guardian-delivery:v1:{$scope}:{$key}");
    }

    private function assertTransaction(): void
    {
        if (DB::transactionLevel() <= 0) {
            throw new EventSafetyException('event_guardian_delivery_transaction_required');
        }
    }

    private function tenantId(): int
    {
        $tenantId = TenantContext::currentId();
        if ($tenantId === null || $tenantId <= 0) {
            throw new EventSafetyException('event_safety_tenant_context_required');
        }

        return $tenantId;
    }

    private function foundation(): \App\Support\Events\EventSafetyFoundationSupport
    {
        return $this->support ?? new \App\Support\Events\EventSafetyFoundationSupport();
    }

    private function carbon(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return Carbon::parse($value, 'UTC');
        } catch (\Throwable) {
            return null;
        }
    }

    private function isUniqueConflict(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[0] ?? ''), ['23000', '23505'], true);
    }
}

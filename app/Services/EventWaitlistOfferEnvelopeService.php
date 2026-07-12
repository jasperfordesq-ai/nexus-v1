<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Core\TenantContext;
use App\Enums\EventWaitlistQueueState;
use App\Exceptions\EventWaitlistException;
use App\Models\EventWaitlistEntry;
use App\Models\EventWaitlistOfferEnvelope;
use App\Support\Events\EventWaitlistOfferEnvelopeClaim;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use JsonException;

/** AES-GCM delivery-secret vault for waitlist offers; canonical facts remain hash-only. */
final class EventWaitlistOfferEnvelopeService
{
    private const CIPHER = 'aes-256-gcm';
    private const CIPHER_VERSION = 'aes-256-gcm-v1';
    private const OFFER_ACTION = 'event.waitlist.offered';
    private const STATUS_SEALED = 'sealed';
    private const STATUS_CLAIMED = 'claimed';
    private const STATUS_HANDED_OFF = 'handed_off';
    private const STATUS_ERASED = 'erased';
    private const STATUS_EXPIRED = 'expired';

    /**
     * Prove the configured envelope key and cipher can perform a local
     * authenticated-encryption round trip without exposing either secret.
     */
    public function assertCryptoAvailable(): void
    {
        [, $key] = $this->activeKey();
        $aad = 'event-waitlist-envelope-readiness-v1';
        $probe = bin2hex(random_bytes(16));
        $ciphertext = $this->encrypt($probe, $key, $aad);
        if (! hash_equals($probe, $this->decrypt($ciphertext, $key, $aad))) {
            throw new EventWaitlistException('event_waitlist_offer_envelope_cipher_unavailable');
        }
    }

    public function seal(
        EventWaitlistEntry $entry,
        int $outboxId,
        string $action,
        string $offerToken,
    ): EventWaitlistOfferEnvelope {
        $this->assertTransaction();
        $tenantId = $this->tenantId();
        $eventId = (int) $entry->event_id;
        $entryId = (int) $entry->getKey();
        $queueVersion = (int) $entry->queue_version;
        $action = trim($action);
        $expiresAt = $this->carbon($entry->getRawOriginal('offer_expires_at'));
        $storedTokenHash = $entry->getRawOriginal('offer_token_hash');
        if ((int) $entry->tenant_id !== $tenantId
            || $eventId <= 0
            || $entryId <= 0
            || $queueVersion <= 0
            || $outboxId <= 0
            || $entry->queue_state !== EventWaitlistQueueState::Offered
            || $action !== self::OFFER_ACTION
            || $expiresAt === null
            || ! $expiresAt->isFuture()
            || ! is_string($storedTokenHash)
            || ! hash_equals($storedTokenHash, hash('sha256', $offerToken))) {
            throw new EventWaitlistException('event_waitlist_offer_envelope_scope_invalid');
        }

        $outbox = DB::table('event_domain_outbox')
            ->where('id', $outboxId)
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('action', $action)
            ->lockForUpdate()
            ->first();
        if ($outbox === null) {
            throw new EventWaitlistException('event_waitlist_offer_envelope_outbox_invalid');
        }

        [$keyVersion, $key, $keyFingerprint] = $this->activeKey();
        $aad = $this->aad(
            $tenantId,
            $eventId,
            $entryId,
            $outboxId,
            $action,
            $queueVersion,
            self::CIPHER_VERSION,
            $keyVersion,
        );
        $ciphertext = $this->encrypt($offerToken, $key, $aad);
        $now = now();

        try {
            $id = (int) DB::table('event_waitlist_offer_envelopes')->insertGetId([
                'tenant_id' => $tenantId,
                'event_id' => $eventId,
                'waitlist_entry_id' => $entryId,
                'outbox_id' => $outboxId,
                'queue_version' => $queueVersion,
                'action' => $action,
                'cipher_version' => self::CIPHER_VERSION,
                'key_version' => $keyVersion,
                'key_fingerprint' => $keyFingerprint,
                'aad_hash' => hash('sha256', $aad),
                'token_ciphertext' => $ciphertext,
                'status' => self::STATUS_SEALED,
                'envelope_version' => 1,
                'expires_at' => $expiresAt,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (QueryException $exception) {
            if ($this->isUniqueConflict($exception)) {
                throw new EventWaitlistException('event_waitlist_offer_envelope_conflict');
            }
            throw $exception;
        }

        /** @var EventWaitlistOfferEnvelope $envelope */
        $envelope = EventWaitlistOfferEnvelope::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereKey($id)
            ->lockForUpdate()
            ->firstOrFail();
        $this->recordAccess(
            $envelope,
            'sealed',
            null,
            null,
            self::STATUS_SEALED,
            "seal:{$outboxId}",
            null,
            [
                'cipher_version' => self::CIPHER_VERSION,
                'key_version' => $keyVersion,
            ],
            $now,
        );

        return $envelope;
    }

    public function claimForDelivery(
        int $outboxId,
        string $consumer,
        string $idempotencyKey,
    ): EventWaitlistOfferEnvelopeClaim {
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
        ): EventWaitlistOfferEnvelopeClaim {
            $replay = DB::table('event_waitlist_offer_envelope_access')
                ->where('tenant_id', $tenantId)
                ->where('idempotency_key', $auditKey)
                ->lockForUpdate()
                ->first();
            if ($replay !== null) {
                throw new EventWaitlistException('event_waitlist_offer_envelope_already_claimed');
            }

            /** @var EventWaitlistOfferEnvelope|null $envelope */
            $envelope = EventWaitlistOfferEnvelope::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('outbox_id', $outboxId)
                ->lockForUpdate()
                ->first();
            if ($envelope === null) {
                throw new EventWaitlistException('event_waitlist_offer_envelope_not_found');
            }
            if ((string) $envelope->status !== self::STATUS_SEALED
                || $envelope->claimed_at !== null
                || $envelope->claim_token_hash !== null) {
                throw new EventWaitlistException('event_waitlist_offer_envelope_already_claimed');
            }
            if ($envelope->expires_at === null || ! $envelope->expires_at->isFuture()) {
                throw new EventWaitlistException('event_waitlist_offer_envelope_expired');
            }

            $offerToken = $this->decryptEnvelope($envelope);
            $entryTokenHash = DB::table('event_waitlist_entries')
                ->where('tenant_id', $tenantId)
                ->where('event_id', (int) $envelope->event_id)
                ->where('id', (int) $envelope->waitlist_entry_id)
                ->where('queue_state', EventWaitlistQueueState::Offered->value)
                ->value('offer_token_hash');
            if (! is_string($entryTokenHash)
                || ! hash_equals($entryTokenHash, hash('sha256', $offerToken))) {
                throw new EventWaitlistException('event_waitlist_offer_envelope_scope_invalid');
            }

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

            return new EventWaitlistOfferEnvelopeClaim(
                $envelope,
                $offerToken,
                $claimToken,
            );
        }, 5);
    }

    /**
     * Resume a claim after a worker/provider crash without widening secret access.
     *
     * The caller must present the exact consumer and idempotency key used by the
     * original claim. The offer token and a recoverable claim token are returned
     * only in the in-memory value object; neither is persisted in plaintext.
     */
    public function resumeClaimForDelivery(
        int $outboxId,
        string $consumer,
        string $idempotencyKey,
    ): EventWaitlistOfferEnvelopeClaim {
        $tenantId = $this->tenantId();
        $consumer = $this->consumer($consumer);
        $claimAuditKey = $this->idempotencyKey(
            $idempotencyKey,
            "claim:{$tenantId}:{$outboxId}:{$consumer}",
        );
        $resumeAuditKey = hash(
            'sha256',
            "event-waitlist-envelope:v1:resume:{$claimAuditKey}",
        );

        return DB::transaction(function () use (
            $tenantId,
            $outboxId,
            $consumer,
            $claimAuditKey,
            $resumeAuditKey,
        ): EventWaitlistOfferEnvelopeClaim {
            $originalClaim = DB::table('event_waitlist_offer_envelope_access')
                ->where('tenant_id', $tenantId)
                ->where('idempotency_key', $claimAuditKey)
                ->lockForUpdate()
                ->first();
            if ($originalClaim === null
                || (string) $originalClaim->operation !== 'claimed'
                || (string) $originalClaim->consumer !== $consumer
                || (int) $originalClaim->outbox_id !== $outboxId) {
                throw new EventWaitlistException('event_waitlist_offer_envelope_resume_denied');
            }

            /** @var EventWaitlistOfferEnvelope|null $envelope */
            $envelope = EventWaitlistOfferEnvelope::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('outbox_id', $outboxId)
                ->whereKey((int) $originalClaim->envelope_id)
                ->lockForUpdate()
                ->first();
            if ($envelope === null) {
                throw new EventWaitlistException('event_waitlist_offer_envelope_not_found');
            }
            if ((string) $envelope->status !== self::STATUS_CLAIMED
                || (string) $envelope->claimed_by !== $consumer
                || $envelope->getRawOriginal('token_ciphertext') === null) {
                throw new EventWaitlistException('event_waitlist_offer_envelope_resume_denied');
            }
            if ($envelope->expires_at === null || ! $envelope->expires_at->isFuture()) {
                throw new EventWaitlistException('event_waitlist_offer_envelope_expired');
            }

            $offerToken = $this->decryptEnvelope($envelope);
            $this->assertOfferStillActive($envelope, $offerToken);
            $claimToken = $this->recoverableClaimToken($envelope, $claimAuditKey);
            $claimHash = hash('sha256', $claimToken);
            $storedClaimHash = $envelope->getRawOriginal('claim_token_hash');
            if (! is_string($storedClaimHash)
                || ! hash_equals($storedClaimHash, $claimHash)) {
                $envelope->forceFill([
                    'envelope_version' => (int) $envelope->envelope_version + 1,
                    'claim_token_hash' => $claimHash,
                ])->save();
            }

            $resume = DB::table('event_waitlist_offer_envelope_access')
                ->where('tenant_id', $tenantId)
                ->where('idempotency_key', $resumeAuditKey)
                ->lockForUpdate()
                ->first();
            if ($resume === null) {
                $this->recordAccess(
                    $envelope,
                    'claim_resumed',
                    $consumer,
                    self::STATUS_CLAIMED,
                    self::STATUS_CLAIMED,
                    $resumeAuditKey,
                    $claimHash,
                    [
                        'origin_claim_access_id' => (int) $originalClaim->id,
                        'crash_recovery' => true,
                    ],
                    now(),
                    true,
                );
            } elseif ((int) $resume->envelope_id !== (int) $envelope->getKey()
                || (string) $resume->operation !== 'claim_resumed'
                || (string) $resume->consumer !== $consumer) {
                throw new EventWaitlistException('event_waitlist_offer_envelope_conflict');
            }
            $envelope->refresh();

            return new EventWaitlistOfferEnvelopeClaim(
                $envelope,
                $offerToken,
                $claimToken,
            );
        }, 5);
    }

    public function completeHandoff(
        int $envelopeId,
        string $claimToken,
        string $consumer,
        string $idempotencyKey,
    ): EventWaitlistOfferEnvelope {
        $tenantId = $this->tenantId();
        $consumer = $this->consumer($consumer);
        $claimToken = trim($claimToken);
        if ($claimToken === '' || mb_strlen($claimToken) > 512) {
            throw new EventWaitlistException('event_waitlist_offer_envelope_claim_invalid');
        }
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
        ): EventWaitlistOfferEnvelope {
            $replay = DB::table('event_waitlist_offer_envelope_access')
                ->where('tenant_id', $tenantId)
                ->where('idempotency_key', $auditKey)
                ->lockForUpdate()
                ->first();
            if ($replay !== null) {
                /** @var EventWaitlistOfferEnvelope|null $envelope */
                $envelope = EventWaitlistOfferEnvelope::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->whereKey((int) $replay->envelope_id)
                    ->lockForUpdate()
                    ->first();
                if ($envelope === null
                    || (int) $envelope->getKey() !== $envelopeId
                    || (string) $replay->operation !== 'handed_off') {
                    throw new EventWaitlistException('event_waitlist_offer_envelope_conflict');
                }

                return $envelope;
            }

            /** @var EventWaitlistOfferEnvelope|null $envelope */
            $envelope = EventWaitlistOfferEnvelope::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereKey($envelopeId)
                ->lockForUpdate()
                ->first();
            if ($envelope === null) {
                throw new EventWaitlistException('event_waitlist_offer_envelope_not_found');
            }
            $storedClaimHash = $envelope->getRawOriginal('claim_token_hash');
            if ((string) $envelope->status !== self::STATUS_CLAIMED
                || (string) $envelope->claimed_by !== $consumer
                || ! is_string($storedClaimHash)
                || ! hash_equals($storedClaimHash, hash('sha256', $claimToken))) {
                throw new EventWaitlistException('event_waitlist_offer_envelope_claim_invalid');
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
                true,
            );
            $envelope->refresh();

            return $envelope;
        }, 5);
    }

    /** Caller must hold the waitlist entry/event transaction locks. */
    public function eraseForTerminalEntry(
        EventWaitlistEntry $entry,
        EventWaitlistQueueState $terminalState,
        Carbon $now,
    ): ?EventWaitlistOfferEnvelope {
        $this->assertTransaction();
        $tenantId = $this->tenantId();
        if ((int) $entry->tenant_id !== $tenantId
            || ! in_array($terminalState, [
                EventWaitlistQueueState::Accepted,
                EventWaitlistQueueState::Expired,
                EventWaitlistQueueState::Cancelled,
            ], true)) {
            throw new EventWaitlistException('event_waitlist_offer_envelope_scope_invalid');
        }

        /** @var EventWaitlistOfferEnvelope|null $envelope */
        $envelope = EventWaitlistOfferEnvelope::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('event_id', (int) $entry->event_id)
            ->where('waitlist_entry_id', (int) $entry->getKey())
            ->whereIn('status', [self::STATUS_SEALED, self::STATUS_CLAIMED])
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();
        if ($envelope === null) {
            return null;
        }

        $from = (string) $envelope->status;
        $to = $terminalState === EventWaitlistQueueState::Expired
            ? self::STATUS_EXPIRED
            : self::STATUS_ERASED;
        $claimHash = is_string($envelope->getRawOriginal('claim_token_hash'))
            ? $envelope->getRawOriginal('claim_token_hash')
            : null;
        $envelope->forceFill([
            'status' => $to,
            'envelope_version' => (int) $envelope->envelope_version + 1,
            'token_ciphertext' => null,
            'claim_token_hash' => null,
            'erased_at' => $now,
        ])->save();
        $this->recordAccess(
            $envelope,
            $to === self::STATUS_EXPIRED ? 'expired' : 'terminal_erased',
            'system',
            $from,
            $to,
            "terminal:{$entry->getKey()}:{$entry->queue_version}:{$terminalState->value}",
            is_string($claimHash) ? $claimHash : null,
            [
                'terminal_queue_state' => $terminalState->value,
                'ciphertext_erased' => true,
            ],
            $now,
        );
        $envelope->refresh();

        return $envelope;
    }

    private function decryptEnvelope(EventWaitlistOfferEnvelope $envelope): string
    {
        if ((string) $envelope->cipher_version !== self::CIPHER_VERSION) {
            throw new EventWaitlistException('event_waitlist_offer_envelope_cipher_unsupported');
        }
        [$key, $fingerprint] = $this->keyForVersion((string) $envelope->key_version);
        if (! hash_equals((string) $envelope->key_fingerprint, $fingerprint)) {
            throw new EventWaitlistException('event_waitlist_offer_envelope_key_unavailable');
        }
        $aad = $this->aad(
            (int) $envelope->tenant_id,
            (int) $envelope->event_id,
            (int) $envelope->waitlist_entry_id,
            (int) $envelope->outbox_id,
            (string) $envelope->action,
            (int) $envelope->queue_version,
            (string) $envelope->cipher_version,
            (string) $envelope->key_version,
        );
        if (! hash_equals((string) $envelope->aad_hash, hash('sha256', $aad))) {
            throw new EventWaitlistException('event_waitlist_offer_envelope_scope_invalid');
        }
        $ciphertext = $envelope->getRawOriginal('token_ciphertext');
        if (! is_string($ciphertext) || $ciphertext === '') {
            throw new EventWaitlistException('event_waitlist_offer_envelope_erased');
        }

        return $this->decrypt($ciphertext, $key, $aad);
    }

    private function assertOfferStillActive(
        EventWaitlistOfferEnvelope $envelope,
        string $offerToken,
    ): void {
        $entryTokenHash = DB::table('event_waitlist_entries')
            ->where('tenant_id', (int) $envelope->tenant_id)
            ->where('event_id', (int) $envelope->event_id)
            ->where('id', (int) $envelope->waitlist_entry_id)
            ->where('queue_state', EventWaitlistQueueState::Offered->value)
            ->value('offer_token_hash');
        if (! is_string($entryTokenHash)
            || ! hash_equals($entryTokenHash, hash('sha256', $offerToken))) {
            throw new EventWaitlistException('event_waitlist_offer_envelope_scope_invalid');
        }
    }

    private function recoverableClaimToken(
        EventWaitlistOfferEnvelope $envelope,
        string $claimAuditKey,
    ): string {
        [$key, $fingerprint] = $this->keyForVersion((string) $envelope->key_version);
        if (! hash_equals((string) $envelope->key_fingerprint, $fingerprint)) {
            throw new EventWaitlistException('event_waitlist_offer_envelope_key_unavailable');
        }

        return hash_hmac(
            'sha256',
            implode(':', [
                'event-waitlist-envelope-claim-v1',
                (string) $envelope->tenant_id,
                (string) $envelope->event_id,
                (string) $envelope->getKey(),
                $claimAuditKey,
            ]),
            $key,
        );
    }

    /** @return array{string,string,string} */
    private function activeKey(): array
    {
        $version = trim((string) config(
            'event_waitlist.envelope.active_key_version',
            'app-key-v1',
        ));
        if ($version === '' || mb_strlen($version) > 64) {
            throw new EventWaitlistException('event_waitlist_offer_envelope_key_unavailable');
        }
        [$key, $fingerprint] = $this->keyForVersion($version);

        return [$version, $key, $fingerprint];
    }

    /** @return array{string,string} */
    private function keyForVersion(string $version): array
    {
        $activeVersion = trim((string) config(
            'event_waitlist.envelope.active_key_version',
            'app-key-v1',
        ));
        $material = null;
        if ($version === $activeVersion) {
            $configured = config('event_waitlist.envelope.active_key');
            if (is_string($configured) && trim($configured) !== '') {
                $material = trim($configured);
            } elseif ((bool) config(
                'event_waitlist.envelope.fallback_to_app_key',
                true,
            )) {
                $appKey = config('app.key');
                $material = is_string($appKey) ? trim($appKey) : null;
            }
        } else {
            $previous = config('event_waitlist.envelope.previous_keys', []);
            $candidate = is_array($previous) ? ($previous[$version] ?? null) : null;
            $material = is_string($candidate) ? trim($candidate) : null;
        }

        $key = $this->normalizeKey($material);
        if ($key === null) {
            throw new EventWaitlistException('event_waitlist_offer_envelope_key_unavailable');
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
            throw new EventWaitlistException('event_waitlist_offer_envelope_cipher_unavailable');
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
            throw new EventWaitlistException('event_waitlist_offer_envelope_cipher_unavailable');
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
            throw new EventWaitlistException('event_waitlist_offer_envelope_cipher_unavailable');
        }
        try {
            $decoded = json_decode($payload, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new EventWaitlistException('event_waitlist_offer_envelope_ciphertext_invalid');
        }
        if (! is_array($decoded) || ($decoded['v'] ?? null) !== 1) {
            throw new EventWaitlistException('event_waitlist_offer_envelope_ciphertext_invalid');
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
            throw new EventWaitlistException('event_waitlist_offer_envelope_decryption_failed');
        }

        return $plaintext;
    }

    /** @param array<string,mixed> $payload */
    private function base64Field(array $payload, string $field, ?int $length = null): string
    {
        $encoded = $payload[$field] ?? null;
        $decoded = is_string($encoded) ? base64_decode($encoded, true) : false;
        if (! is_string($decoded) || ($length !== null && strlen($decoded) !== $length)) {
            throw new EventWaitlistException('event_waitlist_offer_envelope_ciphertext_invalid');
        }

        return $decoded;
    }

    private function aad(
        int $tenantId,
        int $eventId,
        int $entryId,
        int $outboxId,
        string $action,
        int $queueVersion,
        string $cipherVersion,
        string $keyVersion,
    ): string {
        return implode('|', [
            'event-waitlist-offer-envelope',
            'v1',
            "tenant={$tenantId}",
            "event={$eventId}",
            "entry={$entryId}",
            "outbox={$outboxId}",
            "action={$action}",
            "queue_version={$queueVersion}",
            "cipher={$cipherVersion}",
            "key={$keyVersion}",
        ]);
    }

    /** @param array<string,mixed> $metadata */
    private function recordAccess(
        EventWaitlistOfferEnvelope $envelope,
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
            return (int) DB::table('event_waitlist_offer_envelope_access')->insertGetId([
                'tenant_id' => $tenantId,
                'event_id' => (int) $envelope->event_id,
                'envelope_id' => (int) $envelope->getKey(),
                'waitlist_entry_id' => (int) $envelope->waitlist_entry_id,
                'outbox_id' => (int) $envelope->outbox_id,
                'queue_version' => (int) $envelope->queue_version,
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
                throw new EventWaitlistException('event_waitlist_offer_envelope_conflict');
            }
            throw $exception;
        }
    }

    private function consumer(string $consumer): string
    {
        $consumer = trim($consumer);
        if ($consumer === '' || mb_strlen($consumer) > 191
            || preg_match('/^[A-Za-z0-9._:-]+$/', $consumer) !== 1) {
            throw new EventWaitlistException('event_waitlist_offer_envelope_consumer_invalid');
        }

        return $consumer;
    }

    private function idempotencyKey(string $key, string $scope): string
    {
        $key = trim($key);
        if ($key === '' || mb_strlen($key) > 191) {
            throw new EventWaitlistException('event_waitlist_offer_envelope_idempotency_invalid');
        }

        return hash('sha256', "event-waitlist-envelope:v1:{$scope}:{$key}");
    }

    private function assertTransaction(): void
    {
        if (DB::transactionLevel() <= 0) {
            throw new EventWaitlistException('event_waitlist_offer_envelope_transaction_required');
        }
    }

    private function tenantId(): int
    {
        $tenantId = TenantContext::currentId();
        if ($tenantId === null || $tenantId <= 0) {
            throw new EventWaitlistException('event_waitlist_tenant_context_missing');
        }

        return $tenantId;
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

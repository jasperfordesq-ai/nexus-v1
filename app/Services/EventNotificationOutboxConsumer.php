<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Claim/retry mechanics for the disabled-by-default Event outbox consumer.
 * Delivery orchestration is deliberately not activated by this foundation.
 */
final class EventNotificationOutboxConsumer
{
    /** @return list<array<string,mixed>> */
    public function claimBatch(int $limit = 50, ?int $tenantId = null): array
    {
        if (!EventNotificationDeliveryModeResolver::consumerEnabled()) {
            return [];
        }

        $limit = max(1, min($limit, 100));
        if ($tenantId !== null && $tenantId <= 0) {
            return [];
        }
        $token = (string) Str::uuid();

        return DB::transaction(function () use ($limit, $token, $tenantId): array {
            $now = now();
            $candidates = DB::table('event_domain_outbox as candidate')
                ->where('candidate.status', 'pending')
                ->where('candidate.production_mode', 'outbox_authoritative')
                ->when($tenantId !== null, static fn ($query) => $query->where('candidate.tenant_id', $tenantId))
                ->where(function ($query) use ($now): void {
                    $query->whereNull('candidate.available_at')->orWhere('candidate.available_at', '<=', $now);
                })
                ->where(function ($query) use ($now): void {
                    $query->whereNull('candidate.next_attempt_at')->orWhere('candidate.next_attempt_at', '<=', $now);
                })
                ->whereNotExists(function (Builder $query): void {
                    $query->selectRaw('1')->from('event_domain_outbox as earlier');
                    EventNotificationOutboxScope::apply($query, 'earlier');
                    $query->whereColumn('earlier.tenant_id', 'candidate.tenant_id')
                        ->whereColumn('earlier.event_id', 'candidate.event_id')
                        ->where(function ($stream): void {
                            $stream->whereColumn('earlier.aggregate_stream', 'candidate.aggregate_stream')
                                // Expand/backfill compatibility: a row written
                                // before aggregate streams existed remains a
                                // conservative event-wide ordering barrier.
                                ->orWhere('earlier.aggregate_stream', 'event')
                                ->orWhereRaw("candidate.aggregate_stream = 'event'");
                        })
                        ->whereColumn('earlier.aggregate_version', '<', 'candidate.aggregate_version')
                        ->whereIn('earlier.status', ['pending', 'processing', 'dead_letter']);
                })
                ->orderBy('candidate.id');
            EventNotificationOutboxScope::apply($candidates, 'candidate');
            $ids = $candidates
                ->limit($limit)
                ->lockForUpdate()
                ->pluck('candidate.id')
                ->all();

            if ($ids === []) {
                return [];
            }

            $claim = DB::table('event_domain_outbox')
                ->whereIn('id', $ids)
                ->where('status', 'pending');
            EventNotificationOutboxScope::apply($claim);
            $claim->update([
                    'status' => 'processing',
                    'claim_token' => $token,
                    'claimed_at' => $now,
                    'attempts' => DB::raw('attempts + 1'),
                    'updated_at' => $now,
                ]);

            $claimed = DB::table('event_domain_outbox')
                ->where('claim_token', $token)
                ->where('status', 'processing');
            EventNotificationOutboxScope::apply($claimed);

            return $claimed
                ->orderBy('id')
                ->get()
                ->map(static fn (object $row): array => (array) $row)
                ->all();
        }, 3);
    }

    public function markProcessed(int $outboxId, string $claimToken): bool
    {
        $query = DB::table('event_domain_outbox')
            ->where('id', $outboxId)
            ->where('status', 'processing')
            ->where('claim_token', $claimToken);
        EventNotificationOutboxScope::apply($query);

        return $query->update([
                'status' => 'processed',
                'processed_at' => now(),
                'claim_token' => null,
                'claimed_at' => null,
                'last_error' => null,
                'updated_at' => now(),
            ]) === 1;
    }

    public function markFailed(int $outboxId, string $claimToken, string $error): bool
    {
        $query = DB::table('event_domain_outbox')
            ->where('id', $outboxId)
            ->where('status', 'processing')
            ->where('claim_token', $claimToken);
        EventNotificationOutboxScope::apply($query);
        $row = $query->first(['attempts']);
        if ($row === null) {
            return false;
        }

        $attempts = (int) $row->attempts;
        $maxAttempts = max(1, (int) config('events.notification_delivery.max_attempts', 5));
        $deadLetter = $attempts >= $maxAttempts;
        $base = max(1, (int) config('events.notification_delivery.base_retry_seconds', 60));
        $cap = max($base, (int) config('events.notification_delivery.max_retry_seconds', 3600));
        $retrySeconds = min($cap, $base * (2 ** max(0, $attempts - 1)));

        $update = DB::table('event_domain_outbox')
            ->where('id', $outboxId)
            ->where('status', 'processing')
            ->where('claim_token', $claimToken);
        EventNotificationOutboxScope::apply($update);

        return $update->update([
                'status' => $deadLetter ? 'dead_letter' : 'pending',
                'next_attempt_at' => $deadLetter ? null : now()->addSeconds($retrySeconds),
                'dead_lettered_at' => $deadLetter ? now() : null,
                'claim_token' => null,
                'claimed_at' => null,
                'last_error' => EventNotificationErrorSanitizer::sanitize($error),
                'updated_at' => now(),
            ]) === 1;
    }

    /** Explicit, audited operator replay; automatic processing never revives poison rows. */
    public function replayDeadLetter(
        int $outboxId,
        string $actor,
        string $reason,
        ?int $tenantId = null,
    ): bool {
        $actor = trim($actor);
        $rawReason = trim($reason);
        if ($outboxId <= 0 || $actor === '' || $rawReason === ''
            || mb_strlen($actor) > 191 || mb_strlen($rawReason) > 1000) {
            return false;
        }
        // The actor is audit evidence, not an error payload. Preserve an operator
        // email/service identity so a replay remains attributable; the generic
        // error sanitizer intentionally redacts email addresses and would destroy
        // that evidence. Reject control characters instead of rewriting identity.
        if (preg_match('/[\x00-\x1F\x7F]/u', $actor) === 1) {
            return false;
        }
        $reason = EventNotificationErrorSanitizer::sanitize($rawReason, 1000);

        return DB::transaction(function () use ($outboxId, $actor, $reason, $tenantId): bool {
            $query = DB::table('event_domain_outbox')
                ->where('id', $outboxId)
                ->where('status', 'dead_letter')
                ->lockForUpdate();
            EventNotificationOutboxScope::apply($query);
            if ($tenantId !== null) {
                $query->where('tenant_id', $tenantId);
            }
            $row = $query->first();
            if ($row === null) {
                return false;
            }

            $terminalDeliveryIds = DB::table('event_notification_deliveries')
                ->where('tenant_id', (int) $row->tenant_id)
                ->where('outbox_id', (int) $row->id)
                ->where('status', 'failed_terminal')
                ->lockForUpdate()
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();

            $update = DB::table('event_domain_outbox')
                ->where('id', $outboxId)
                ->where('tenant_id', (int) $row->tenant_id)
                ->where('status', 'dead_letter');
            EventNotificationOutboxScope::apply($update);

            $parentReset = $update->update([
                    'status' => 'pending',
                    'attempts' => 0,
                    'available_at' => now(),
                    'next_attempt_at' => now(),
                    'claim_token' => null,
                    'claimed_at' => null,
                    'dead_lettered_at' => null,
                    'last_error' => null,
                    'updated_at' => now(),
                ]);
            if ($parentReset !== 1) {
                throw new \LogicException('event_notification_outbox_replay_parent_reset_failed');
            }

            if ($terminalDeliveryIds !== []) {
                $childrenReset = DB::table('event_notification_deliveries')
                    ->where('tenant_id', (int) $row->tenant_id)
                    ->where('outbox_id', (int) $row->id)
                    ->whereIn('id', $terminalDeliveryIds)
                    ->where('status', 'failed_terminal')
                    ->update([
                        'status' => 'pending',
                        'attempts' => 0,
                        'claim_token' => null,
                        'claimed_at' => null,
                        'next_attempt_at' => null,
                        'dead_lettered_at' => null,
                        'last_error' => null,
                        'updated_at' => now(),
                    ]);
                if ($childrenReset !== count($terminalDeliveryIds)) {
                    throw new \LogicException('event_notification_outbox_replay_child_reset_failed');
                }
            }

            $auditInserted = DB::table('event_notification_outbox_replays')->insert([
                'tenant_id' => (int) $row->tenant_id,
                'outbox_id' => (int) $row->id,
                'requested_by' => $actor,
                'reason' => $reason,
                'previous_attempts' => (int) $row->attempts,
                'previous_error_fingerprint' => $row->last_error !== null
                    ? hash('sha256', (string) $row->last_error)
                    : null,
                'created_at' => now(),
            ]);
            if (! $auditInserted) {
                throw new \LogicException('event_notification_outbox_replay_audit_failed');
            }

            return true;
        }, 3);
    }

    public function releaseStaleClaims(?int $tenantId = null): int
    {
        if ($tenantId !== null && $tenantId <= 0) {
            return 0;
        }
        $minutes = max(1, (int) config('events.notification_delivery.stale_claim_minutes', 10));

        $query = DB::table('event_domain_outbox')
            ->where('status', 'processing')
            ->when($tenantId !== null, static fn ($candidate) => $candidate->where('tenant_id', $tenantId))
            ->where('claimed_at', '<', now()->subMinutes($minutes));
        EventNotificationOutboxScope::apply($query);

        return $query->update([
                'status' => 'pending',
                'claim_token' => null,
                'claimed_at' => null,
                'next_attempt_at' => now(),
                'last_error' => 'event_notification_stale_claim_released',
                'updated_at' => now(),
            ]);
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Core\TenantContext;
use App\Enums\EventAttendanceAction;
use App\Enums\EventCheckinCredentialStatus;
use App\Enums\EventCheckinDeviceStatus;
use App\Enums\EventOfflineSyncOutcome;
use App\Exceptions\EventAttendanceException;
use App\Exceptions\EventOfflineCheckinException;
use App\Models\EventCheckinCredential;
use App\Models\EventCheckinDevice;
use App\Models\EventOfflineSyncBatch;
use App\Models\EventOfflineSyncItem;
use App\Models\User;
use App\Support\Events\EventCheckinSecurity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Replays staged scans through the canonical attendance state machine.
 *
 * It never writes attendance directly and passes one stable idempotency key to
 * both attendance and decision ledgers, making a partial retry safe.
 */
final class EventOfflineCheckinProcessor
{
    public function __construct(
        private readonly EventOfflineCheckinSyncService $sync,
        private readonly EventAttendanceService $attendance,
    ) {
    }

    public function processBatch(int $batchId): EventOfflineSyncBatch
    {
        $tenantId = $this->tenantId();
        $claim = $this->sync->claimBatch($batchId);
        if ($claim === null) {
            return $this->batch($tenantId, $batchId);
        }

        $batch = $claim->batch;
        $actor = User::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereKey((int) $batch->submitted_by_user_id)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first();
        if (! $actor instanceof User) {
            $this->releaseQuietly((int) $batch->id, $claim->claimToken);
            throw new EventOfflineCheckinException('event_offline_decision_actor_invalid');
        }

        try {
            $deviceCode = $this->deviceRejectionCode($tenantId, $batch);
            $items = EventOfflineSyncItem::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('batch_id', (int) $batch->id)
                ->orderBy('item_position')
                ->get();
            foreach ($items as $item) {
                if (DB::table('event_offline_sync_decisions')
                    ->where('tenant_id', $tenantId)
                    ->where('item_id', (int) $item->id)
                    ->exists()) {
                    continue;
                }
                $this->processItem(
                    $batch,
                    $item,
                    $actor,
                    $claim->claimToken,
                    $deviceCode,
                );
            }

            return $this->batch($tenantId, (int) $batch->id);
        } catch (Throwable $exception) {
            $this->releaseQuietly((int) $batch->id, $claim->claimToken);
            throw $exception;
        }
    }

    private function processItem(
        EventOfflineSyncBatch $batch,
        EventOfflineSyncItem $item,
        User $actor,
        string $claimToken,
        ?string $deviceCode,
    ): void {
        $idempotencyKey = sprintf(
            'event-offline:%d:%s',
            (int) $batch->device_id,
            (string) $item->client_nonce,
        );
        if ($deviceCode !== null && $deviceCode !== 'manifest_rotated') {
            $this->reject(
                $batch,
                $item,
                $actor,
                $claimToken,
                $idempotencyKey,
                $deviceCode,
            );

            return;
        }

        $credentialCode = $this->credentialRejectionCode($batch, $item);
        if ($credentialCode !== null) {
            $this->reject(
                $batch,
                $item,
                $actor,
                $claimToken,
                $idempotencyKey,
                $credentialCode,
            );

            return;
        }
        if ($deviceCode !== null) {
            $this->reject(
                $batch,
                $item,
                $actor,
                $claimToken,
                $idempotencyKey,
                $deviceCode,
            );

            return;
        }

        try {
            $before = (int) $item->expected_attendance_version;
            $result = $this->attendance->transition(
                (int) $batch->event_id,
                (int) $item->user_id,
                EventAttendanceAction::from($item->operation->value),
                $actor,
                $before,
                $item->submitted_reason,
                $idempotencyKey,
            );
            if ($result->activityId === null) {
                throw new EventOfflineCheckinException(
                    'event_offline_attendance_evidence_missing',
                );
            }
            $this->sync->decide(
                (int) $batch->id,
                (int) $item->id,
                $claimToken,
                (int) $actor->id,
                EventOfflineSyncOutcome::Accepted,
                'attendance_applied',
                null,
                $before,
                (int) $result->attendance->attendance_version,
                $result->activityId,
                $idempotencyKey,
            );
        } catch (EventAttendanceException $exception) {
            $conflict = in_array($exception->reasonCode, [
                'event_attendance_version_conflict',
                'event_attendance_idempotency_conflict',
                'event_attendance_transition_invalid',
                'event_attendance_undo_unavailable',
                'event_attendance_history_conflict',
            ], true);
            $this->sync->decide(
                (int) $batch->id,
                (int) $item->id,
                $claimToken,
                (int) $actor->id,
                $conflict
                    ? EventOfflineSyncOutcome::Conflict
                    : EventOfflineSyncOutcome::Rejected,
                $exception->reasonCode,
                null,
                (int) $item->expected_attendance_version,
                null,
                null,
                $idempotencyKey,
            );
        }
    }

    private function reject(
        EventOfflineSyncBatch $batch,
        EventOfflineSyncItem $item,
        User $actor,
        string $claimToken,
        string $idempotencyKey,
        string $code,
    ): void {
        $this->sync->decide(
            (int) $batch->id,
            (int) $item->id,
            $claimToken,
            (int) $actor->id,
            EventOfflineSyncOutcome::Rejected,
            $code,
            null,
            (int) $item->expected_attendance_version,
            null,
            null,
            $idempotencyKey,
        );
    }

    private function deviceRejectionCode(
        int $tenantId,
        EventOfflineSyncBatch $batch,
    ): ?string {
        $device = EventCheckinDevice::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('event_id', (int) $batch->event_id)
            ->whereKey((int) $batch->device_id)
            ->first();
        if ($device === null || $device->status === EventCheckinDeviceStatus::Revoked) {
            return 'device_revoked';
        }
        if ($device->status !== EventCheckinDeviceStatus::Active
            || ! $device->expires_at->isFuture()) {
            return 'device_expired';
        }
        if ((int) $device->registered_by_user_id !== (int) $batch->submitted_by_user_id) {
            return 'device_actor_mismatch';
        }
        $manifestVersion = DB::table('events')
            ->where('tenant_id', $tenantId)
            ->where('id', (int) $batch->event_id)
            ->value('checkin_manifest_version');
        if (! is_numeric($manifestVersion)
            || (int) $manifestVersion !== (int) $batch->manifest_version) {
            return 'manifest_rotated';
        }

        return null;
    }

    private function credentialRejectionCode(
        EventOfflineSyncBatch $batch,
        EventOfflineSyncItem $item,
    ): ?string {
        if ($item->credential_id === null || $item->user_id === null
            || $item->registration_id === null) {
            return 'credential_invalid';
        }
        $credential = EventCheckinCredential::withoutGlobalScopes()
            ->where('tenant_id', (int) $batch->tenant_id)
            ->where('event_id', (int) $batch->event_id)
            ->whereKey((int) $item->credential_id)
            ->first();
        if ($credential === null
            || ! EventCheckinSecurity::matches(
                (string) $credential->token_hash,
                (string) $item->credential_hash_reference,
            )
            || (int) $credential->user_id !== (int) $item->user_id
            || (int) $credential->registration_id !== (int) $item->registration_id) {
            return 'credential_invalid';
        }

        return match ($credential->status) {
            EventCheckinCredentialStatus::Rotated => 'credential_rotated',
            EventCheckinCredentialStatus::Revoked => 'credential_revoked',
            EventCheckinCredentialStatus::Expired => 'credential_expired',
            EventCheckinCredentialStatus::Active => $credential->expires_at->isFuture()
                ? null
                : 'credential_expired',
        };
    }

    private function batch(int $tenantId, int $batchId): EventOfflineSyncBatch
    {
        $batch = EventOfflineSyncBatch::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereKey($batchId)
            ->first();
        if (! $batch instanceof EventOfflineSyncBatch) {
            throw new EventOfflineCheckinException('event_offline_batch_not_found');
        }

        return $batch;
    }

    private function releaseQuietly(int $batchId, string $claimToken): void
    {
        try {
            $this->sync->releaseClaim($batchId, $claimToken, 5);
        } catch (Throwable) {
            // A completed batch clears its claim; an expired lease is safely
            // reclaimed by claimBatch/claimNext on the next processor pass.
        }
    }

    private function tenantId(): int
    {
        $tenantId = TenantContext::currentId();
        if ($tenantId === null || $tenantId <= 0) {
            throw new EventOfflineCheckinException('event_checkin_tenant_context_missing');
        }

        return $tenantId;
    }
}

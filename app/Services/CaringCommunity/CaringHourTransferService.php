<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\CaringCommunity;

use App\Core\TenantContext;
use App\Events\TransactionCompleted;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

/**
 * CaringHourTransferService — cooperative-to-cooperative banked-hour transfer.
 *
 * Federation here is between cooperative tenants on the same multi-tenant
 * NEXUS install (same-platform federation). The signature contract is in
 * place so cross-platform HTTP transport can be added later without changing
 * the data model.
 *
 * Transfer lifecycle (rows in `caring_hour_transfers`):
 *  - Source row (role='source')      : pending → approved_by_source → sent → completed
 *  - Destination row (role='destination'): received → completed
 *  - Either side may go to 'rejected' (no funds movement).
 *
 * Wallet impact: both sides write a `transactions` row of type 'other' with
 * a description prefixed `[hour_transfer_out]` / `[hour_transfer_in]` and a
 * cross-tenant marker (`is_federated=1`, sender/receiver tenant ids set).
 *
 * TODO (cross-platform federation): when receiving cooperatives are on a
 * different NEXUS install, replace `deliverToDestination()` with an HTTP POST
 * to `/api/v2/federation/hour-transfer/inbound` carrying the same payload +
 * signature. Verify with `verifySignature()` using a per-pair shared secret
 * negotiated at partnership creation.
 */
class CaringHourTransferService
{
    public const ALGORITHM = 'HMAC-SHA256';

    private const STATUS_PENDING = 'pending';
    private const STATUS_APPROVED = 'approved_by_source';
    private const STATUS_SENT = 'sent';
    private const STATUS_RECEIVED = 'received';
    private const STATUS_COMPLETED = 'completed';
    private const STATUS_REJECTED = 'rejected';

    /**
     * Member at source tenant initiates a transfer to a member at destination tenant.
     *
     * @return array{transfer_id:int,status:string}
     */
    public function initiate(int $sourceMemberId, string $destinationTenantSlug, float $hours, string $reason): array
    {
        $sourceTenantId = (int) TenantContext::getId();
        $destinationTenantSlug = trim($destinationTenantSlug);

        if ($hours <= 0) {
            throw new InvalidArgumentException('Hours must be greater than zero.');
        }
        if (round($hours, 2) != $hours) {
            throw new InvalidArgumentException('Hours must have at most 2 decimal places.');
        }

        if ($destinationTenantSlug === '') {
            throw new InvalidArgumentException('Destination cooperative is required.');
        }

        // Resolve source member + their balance & email
        $sourceUser = DB::table('users')
            ->where('tenant_id', $sourceTenantId)
            ->where('id', $sourceMemberId)
            ->first(['id', 'email', 'balance']);

        if (!$sourceUser) {
            throw new RuntimeException('Source member not found.');
        }
        if ((float) $sourceUser->balance < $hours) {
            throw new RuntimeException('Insufficient banked hours.');
        }

        // Resolve destination tenant (must be a different tenant)
        $destinationTenant = DB::table('tenants')
            ->where('slug', $destinationTenantSlug)
            ->first(['id', 'slug', 'name']);

        if (!$destinationTenant) {
            throw new RuntimeException('Destination cooperative not found.');
        }
        if ((int) $destinationTenant->id === $sourceTenantId) {
            throw new InvalidArgumentException('Destination cooperative must be different from source.');
        }

        // Match by email — the destination tenant must have the same email registered
        $destinationUser = DB::table('users')
            ->where('tenant_id', $destinationTenant->id)
            ->where('email', $sourceUser->email)
            ->first(['id']);

        if (!$destinationUser) {
            throw new RuntimeException('No matching member at destination cooperative — register there first.');
        }

        $row = [
            'tenant_id'                => $sourceTenantId,
            'counterpart_tenant_slug'  => (string) $destinationTenant->slug,
            'role'                     => 'source',
            'member_user_id'           => $sourceMemberId,
            'counterpart_member_email' => (string) $sourceUser->email,
            'hours_transferred'        => round($hours, 2),
            'status'                   => self::STATUS_PENDING,
            'reason'                   => $reason !== '' ? $reason : null,
            'signature'                => null,
            'payload_json'             => null,
            'linked_transfer_id'       => null,
            'created_at'               => now(),
            'updated_at'               => now(),
        ];

        $transferId = (int) DB::table('caring_hour_transfers')->insertGetId($row);

        return [
            'transfer_id' => $transferId,
            'status'      => self::STATUS_PENDING,
        ];
    }

    /**
     * Source-tenant admin approves a pending transfer.
     *
     * Atomically: debits source wallet, signs payload, inserts destination
     * row, credits destination wallet, marks both rows completed.
     *
     * @return array{transfer_id:int,status:string,destination_transfer_id:int}
     */
    public function approveAtSource(int $transferId, int $approverUserId): array
    {
        $sourceTenantId = (int) TenantContext::getId();

        // Lock + verify the source row
        $transfer = DB::table('caring_hour_transfers')
            ->where('id', $transferId)
            ->where('tenant_id', $sourceTenantId)
            ->where('role', 'source')
            ->first();

        if (!$transfer) {
            throw new RuntimeException('Transfer not found.');
        }
        if ($transfer->status !== self::STATUS_PENDING) {
            throw new RuntimeException('Transfer is not pending and cannot be approved.');
        }

        // Resolve destination tenant + user from the persisted slug + email
        $destinationTenant = DB::table('tenants')
            ->where('slug', $transfer->counterpart_tenant_slug)
            ->first(['id', 'slug', 'name']);

        if (!$destinationTenant) {
            throw new RuntimeException('Destination cooperative no longer exists.');
        }

        $destinationUser = DB::table('users')
            ->where('tenant_id', $destinationTenant->id)
            ->where('email', $transfer->counterpart_member_email)
            ->first(['id', 'email']);

        if (!$destinationUser) {
            throw new RuntimeException('No matching destination member — they may have removed their account.');
        }

        $sourceTenantSlug = (string) (DB::table('tenants')
            ->where('id', $sourceTenantId)
            ->value('slug') ?? '');

        $hours = round((float) $transfer->hours_transferred, 2);

        // Build canonical payload + signature BEFORE the DB transaction so the
        // signature is deterministic and we can store it on both rows.
        $payload = [
            'source_tenant_slug'      => $sourceTenantSlug,
            'destination_tenant_slug' => (string) $transfer->counterpart_tenant_slug,
            'source_member_email'     => (string) $transfer->counterpart_member_email,
            'hours'                   => $hours,
            'reason'                  => (string) ($transfer->reason ?? ''),
            'transfer_id'             => $transferId,
            'generated_at'            => now()->toIso8601String(),
        ];
        $signature = $this->signPayload($payload, $this->sharedPlatformSecret());

        return DB::transaction(function () use (
            $transferId,
            $transfer,
            $sourceTenantId,
            $destinationTenant,
            $destinationUser,
            $hours,
            $payload,
            $signature,
            $approverUserId
        ): array {
            // Lock + re-check source row
            $locked = DB::table('caring_hour_transfers')
                ->where('id', $transferId)
                ->where('tenant_id', $sourceTenantId)
                ->lockForUpdate()
                ->first();
            if (!$locked || $locked->status !== self::STATUS_PENDING) {
                throw new RuntimeException('Transfer is no longer pending.');
            }

            // Lock the source user row
            $sourceUser = DB::table('users')
                ->where('id', $transfer->member_user_id)
                ->where('tenant_id', $sourceTenantId)
                ->lockForUpdate()
                ->first(['id', 'balance']);

            if (!$sourceUser) {
                throw new RuntimeException('Source member not found.');
            }
            if ((float) $sourceUser->balance < $hours) {
                throw new RuntimeException('Source member no longer has enough banked hours.');
            }

            // ── 1. Debit source wallet via transactions row ─────────────────
            $now = now();
            $sourceTxnId = DB::table('transactions')->insertGetId([
                'tenant_id'          => $sourceTenantId,
                'sender_id'          => $transfer->member_user_id,
                'receiver_id'        => $approverUserId, // book-keeping placeholder; admin approves
                'amount'             => $hours,
                'description'        => '[hour_transfer_out] ' . (string) ($transfer->reason ?? ''),
                'status'             => 'completed',
                'transaction_type'   => 'other',
                'is_federated'       => 1,
                'sender_tenant_id'   => $sourceTenantId,
                'receiver_tenant_id' => (int) $destinationTenant->id,
                'created_at'         => $now,
                'updated_at'         => $now,
            ]);

            DB::table('users')
                ->where('id', $transfer->member_user_id)
                ->where('tenant_id', $sourceTenantId)
                ->decrement('balance', $hours);

            // Mark source row approved + sent
            DB::table('caring_hour_transfers')
                ->where('id', $transferId)
                ->update([
                    'status'       => self::STATUS_SENT,
                    'signature'    => $signature,
                    'payload_json' => json_encode($payload),
                    'updated_at'   => $now,
                ]);

            // ── 2. Insert destination row & credit destination wallet ───────
            $destinationRowId = $this->deliverToDestination(
                payload: $payload,
                signature: $signature,
                destinationTenantId: (int) $destinationTenant->id,
                destinationUserId: (int) $destinationUser->id,
                sourceTransferId: $transferId,
                sourceTenantId: $sourceTenantId,
            );

            // ── 3. Mark source row completed + cross-link ───────────────────
            DB::table('caring_hour_transfers')
                ->where('id', $transferId)
                ->update([
                    'status'             => self::STATUS_COMPLETED,
                    'linked_transfer_id' => $destinationRowId,
                    'updated_at'         => now(),
                ]);

            // Best-effort event dispatch — do not fail the transfer on event errors
            try {
                /** @var Transaction|null $txnModel */
                $txnModel = Transaction::query()->find($sourceTxnId);
                $sender = User::query()->find((int) $transfer->member_user_id);
                $receiver = User::query()->find($approverUserId);
                if ($txnModel && $sender && $receiver) {
                    event(new TransactionCompleted($txnModel, $sender, $receiver, $sourceTenantId));
                }
            } catch (\Throwable $e) {
                Log::warning('[CaringHourTransfer] TransactionCompleted dispatch failed: ' . $e->getMessage());
            }

            return [
                'transfer_id'             => $transferId,
                'status'                  => self::STATUS_COMPLETED,
                'destination_transfer_id' => $destinationRowId,
            ];
        });
    }

    /**
     * Reject a pending transfer at source. No funds movement.
     */
    public function rejectAtSource(int $transferId, int $approverUserId, string $reason): void
    {
        $sourceTenantId = (int) TenantContext::getId();

        $row = DB::table('caring_hour_transfers')
            ->where('id', $transferId)
            ->where('tenant_id', $sourceTenantId)
            ->where('role', 'source')
            ->first();

        if (!$row) {
            throw new RuntimeException('Transfer not found.');
        }
        if ($row->status !== self::STATUS_PENDING) {
            throw new RuntimeException('Only pending transfers can be rejected.');
        }

        $reason = trim($reason);
        $appendedReason = $reason !== ''
            ? ((string) ($row->reason ?? '')) . "\n[rejected by admin #{$approverUserId}] " . $reason
            : ($row->reason ?? null);

        DB::table('caring_hour_transfers')
            ->where('id', $transferId)
            ->update([
                'status'     => self::STATUS_REJECTED,
                'reason'     => $appendedReason,
                'updated_at' => now(),
            ]);
    }

    /**
     * History of a member's transfers (as the source-side member).
     *
     * @return array<int,array<string,mixed>>
     */
    public function memberHistory(int $memberId): array
    {
        $tenantId = (int) TenantContext::getId();

        if (!Schema::hasTable('caring_hour_transfers')) {
            return [];
        }

        $rows = DB::table('caring_hour_transfers')
            ->where('tenant_id', $tenantId)
            ->where('member_user_id', $memberId)
            ->where('role', 'source')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return $rows->map(fn (object $r) => $this->formatTransferRow($r))->all();
    }

    /**
     * List transfers initiated by members of the current tenant that are still pending admin review.
     *
     * @return array<int,array<string,mixed>>
     */
    public function pendingAtSource(): array
    {
        $tenantId = (int) TenantContext::getId();

        if (!Schema::hasTable('caring_hour_transfers')) {
            return [];
        }

        $rows = DB::table('caring_hour_transfers as t')
            ->leftJoin('users as u', function ($j) {
                $j->on('u.id', '=', 't.member_user_id')
                  ->on('u.tenant_id', '=', 't.tenant_id');
            })
            ->where('t.tenant_id', $tenantId)
            ->where('t.role', 'source')
            ->where('t.status', self::STATUS_PENDING)
            ->orderBy('t.created_at')
            ->get([
                't.id', 't.member_user_id', 't.counterpart_tenant_slug',
                't.counterpart_member_email', 't.hours_transferred', 't.status',
                't.reason', 't.created_at',
                'u.first_name', 'u.last_name', 'u.email',
            ]);

        return $rows->map(function (object $r) {
            return [
                'id'                       => (int) $r->id,
                'member_user_id'           => (int) $r->member_user_id,
                'member_name'              => trim(((string) ($r->first_name ?? '')) . ' ' . ((string) ($r->last_name ?? ''))),
                'member_email'             => (string) ($r->email ?? ''),
                'destination_tenant_slug'  => (string) $r->counterpart_tenant_slug,
                'destination_member_email' => (string) $r->counterpart_member_email,
                'hours'                    => round((float) $r->hours_transferred, 2),
                'status'                   => (string) $r->status,
                'reason'                   => (string) ($r->reason ?? ''),
                'created_at'               => (string) $r->created_at,
            ];
        })->all();
    }

    /**
     * List transfers received at this tenant from other cooperatives in the last 90 days.
     *
     * @return array<int,array<string,mixed>>
     */
    public function recentAtDestination(): array
    {
        $tenantId = (int) TenantContext::getId();

        if (!Schema::hasTable('caring_hour_transfers')) {
            return [];
        }

        $rows = DB::table('caring_hour_transfers as t')
            ->leftJoin('users as u', function ($j) {
                $j->on('u.id', '=', 't.member_user_id')
                  ->on('u.tenant_id', '=', 't.tenant_id');
            })
            ->where('t.tenant_id', $tenantId)
            ->where('t.role', 'destination')
            ->where('t.created_at', '>=', now()->subDays(90))
            ->orderByDesc('t.created_at')
            ->limit(200)
            ->get([
                't.id', 't.member_user_id', 't.counterpart_tenant_slug',
                't.counterpart_member_email', 't.hours_transferred', 't.status',
                't.reason', 't.created_at',
                'u.first_name', 'u.last_name', 'u.email',
            ]);

        return $rows->map(function (object $r) {
            return [
                'id'                  => (int) $r->id,
                'member_user_id'      => (int) $r->member_user_id,
                'member_name'         => trim(((string) ($r->first_name ?? '')) . ' ' . ((string) ($r->last_name ?? ''))),
                'member_email'        => (string) ($r->email ?? ''),
                'source_tenant_slug'  => (string) $r->counterpart_tenant_slug,
                'hours'               => round((float) $r->hours_transferred, 2),
                'status'              => (string) $r->status,
                'reason'              => (string) ($r->reason ?? ''),
                'created_at'          => (string) $r->created_at,
            ];
        })->all();
    }

    /**
     * HMAC-SHA256 signature of the canonical JSON of the payload.
     */
    public function signPayload(array $payload, string $secret): string
    {
        $canonical = $this->canonicalJson($payload);
        return hash_hmac('sha256', $canonical, $secret);
    }

    /**
     * Verify that the signature matches the payload under the given shared secret.
     */
    public function verifySignature(array $payload, string $signature, string $sharedSecret): bool
    {
        if ($signature === '' || $sharedSecret === '') {
            return false;
        }
        $expected = $this->signPayload($payload, $sharedSecret);
        return hash_equals($expected, $signature);
    }

    /**
     * Canonical JSON for signing — recursively sorts associative array keys.
     */
    public function canonicalJson(array $payload): string
    {
        $sorted = $this->ksortRecursive($payload);
        return (string) json_encode($sorted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Same-platform federation shared secret.
     *
     * NOTE: For cross-platform federation this must be replaced with a
     * per-tenant-pair secret negotiated at partnership creation. We derive
     * from APP_KEY here so all tenants on one install can verify each other
     * deterministically without a separate setup step.
     */
    public function sharedPlatformSecret(): string
    {
        $key = (string) (config('app.key') ?? '');
        // Strip Laravel "base64:" prefix if present and re-hash to a stable hex
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7), true) ?: $key;
        }
        return hash('sha256', 'caring-hour-transfer:' . $key);
    }

    /**
     * Same-platform delivery: insert the destination-side row and credit the
     * destination user's wallet.  In a cross-platform world this is replaced
     * by an HTTP call carrying $payload + $signature.
     */
    private function deliverToDestination(
        array $payload,
        string $signature,
        int $destinationTenantId,
        int $destinationUserId,
        int $sourceTransferId,
        int $sourceTenantId,
    ): int {
        // Defensive verification — the signature MUST verify for the same
        // payload at delivery time.  This is the contract a remote receiver
        // would also check.
        if (!$this->verifySignature($payload, $signature, $this->sharedPlatformSecret())) {
            throw new RuntimeException('Transfer signature verification failed.');
        }

        $hours = round((float) $payload['hours'], 2);
        $now = now();

        // Insert destination row
        $destinationRowId = (int) DB::table('caring_hour_transfers')->insertGetId([
            'tenant_id'                => $destinationTenantId,
            'counterpart_tenant_slug'  => (string) $payload['source_tenant_slug'],
            'role'                     => 'destination',
            'member_user_id'           => $destinationUserId,
            'counterpart_member_email' => (string) $payload['source_member_email'],
            'hours_transferred'        => $hours,
            'status'                   => self::STATUS_RECEIVED,
            'reason'                   => (string) ($payload['reason'] ?? ''),
            'signature'                => $signature,
            'payload_json'             => json_encode($payload),
            'linked_transfer_id'       => $sourceTransferId,
            'created_at'               => $now,
            'updated_at'               => $now,
        ]);

        // Credit destination user's wallet via a transactions row
        DB::table('transactions')->insert([
            'tenant_id'          => $destinationTenantId,
            'sender_id'          => $destinationUserId, // self-as-placeholder; cross-tenant sender has no row in this tenant
            'receiver_id'        => $destinationUserId,
            'amount'             => $hours,
            'description'        => '[hour_transfer_in] from ' . (string) $payload['source_tenant_slug']
                . (($payload['reason'] ?? '') !== '' ? ' — ' . (string) $payload['reason'] : ''),
            'status'             => 'completed',
            'transaction_type'   => 'other',
            'is_federated'       => 1,
            'sender_tenant_id'   => $sourceTenantId,
            'receiver_tenant_id' => $destinationTenantId,
            'created_at'         => $now,
            'updated_at'         => $now,
        ]);

        DB::table('users')
            ->where('id', $destinationUserId)
            ->where('tenant_id', $destinationTenantId)
            ->increment('balance', $hours);

        // Promote destination row to completed
        DB::table('caring_hour_transfers')
            ->where('id', $destinationRowId)
            ->update([
                'status'     => self::STATUS_COMPLETED,
                'updated_at' => now(),
            ]);

        return $destinationRowId;
    }

    private function formatTransferRow(object $row): array
    {
        return [
            'id'                       => (int) $row->id,
            'destination_tenant_slug'  => (string) $row->counterpart_tenant_slug,
            'destination_member_email' => (string) $row->counterpart_member_email,
            'hours'                    => round((float) $row->hours_transferred, 2),
            'status'                   => (string) $row->status,
            'reason'                   => (string) ($row->reason ?? ''),
            'created_at'               => (string) $row->created_at,
        ];
    }

    /**
     * @param array<string,mixed> $array
     * @return array<string,mixed>
     */
    private function ksortRecursive(array $array): array
    {
        $isList = array_keys($array) === range(0, count($array) - 1);
        if (!$isList) {
            ksort($array);
        }
        foreach ($array as $k => $v) {
            if (is_array($v)) {
                $array[$k] = $this->ksortRecursive($v);
            }
        }
        return $array;
    }
}

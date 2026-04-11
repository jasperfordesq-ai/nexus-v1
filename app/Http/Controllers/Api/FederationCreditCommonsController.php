<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Services\CreditCommonsNodeService;
use App\Services\Protocols\CreditCommonsAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * FederationCreditCommonsController — Credit Commons-compatible endpoints.
 *
 * Implements the CC protocol's REST API so that CC nodes can query NEXUS
 * as a compatible node in the network. Endpoints match the CC OpenAPI spec
 * at https://gitlab.com/credit-commons/cc-php-lib/-/blob/0.9.x/docs/credit-commons-openapi3.yml
 *
 * Authentication: via FederationApiMiddleware (API key, HMAC, JWT, or OAuth2).
 *
 * Endpoints:
 *   GET    /api/v2/federation/cc/about                       — Node metadata
 *   GET    /api/v2/federation/cc/accounts                    — Account autocomplete
 *   GET    /api/v2/federation/cc/account                     — Trading stats (self or specified)
 *   GET    /api/v2/federation/cc/account/{acc_id}            — Trading stats by ID
 *   GET    /api/v2/federation/cc/account/history              — Balance history
 *   GET    /api/v2/federation/cc/account/history/{acc_id}     — Balance history by ID
 *   POST   /api/v2/federation/cc/transaction                 — Propose transaction
 *   GET    /api/v2/federation/cc/transactions                — List/filter transactions
 *   GET    /api/v2/federation/cc/transaction/{uuid}          — Single transaction
 *   PATCH  /api/v2/federation/cc/transaction/{uuid}/{state}  — Change transaction state
 *   GET    /api/v2/federation/cc/entries                     — List entries
 *   GET    /api/v2/federation/cc/entries/{uuid}              — Entries for a transaction
 *   GET    /api/v2/federation/cc/forms                       — Available workflows
 */
class FederationCreditCommonsController extends BaseApiController
{
    protected bool $isV2Api = true;

    // ─────────────────────────────────────────────────────────────────────────
    // GET /about — Node metadata
    // ─────────────────────────────────────────────────────────────────────────

    public function about(Request $request): JsonResponse
    {
        $tenantId = TenantContext::getId();

        $nodeConfig = DB::table('federation_cc_node_config')
            ->where('tenant_id', $tenantId)
            ->first();

        $nodeSlug = $nodeConfig->node_slug ?? $this->generateNodeSlug($tenantId);
        $since = $request->query('since');

        // Gather trading stats
        $txQuery = DB::table('transactions')->where('tenant_id', $tenantId);
        if ($since) {
            $txQuery->where('created_at', '>=', $since);
        }

        $stats = $txQuery->selectRaw('
            COUNT(*) as trades,
            COUNT(DISTINCT sender_id) + COUNT(DISTINCT receiver_id) as traders,
            COALESCE(SUM(amount), 0) as volume
        ')->first();

        $accountCount = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->count();

        return response()->json([
            'format' => $nodeConfig->currency_format ?? '<quantity> hours',
            'rate' => (float) ($nodeConfig->exchange_rate ?? 1.0),
            'absolute_path' => $nodeConfig
                ? [$nodeConfig->parent_node_slug ?? $nodeSlug, $nodeSlug]
                : [$nodeSlug],
            'validated_window' => (int) ($nodeConfig->validated_window ?? 300),
            'trades' => (int) ($stats->trades ?? 0),
            'traders' => (int) ($stats->traders ?? 0),
            'volume' => (float) ($stats->volume ?? 0),
            'accounts' => $accountCount,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /accounts — Account autocomplete
    // ─────────────────────────────────────────────────────────────────────────

    public function accounts(Request $request): JsonResponse
    {
        $tenantId = TenantContext::getId();
        $accPath = $request->query('acc_path', '');
        $limit = min((int) $request->query('limit', '10'), 50);

        $nodeSlug = $this->getNodeSlug($tenantId);

        $query = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active');

        if ($accPath) {
            // Strip node prefix if present
            $search = str_contains($accPath, '/') ? explode('/', $accPath, 2)[1] : $accPath;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'LIKE', "{$search}%")
                    ->orWhere('name', 'LIKE', "{$search}%");
            });
        }

        $users = $query->orderBy('username')
            ->limit($limit)
            ->get(['id', 'username', 'name']);

        // Return as array of account paths (CC format)
        $paths = $users->map(function ($user) use ($nodeSlug) {
            $slug = $user->username ?? "user-{$user->id}";
            return "{$nodeSlug}/{$slug}";
        })->all();

        return response()->json($paths);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /account — Trading stats (SummaryStats)
    // ─────────────────────────────────────────────────────────────────────────

    public function accountStats(Request $request, ?string $accId = null): JsonResponse
    {
        $tenantId = TenantContext::getId();
        $since = $request->query('since');

        // Resolve user
        $userId = $accId ? $this->resolveAccountId($accId, $tenantId) : null;

        if ($accId && !$userId) {
            return $this->ccError('UnresolvedAccountnameViolation', "Account '{$accId}' not found", 400);
        }

        $query = DB::table('transactions')
            ->where('tenant_id', $tenantId);

        if ($since) {
            $query->where('created_at', '>=', $since);
        }

        if ($userId) {
            $query->where(function ($q) use ($userId) {
                $q->where('sender_id', $userId)->orWhere('receiver_id', $userId);
            });
        }

        $stats = $query->selectRaw("
            COUNT(*) as trades,
            COALESCE(SUM(amount), 0) as volume,
            COALESCE(SUM(CASE WHEN receiver_id = ? THEN amount ELSE 0 END), 0) as gross_in,
            COALESCE(SUM(CASE WHEN sender_id = ? THEN amount ELSE 0 END), 0) as gross_out,
            COUNT(DISTINCT CASE WHEN sender_id = ? THEN receiver_id WHEN receiver_id = ? THEN sender_id END) as partners
        ", [$userId ?? 0, $userId ?? 0, $userId ?? 0, $userId ?? 0])->first();

        // Get balance
        $balance = 0.0;
        if ($userId) {
            $user = DB::table('users')->where('id', $userId)->first(['balance']);
            $balance = (float) ($user->balance ?? 0);
        }

        return response()->json([
            'balance' => $balance,
            'volume' => (float) $stats->volume,
            'gross_in' => (float) $stats->gross_in,
            'gross_out' => (float) $stats->gross_out,
            'partners' => (int) $stats->partners,
            'trades' => (int) $stats->trades,
            'entries' => (int) $stats->trades,  // We generate 1 entry per transaction
            'min' => 0,  // Would need historical tracking for accurate min/max
            'max' => 0,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /account/history — Balance history
    // ─────────────────────────────────────────────────────────────────────────

    public function accountHistory(Request $request, ?string $accId = null): JsonResponse
    {
        $tenantId = TenantContext::getId();
        $userId = $accId ? $this->resolveAccountId($accId, $tenantId) : null;

        if ($accId && !$userId) {
            return $this->ccError('UnresolvedAccountnameViolation', "Account '{$accId}' not found", 400);
        }

        if (!$userId) {
            return $this->ccError('MissingParameter', 'Account ID required for history', 400);
        }

        // Build balance history from transactions (running balance reconstruction)
        $transactions = DB::table('transactions')
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where(function ($q) use ($userId) {
                $q->where('sender_id', $userId)->orWhere('receiver_id', $userId);
            })
            ->orderBy('created_at')
            ->get(['sender_id', 'receiver_id', 'amount', 'created_at']);

        $history = [];
        $runningBalance = 0.0;
        $min = 0;
        $max = 0;

        foreach ($transactions as $tx) {
            if ((int) $tx->receiver_id === $userId) {
                $runningBalance += (float) $tx->amount;
            } else {
                $runningBalance -= (float) $tx->amount;
            }
            $history[$tx->created_at] = round($runningBalance, 2);
            $min = min($min, $runningBalance);
            $max = max($max, $runningBalance);
        }

        return response()->json([
            'data' => $history,
            'meta' => [
                'start' => $transactions->first()->created_at ?? null,
                'end' => $transactions->last()->created_at ?? null,
                'points' => count($history),
                'min' => (int) floor($min),
                'max' => (int) ceil($max),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /transaction — Propose a new transaction
    // ─────────────────────────────────────────────────────────────────────────

    public function createTransaction(Request $request): JsonResponse
    {
        $tenantId = TenantContext::getId();
        $payload = $request->json()->all();

        $payerPath = $payload['payer'] ?? null;
        $payeePath = $payload['payee'] ?? null;
        $quant = (float) ($payload['quant'] ?? 0);
        $description = $payload['description'] ?? '';
        $workflow = $payload['workflow'] ?? '0|PC-CE=';

        if (!$payerPath || !$payeePath || $quant <= 0) {
            return $this->ccError('MissingParameter', 'Required: payer, payee, quant > 0', 400);
        }

        // Amount bounds validation
        if ($quant > 999999.99) {
            return $this->ccError('MissingParameter', 'Amount exceeds maximum (999999.99)', 400);
        }

        $payerId = $this->resolveAccountId($payerPath, $tenantId);
        $payeeId = $this->resolveAccountId($payeePath, $tenantId);

        if (!$payerId) {
            return $this->ccError('UnresolvedAccountnameViolation', "Payer account not found", 400);
        }
        if (!$payeeId) {
            return $this->ccError('UnresolvedAccountnameViolation', "Payee account not found", 400);
        }

        $uuid = (string) Str::uuid();
        $nodeSlug = $this->getNodeSlug($tenantId);

        // Execute the transaction with pessimistic locking to prevent race conditions
        DB::beginTransaction();
        try {
            // Lock payer row and check balance atomically
            $updated = DB::update(
                "UPDATE users SET balance = balance - ? WHERE id = ? AND tenant_id = ? AND balance >= ?",
                [$quant, $payerId, $tenantId, $quant]
            );

            if ($updated === 0) {
                DB::rollBack();
                return $this->ccError('InsufficientBalance', 'Payer has insufficient balance', 400);
            }

            DB::update("UPDATE users SET balance = balance + ? WHERE id = ? AND tenant_id = ?",
                [$quant, $payeeId, $tenantId]);

            $txId = DB::table('transactions')->insertGetId([
                'tenant_id' => $tenantId,
                'sender_id' => $payerId,
                'receiver_id' => $payeeId,
                'amount' => $quant,
                'description' => $description,
                'status' => 'completed',
                'is_federated' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create CC entry
            DB::table('federation_cc_entries')->insert([
                'tenant_id' => $tenantId,
                'transaction_uuid' => $uuid,
                'federation_transaction_id' => null,
                'payer' => $this->toAccountPath($payerId, $tenantId),
                'payee' => $this->toAccountPath($payeeId, $tenantId),
                'quant' => $quant,
                'description' => $description,
                'state' => CreditCommonsAdapter::STATE_COMPLETED,
                'workflow' => $workflow,
                'written_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[FederationCC] Transaction failed', ['error' => $e->getMessage()]);
            return $this->ccError('Other', 'Transaction processing failed', 500);
        }

        $payerPath = $this->toAccountPath($payerId, $tenantId);
        $payeePath = $this->toAccountPath($payeeId, $tenantId);

        return response()->json([
            'data' => [
                'uuid' => $uuid,
                'written' => now()->format('Y-m-d'),
                'state' => CreditCommonsAdapter::STATE_COMPLETED,
                'workflow' => $workflow,
                'entries' => [
                    [
                        'payer' => $payerPath,
                        'payee' => $payeePath,
                        'quant' => $quant,
                        'description' => $description,
                    ],
                ],
            ],
            'meta' => [
                'transitions' => [],
            ],
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /transactions — List/filter transactions
    // ─────────────────────────────────────────────────────────────────────────

    public function transactions(Request $request): JsonResponse
    {
        $tenantId = TenantContext::getId();
        $limit = min((int) $request->query('limit', '25'), 100);
        $offset = max((int) $request->query('offset', '0'), 0);
        $since = $request->query('since');
        $until = $request->query('until');
        $states = $request->query('states');
        $involving = $request->query('involving');
        $payer = $request->query('payer');
        $payee = $request->query('payee');
        $sort = $request->query('sort', 'written');
        $dir = strtolower($request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $nodeSlug = $this->getNodeSlug($tenantId);

        $query = DB::table('transactions')
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed');

        if ($since) $query->where('created_at', '>=', $since);
        if ($until) $query->where('created_at', '<=', $until);

        if ($involving) {
            $involvingId = $this->resolveAccountId($involving, $tenantId);
            if ($involvingId) {
                $query->where(function ($q) use ($involvingId) {
                    $q->where('sender_id', $involvingId)->orWhere('receiver_id', $involvingId);
                });
            }
        }

        if ($payer) {
            $payerId = $this->resolveAccountId($payer, $tenantId);
            if ($payerId) $query->where('sender_id', $payerId);
        }

        if ($payee) {
            $payeeId = $this->resolveAccountId($payee, $tenantId);
            if ($payeeId) $query->where('receiver_id', $payeeId);
        }

        if ($states) {
            // CC states are single letters concatenated: "PC" = Pending + Completed
            $ccStates = str_split($states);
            $nexusStatuses = array_map(fn($s) => CreditCommonsAdapter::mapCcStateToNexus($s), $ccStates);
            $query->whereIn('status', array_unique($nexusStatuses));
        }

        $orderCol = match ($sort) {
            'written', 'created' => 'created_at',
            'amount', 'quant' => 'amount',
            default => 'created_at',
        };

        $total = $query->count();
        $txs = $query->orderBy($orderCol, $dir)
            ->offset($offset)
            ->limit($limit)
            ->get();

        $results = $txs->map(function ($tx) use ($tenantId, $nodeSlug) {
            return [
                'uuid' => Str::uuid()->toString(),  // Generate UUID for CC compatibility
                'written' => $tx->created_at ? substr($tx->created_at, 0, 10) : null,
                'state' => CreditCommonsAdapter::mapNexusStateToCc($tx->status ?? 'completed'),
                'workflow' => '0|PC-CE=',
                'entries' => [
                    [
                        'payer' => $this->toAccountPath((int) $tx->sender_id, $tenantId),
                        'payee' => $this->toAccountPath((int) $tx->receiver_id, $tenantId),
                        'quant' => (float) $tx->amount,
                        'description' => $tx->description ?? '',
                    ],
                ],
            ];
        })->all();

        $currentPage = ($offset / max($limit, 1)) + 1;

        return response()->json([
            'data' => $results,
            'meta' => [
                'number_of_results' => $total,
                'current_page' => (int) $currentPage,
            ],
            'links' => [
                'first' => "?offset=0&limit={$limit}",
                'last' => '?offset=' . max(0, $total - $limit) . "&limit={$limit}",
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /transaction/{uuid} — Single transaction
    // ─────────────────────────────────────────────────────────────────────────

    public function transaction(Request $request, string $uuid): JsonResponse
    {
        $tenantId = TenantContext::getId();

        // Check CC entries table first
        $entry = DB::table('federation_cc_entries')
            ->where('transaction_uuid', $uuid)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$entry) {
            return $this->ccError('DoesNotExist', "Transaction {$uuid} not found", 400);
        }

        $transitions = CreditCommonsAdapter::STATE_TRANSITIONS[$entry->state] ?? [];

        return response()->json([
            'data' => [
                'uuid' => $entry->transaction_uuid,
                'written' => $entry->written_at ? substr($entry->written_at, 0, 10) : null,
                'state' => $entry->state,
                'workflow' => $entry->workflow ?? '0|PC-CE=',
                'entries' => [
                    [
                        'payer' => $entry->payer,
                        'payee' => $entry->payee,
                        'quant' => (float) $entry->quant,
                        'description' => $entry->description ?? '',
                    ],
                ],
            ],
            'meta' => [
                'transitions' => [$uuid => $transitions],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PATCH /transaction/{uuid}/{state} — Change transaction state
    // ─────────────────────────────────────────────────────────────────────────

    public function transitionTransaction(Request $request, string $uuid, string $destState): JsonResponse
    {
        $tenantId = TenantContext::getId();
        $destState = strtoupper($destState);

        $entry = DB::table('federation_cc_entries')
            ->where('transaction_uuid', $uuid)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$entry) {
            return $this->ccError('DoesNotExist', "Transaction {$uuid} not found", 400);
        }

        if (!CreditCommonsAdapter::isValidTransition($entry->state, $destState)) {
            return $this->ccError('InvalidStateTransition',
                "Cannot transition from {$entry->state} to {$destState}", 400);
        }

        $updates = [
            'state' => $destState,
            'updated_at' => now(),
        ];

        // Handle state-specific side effects
        if ($destState === CreditCommonsAdapter::STATE_COMPLETED) {
            $updates['written_at'] = now();
        }

        if ($destState === CreditCommonsAdapter::STATE_ERASED) {
            // Reverse the balance changes if the transaction was completed
            if ($entry->state === CreditCommonsAdapter::STATE_COMPLETED && $entry->federation_transaction_id) {
                $this->reverseTransaction($entry, $tenantId);
            }
        }

        // Scrub = delete the record entirely
        if ($destState === CreditCommonsAdapter::STATE_SCRUBBED) {
            DB::table('federation_cc_entries')
                ->where('id', $entry->id)
                ->delete();

            return response()->json(null, 204);
        }

        DB::table('federation_cc_entries')
            ->where('id', $entry->id)
            ->update($updates);

        return response()->json([
            'data' => [
                'uuid' => $uuid,
                'state' => $destState,
            ],
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /entries — List entries (double-entry view)
    // ─────────────────────────────────────────────────────────────────────────

    public function entries(Request $request): JsonResponse
    {
        $tenantId = TenantContext::getId();
        $limit = min((int) $request->query('limit', '25'), 100);
        $offset = max((int) $request->query('offset', '0'), 0);
        $involving = $request->query('involving');
        $since = $request->query('since');
        $until = $request->query('until');

        $query = DB::table('federation_cc_entries')
            ->where('tenant_id', $tenantId);

        if ($involving) {
            $query->where(function ($q) use ($involving) {
                $q->where('payer', 'LIKE', "%{$involving}%")
                    ->orWhere('payee', 'LIKE', "%{$involving}%");
            });
        }

        if ($since) $query->where('created_at', '>=', $since);
        if ($until) $query->where('created_at', '<=', $until);

        $total = $query->count();
        $rows = $query->orderByDesc('created_at')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $results = $rows->map(function ($entry) {
            return [
                'uuid' => $entry->transaction_uuid,
                'payer' => $entry->payer,
                'payee' => $entry->payee,
                'quant' => (float) $entry->quant,
                'description' => $entry->description ?? '',
                'author' => $entry->author ?? $entry->payer,
                'written' => $entry->written_at,
                'workflow' => $entry->workflow ?? '0|PC-CE=',
                'state' => $entry->state,
                'metadata' => $entry->metadata ? json_decode($entry->metadata, true) : null,
            ];
        })->all();

        return response()->json([
            'data' => $results,
            'meta' => [
                'number_of_results' => $total,
                'current_page' => ($offset / max($limit, 1)) + 1,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /entries/{uuid} — Entries for a specific transaction
    // ─────────────────────────────────────────────────────────────────────────

    public function transactionEntries(Request $request, string $uuid): JsonResponse
    {
        $tenantId = TenantContext::getId();

        $rows = DB::table('federation_cc_entries')
            ->where('transaction_uuid', $uuid)
            ->where('tenant_id', $tenantId)
            ->get();

        if ($rows->isEmpty()) {
            return $this->ccError('DoesNotExist', "No entries for transaction {$uuid}", 400);
        }

        $results = $rows->map(function ($entry) {
            return [
                'uuid' => $entry->transaction_uuid,
                'payer' => $entry->payer,
                'payee' => $entry->payee,
                'quant' => (float) $entry->quant,
                'description' => $entry->description ?? '',
                'author' => $entry->author ?? $entry->payer,
                'written' => $entry->written_at,
                'workflow' => $entry->workflow ?? '0|PC-CE=',
                'state' => $entry->state,
                'metadata' => $entry->metadata ? json_decode($entry->metadata, true) : null,
            ];
        })->all();

        return response()->json([
            'data' => $results,
            'meta' => [
                'number_of_results' => count($results),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /forms — Available transaction workflows
    // ─────────────────────────────────────────────────────────────────────────

    public function forms(Request $request): JsonResponse
    {
        // NEXUS supports a simple payment workflow
        return response()->json([
            '0|PC-CE=' => [
                'label' => 'Standard Payment',
                'summary' => 'Direct payment from payer to payee, immediately completed',
                'code' => '0|PC-CE=',
                'labels' => [
                    'P' => 'Pending',
                    'C' => 'Completed',
                    'E' => 'Erased',
                ],
            ],
            '+|PPC-PE+CE-' => [
                'label' => 'Approved Payment',
                'summary' => 'Payment requires validation before completion',
                'code' => '+|PPC-PE+CE-',
                'labels' => [
                    'P' => 'Pending',
                    'V' => 'Validated',
                    'C' => 'Completed',
                    'E' => 'Erased',
                ],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /transaction/relay — Relay transaction through the node tree
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * POST /transaction/relay — Receive a relayed transaction from another CC node.
     *
     * CC relay rules:
     *   - If the payee is local, process the transaction locally
     *   - If the payee is remote, forward to the next node in the tree
     *   - Verify the Last-hash header for hashchain integrity
     */
    public function relayTransaction(Request $request): JsonResponse
    {
        $tenantId = TenantContext::getId();
        $payload = $request->json()->all();

        // Verify hashchain
        $remoteHash = $request->header('Last-hash');
        if (!CreditCommonsNodeService::verifyHash($remoteHash, $tenantId)) {
            return $this->ccError('HashMismatch',
                'Hashchain verification failed — last hashes do not match', 500);
        }

        $payerPath = $payload['payer'] ?? null;
        $payeePath = $payload['payee'] ?? null;
        $quant = (float) ($payload['quant'] ?? 0);
        $description = $payload['description'] ?? '';
        $workflow = $payload['workflow'] ?? '0|PC-CE=';

        if (!$payerPath || !$payeePath || $quant <= 0) {
            return $this->ccError('MissingParameter', 'Required: payer, payee, quant > 0', 400);
        }

        $payeeIsLocal = CreditCommonsNodeService::isLocalAccount($payeePath, $tenantId);

        if ($payeeIsLocal) {
            // Payee is on this node — process locally
            $payeeId = $this->resolveAccountId(
                CreditCommonsAdapter::extractUsername($payeePath), $tenantId
            );

            if (!$payeeId) {
                return $this->ccError('UnresolvedAccountnameViolation',
                    "Payee '{$payeePath}' not found on this node", 400);
            }

            $uuid = (string) Str::uuid();

            DB::beginTransaction();
            try {
                // Credit the local payee
                DB::update("UPDATE users SET balance = balance + ? WHERE id = ? AND tenant_id = ?",
                    [$quant, $payeeId, $tenantId]);

                // Record the CC entry
                DB::table('federation_cc_entries')->insert([
                    'tenant_id' => $tenantId,
                    'transaction_uuid' => $uuid,
                    'payer' => $payerPath,
                    'payee' => $payeePath,
                    'quant' => $quant,
                    'description' => $description,
                    'state' => CreditCommonsAdapter::STATE_COMPLETED,
                    'workflow' => $workflow,
                    'author' => $payerPath,
                    'written_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Advance hashchain
                $newHash = CreditCommonsNodeService::advanceHashchain(
                    $tenantId, $uuid, $quant, $payerPath, $payeePath
                );

                DB::commit();

                return response()->json([
                    'data' => [
                        'uuid' => $uuid,
                        'written' => now()->format('Y-m-d'),
                        'state' => CreditCommonsAdapter::STATE_COMPLETED,
                        'workflow' => $workflow,
                        'entries' => [[
                            'payer' => $payerPath,
                            'payee' => $payeePath,
                            'quant' => $quant,
                            'description' => $description,
                        ]],
                    ],
                    'meta' => ['transitions' => []],
                ], 201, ['Last-hash' => $newHash]);
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('[FederationCC] Relay local processing failed', ['error' => $e->getMessage()]);
                return $this->ccError('Other', 'Relay processing failed', 500);
            }
        }

        // Payee is remote — forward to the next node
        $relayResult = CreditCommonsNodeService::relayTransaction($payload, $tenantId);

        if (!$relayResult['success']) {
            return $this->ccError('UnavailableNode',
                $relayResult['error'] ?? 'Failed to relay transaction', 500);
        }

        // Record the relay in our entries for audit
        $uuid = $relayResult['data']['uuid'] ?? (string) Str::uuid();
        DB::table('federation_cc_entries')->insert([
            'tenant_id' => $tenantId,
            'transaction_uuid' => $uuid,
            'payer' => $payerPath,
            'payee' => $payeePath,
            'quant' => $quant,
            'description' => $description,
            'state' => CreditCommonsAdapter::STATE_COMPLETED,
            'workflow' => $workflow,
            'author' => $payerPath,
            'written_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Advance hashchain for the relay hop
        $newHash = CreditCommonsNodeService::advanceHashchain(
            $tenantId, $uuid, $quant, $payerPath, $payeePath
        );

        $responseData = $relayResult['data'] ?? [];
        return response()->json($responseData, 201, ['Last-hash' => $newHash]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build a CC error response (CCViolation or CCFailure format).
     */
    private function ccError(string $class, string $detail, int $status): JsonResponse
    {
        $type = $status >= 500 ? 'CCFailure' : 'CCViolation';
        $nodeSlug = $this->getNodeSlug(TenantContext::getId());

        return response()->json([
            'class' => $class,
            'node' => $nodeSlug,
            'detail' => $detail,
        ], $status);
    }

    /**
     * Get or generate the CC node slug for a tenant.
     */
    private function getNodeSlug(int $tenantId): string
    {
        $config = DB::table('federation_cc_node_config')
            ->where('tenant_id', $tenantId)
            ->value('node_slug');

        return $config ?: $this->generateNodeSlug($tenantId);
    }

    /**
     * Generate a CC-compatible node slug from the tenant.
     */
    private function generateNodeSlug(int $tenantId): string
    {
        $tenant = DB::table('tenants')->where('id', $tenantId)->first(['slug', 'name']);

        if ($tenant && $tenant->slug) {
            return Str::substr(Str::slug($tenant->slug), 0, 15);
        }

        if ($tenant && $tenant->name) {
            return Str::substr(Str::slug($tenant->name), 0, 15);
        }

        return "nexus-t{$tenantId}";
    }

    /**
     * Convert a user ID to a CC account path.
     */
    private function toAccountPath(int $userId, int $tenantId): string
    {
        $nodeSlug = $this->getNodeSlug($tenantId);

        $username = DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->value('username');

        return $nodeSlug . '/' . ($username ?? "user-{$userId}");
    }

    /**
     * Resolve a CC account path or ID to a NEXUS user ID.
     *
     * Accepts: "node-slug/username", "username", or numeric user ID.
     */
    private function resolveAccountId(string $accountRef, int $tenantId): ?int
    {
        // If numeric, treat as user ID
        if (is_numeric($accountRef)) {
            $exists = DB::table('users')
                ->where('id', (int) $accountRef)
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->exists();
            return $exists ? (int) $accountRef : null;
        }

        // Strip node prefix: "my-node/alice" → "alice"
        $username = str_contains($accountRef, '/')
            ? explode('/', $accountRef, 2)[1]
            : $accountRef;

        $user = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('username', $username)
            ->first(['id']);

        return $user ? (int) $user->id : null;
    }

    /**
     * Reverse a completed transaction's balance changes.
     */
    private function reverseTransaction(object $entry, int $tenantId): void
    {
        $payerId = $this->resolveAccountId($entry->payer, $tenantId);
        $payeeId = $this->resolveAccountId($entry->payee, $tenantId);

        if ($payerId && $payeeId) {
            DB::update("UPDATE users SET balance = balance + ? WHERE id = ? AND tenant_id = ?",
                [(float) $entry->quant, $payerId, $tenantId]);
            DB::update("UPDATE users SET balance = balance - ? WHERE id = ? AND tenant_id = ?",
                [(float) $entry->quant, $payeeId, $tenantId]);
        }
    }
}

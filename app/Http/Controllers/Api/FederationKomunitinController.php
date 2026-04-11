<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\FederationApiMiddleware;
use App\Core\TenantContext;
use App\Services\Protocols\KomunitinAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FederationKomunitinController — JSON:API-compatible endpoints for Komunitin
 * and other platforms that speak the JSON:API accounting protocol.
 *
 * These endpoints serve our data in JSON:API format so that Komunitin instances
 * can query NEXUS as a compatible federation partner.
 *
 * Authentication: via FederationApiMiddleware (API key, HMAC, or JWT).
 *
 * Endpoints:
 *   GET  /api/v2/federation/komunitin/currencies         — List currencies (we have 1: hours)
 *   GET  /api/v2/federation/komunitin/{code}/accounts     — List accounts (users with balances)
 *   GET  /api/v2/federation/komunitin/{code}/accounts/{id} — Single account
 *   GET  /api/v2/federation/komunitin/{code}/transfers    — List transfers (transactions)
 *   POST /api/v2/federation/komunitin/{code}/transfers    — Create transfer
 *   GET  /api/v2/federation/komunitin/{code}/transfers/{id} — Single transfer
 */
class FederationKomunitinController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /currencies — List available currencies.
     *
     * NEXUS timebanks use a single currency: hours.
     */
    public function currencies(Request $request): JsonResponse
    {
        $tenantId = TenantContext::getId();
        $tenant = DB::table('tenants')->where('id', $tenantId)->first();

        return $this->jsonApiResponse([
            [
                'type' => 'currencies',
                'id' => "hours-{$tenantId}",
                'attributes' => [
                    'name' => 'Hours',
                    'name_plural' => 'Hours',
                    'code' => 'HOURS',
                    'symbol' => 'h',
                    'decimals' => 2,
                    'scale' => KomunitinAdapter::MINOR_UNITS_PER_HOUR,
                    'value' => 0,
                    'stats' => [
                        'accounts' => DB::table('users')
                            ->where('tenant_id', $tenantId)
                            ->where('status', 'active')
                            ->count(),
                        'transfers' => DB::table('transactions')
                            ->where('tenant_id', $tenantId)
                            ->count(),
                    ],
                ],
            ],
        ]);
    }

    /**
     * GET /{code}/accounts — List accounts (federated-opted-in users).
     */
    public function accounts(Request $request, string $code): JsonResponse
    {
        $tenantId = TenantContext::getId();
        $limit = min((int) $request->query('page_size', '25'), 100);
        $offset = max((int) $request->query('page_offset', '0'), 0);
        $search = $request->query('filter_code');

        $query = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active');

        // Only include users who opted into federation (if the setting exists)
        if (DB::table('federation_user_settings')
            ->where('tenant_id', $tenantId)
            ->exists()) {
            $query->whereIn('id', function ($sub) use ($tenantId) {
                $sub->select('user_id')
                    ->from('federation_user_settings')
                    ->where('tenant_id', $tenantId)
                    ->where('federation_optin', 1);
            });
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('username', 'LIKE', "%{$search}%");
            });
        }

        $total = $query->count();
        $users = $query->orderBy('name')
            ->offset($offset)
            ->limit($limit)
            ->get(['id', 'name', 'username', 'balance', 'created_at']);

        $resources = $users->map(function ($user) use ($tenantId) {
            return [
                'type' => 'accounts',
                'id' => (string) $user->id,
                'attributes' => [
                    'code' => $user->username ?? "user-{$user->id}",
                    'balance' => KomunitinAdapter::hoursToMinorUnits((float) $user->balance),
                    'creditLimit' => KomunitinAdapter::hoursToMinorUnits(-100.0),
                    'debitLimit' => KomunitinAdapter::hoursToMinorUnits(100.0),
                    'created' => $user->created_at,
                ],
                'relationships' => [
                    'currency' => [
                        'data' => ['type' => 'currencies', 'id' => "hours-{$tenantId}"],
                    ],
                ],
            ];
        })->all();

        return $this->jsonApiResponse($resources, [
            'total' => $total,
            'offset' => $offset,
            'limit' => $limit,
        ]);
    }

    /**
     * GET /{code}/accounts/{id} — Single account.
     */
    public function account(Request $request, string $code, int $id): JsonResponse
    {
        $tenantId = TenantContext::getId();

        $user = DB::table('users')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->first(['id', 'name', 'username', 'balance', 'created_at']);

        if (!$user) {
            return $this->jsonApiError('Account not found', 404);
        }

        return $this->jsonApiResponse([
            'type' => 'accounts',
            'id' => (string) $user->id,
            'attributes' => [
                'code' => $user->username ?? "user-{$user->id}",
                'balance' => KomunitinAdapter::hoursToMinorUnits((float) $user->balance),
                'creditLimit' => KomunitinAdapter::hoursToMinorUnits(-100.0),
                'debitLimit' => KomunitinAdapter::hoursToMinorUnits(100.0),
                'created' => $user->created_at,
            ],
            'relationships' => [
                'currency' => [
                    'data' => ['type' => 'currencies', 'id' => "hours-{$tenantId}"],
                ],
            ],
        ], null, true);
    }

    /**
     * GET /{code}/transfers — List transfers (transactions).
     */
    public function transfers(Request $request, string $code): JsonResponse
    {
        $tenantId = TenantContext::getId();
        $limit = min((int) $request->query('page_size', '25'), 100);
        $offset = max((int) $request->query('page_offset', '0'), 0);

        // Filter options (JSON:API style)
        $account = $request->query('filter_account');
        $state = $request->query('filter_state');
        $since = $request->query('filter_after');
        $until = $request->query('filter_before');

        $query = DB::table('transactions')
            ->where('tenant_id', $tenantId);

        if ($account) {
            $query->where(function ($q) use ($account) {
                $q->where('sender_id', $account)
                    ->orWhere('receiver_id', $account);
            });
        }

        if ($state) {
            $nexusStatus = match ($state) {
                'committed', 'accepted' => 'completed',
                'pending' => 'pending',
                'rejected', 'deleted' => 'cancelled',
                default => null,
            };
            if ($nexusStatus) {
                $query->where('status', $nexusStatus);
            }
        }

        if ($since) {
            $query->where('created_at', '>=', $since);
        }
        if ($until) {
            $query->where('created_at', '<=', $until);
        }

        $total = $query->count();
        $transactions = $query->orderByDesc('created_at')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $resources = $transactions->map(function ($tx) use ($tenantId) {
            return [
                'type' => 'transfers',
                'id' => (string) $tx->id,
                'attributes' => [
                    'amount' => KomunitinAdapter::hoursToMinorUnits((float) $tx->amount),
                    'meta' => $tx->description ?? '',
                    'state' => match ($tx->status ?? 'pending') {
                        'completed' => 'committed',
                        'pending' => 'pending',
                        'cancelled' => 'rejected',
                        default => 'pending',
                    },
                    'created' => $tx->created_at,
                    'updated' => $tx->updated_at ?? $tx->created_at,
                ],
                'relationships' => [
                    'payer' => [
                        'data' => ['type' => 'accounts', 'id' => (string) $tx->sender_id],
                    ],
                    'payee' => [
                        'data' => ['type' => 'accounts', 'id' => (string) $tx->receiver_id],
                    ],
                    'currency' => [
                        'data' => ['type' => 'currencies', 'id' => "hours-{$tenantId}"],
                    ],
                ],
            ];
        })->all();

        return $this->jsonApiResponse($resources, [
            'total' => $total,
            'offset' => $offset,
            'limit' => $limit,
        ]);
    }

    /**
     * GET /{code}/transfers/{id} — Single transfer.
     */
    public function transfer(Request $request, string $code, int $id): JsonResponse
    {
        $tenantId = TenantContext::getId();

        $tx = DB::table('transactions')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$tx) {
            return $this->jsonApiError('Transfer not found', 404);
        }

        return $this->jsonApiResponse([
            'type' => 'transfers',
            'id' => (string) $tx->id,
            'attributes' => [
                'amount' => KomunitinAdapter::hoursToMinorUnits((float) $tx->amount),
                'meta' => $tx->description ?? '',
                'state' => match ($tx->status ?? 'pending') {
                    'completed' => 'committed',
                    'pending' => 'pending',
                    'cancelled' => 'rejected',
                    default => 'pending',
                },
                'created' => $tx->created_at,
                'updated' => $tx->updated_at ?? $tx->created_at,
            ],
            'relationships' => [
                'payer' => [
                    'data' => ['type' => 'accounts', 'id' => (string) $tx->sender_id],
                ],
                'payee' => [
                    'data' => ['type' => 'accounts', 'id' => (string) $tx->receiver_id],
                ],
                'currency' => [
                    'data' => ['type' => 'currencies', 'id' => "hours-{$tenantId}"],
                ],
            ],
        ], null, true);
    }

    /**
     * POST /{code}/transfers — Create a new transfer.
     *
     * Expects JSON:API format:
     * {
     *   "data": {
     *     "type": "transfers",
     *     "attributes": { "amount": 200, "meta": "description" },
     *     "relationships": {
     *       "payer": { "data": { "type": "accounts", "id": "123" } },
     *       "payee": { "data": { "type": "accounts", "id": "456" } }
     *     }
     *   }
     * }
     */
    public function createTransfer(Request $request, string $code): JsonResponse
    {
        $tenantId = TenantContext::getId();
        $payload = $request->json()->all();

        $data = $payload['data'] ?? [];
        $attrs = $data['attributes'] ?? [];
        $rels = $data['relationships'] ?? [];

        $amount = KomunitinAdapter::minorUnitsToHours((int) ($attrs['amount'] ?? 0));
        $description = $attrs['meta'] ?? $attrs['description'] ?? '';
        $payerId = $rels['payer']['data']['id'] ?? null;
        $payeeId = $rels['payee']['data']['id'] ?? null;

        if (!$payerId || !$payeeId || $amount <= 0) {
            return $this->jsonApiError('Missing required fields: payer, payee, and amount > 0', 400);
        }

        // Validate both accounts exist in this tenant
        $payer = DB::table('users')
            ->where('id', (int) $payerId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->first();

        $payee = DB::table('users')
            ->where('id', (int) $payeeId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->first();

        if (!$payer) {
            return $this->jsonApiError("Payer account {$payerId} not found", 404);
        }
        if (!$payee) {
            return $this->jsonApiError("Payee account {$payeeId} not found", 404);
        }

        // Check balance
        if ((float) $payer->balance < $amount) {
            return $this->jsonApiError('Insufficient balance', 422);
        }

        // Execute transfer
        DB::beginTransaction();
        try {
            DB::update("UPDATE users SET balance = balance - ? WHERE id = ? AND tenant_id = ?",
                [$amount, (int) $payerId, $tenantId]);
            DB::update("UPDATE users SET balance = balance + ? WHERE id = ? AND tenant_id = ?",
                [$amount, (int) $payeeId, $tenantId]);

            $txId = DB::table('transactions')->insertGetId([
                'tenant_id' => $tenantId,
                'sender_id' => (int) $payerId,
                'receiver_id' => (int) $payeeId,
                'amount' => $amount,
                'description' => $description,
                'status' => 'completed',
                'is_federated' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[FederationKomunitin] Transfer failed', ['error' => $e->getMessage()]);
            return $this->jsonApiError('Transfer failed', 500);
        }

        return $this->jsonApiResponse([
            'type' => 'transfers',
            'id' => (string) $txId,
            'attributes' => [
                'amount' => KomunitinAdapter::hoursToMinorUnits($amount),
                'meta' => $description,
                'state' => 'committed',
                'created' => now()->toIso8601String(),
                'updated' => now()->toIso8601String(),
            ],
            'relationships' => [
                'payer' => ['data' => ['type' => 'accounts', 'id' => $payerId]],
                'payee' => ['data' => ['type' => 'accounts', 'id' => $payeeId]],
                'currency' => ['data' => ['type' => 'currencies', 'id' => "hours-{$tenantId}"]],
            ],
        ], null, true, 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // JSON:API response helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build a JSON:API-compliant response.
     *
     * @param array      $data       JSON:API resource(s) — single object or array of objects
     * @param array|null $meta       Optional meta object
     * @param bool       $isSingle   Whether $data is a single resource (not wrapped in array)
     * @param int        $status     HTTP status code
     */
    private function jsonApiResponse(array $data, ?array $meta = null, bool $isSingle = false, int $status = 200): JsonResponse
    {
        $response = ['data' => $isSingle ? $data : array_values($data)];

        if ($meta) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $status, [
            'Content-Type' => 'application/vnd.api+json',
            'API-Version' => '2.0',
        ]);
    }

    /**
     * Build a JSON:API-compliant error response.
     */
    private function jsonApiError(string $detail, int $status = 400): JsonResponse
    {
        return response()->json([
            'errors' => [
                [
                    'status' => (string) $status,
                    'title' => match ($status) {
                        400 => 'Bad Request',
                        401 => 'Unauthorized',
                        404 => 'Not Found',
                        422 => 'Unprocessable Entity',
                        500 => 'Internal Server Error',
                        default => 'Error',
                    },
                    'detail' => $detail,
                ],
            ],
        ], $status, [
            'Content-Type' => 'application/vnd.api+json',
        ]);
    }
}

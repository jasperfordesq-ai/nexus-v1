<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Services\Protocols\KomunitinAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * FederationKomunitinController — JSON:API-compatible endpoints matching the
 * Komunitin accounting API specification (OpenAPI 3.0).
 *
 * Spec: https://raw.githubusercontent.com/komunitin/komunitin/refs/heads/master/accounting/openapi/openapi_v3.json
 * Repo: https://github.com/community-exchange-network/komunitin
 *
 * These endpoints serve NEXUS data in JSON:API format (application/vnd.api+json)
 * so that Komunitin instances can query NEXUS as a compatible federation partner.
 *
 * Key spec details matched:
 *   - JSON:API resource format with type/id/attributes/relationships/links
 *   - Cursor-based pagination via page[size] and page[after]
 *   - Error format: {errors: [{status, code, title, detail}]}
 *   - Transfer states: committed, pending, rejected
 *   - Amount in minor currency units (scale-based)
 *   - Currency rate as {n, d} (numerator/denominator)
 *   - links.self on every resource
 *   - PATCH for state changes, DELETE for removal
 *
 * Authentication: via FederationApiMiddleware (API key, HMAC, JWT, or OAuth2).
 *
 * Endpoints:
 *   GET    /currencies                  — List currencies
 *   GET    /{code}/currency             — Single currency
 *   GET    /{code}/currency/settings    — Currency settings
 *   GET    /{code}/accounts             — List accounts
 *   GET    /{code}/accounts/{id}        — Single account
 *   GET    /{code}/transfers            — List transfers
 *   GET    /{code}/transfers/{id}       — Single transfer
 *   POST   /{code}/transfers            — Create transfer
 *   PATCH  /{code}/transfers/{id}       — Update transfer state
 *   DELETE /{code}/transfers/{id}       — Delete transfer
 */
class FederationKomunitinController extends BaseApiController
{
    protected bool $isV2Api = true;

    // ─────────────────────────────────────────────────────────────────────────
    // Currencies
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /currencies — List available currencies.
     */
    public function currencies(Request $request): JsonResponse
    {
        $tenantId = TenantContext::getId();

        return $this->jsonApiResponse([
            $this->buildCurrencyResource($tenantId),
        ]);
    }

    /**
     * GET /{code}/currency — Single currency detail.
     */
    public function currency(Request $request, string $code): JsonResponse
    {
        $tenantId = TenantContext::getId();
        return $this->jsonApiResponse($this->buildCurrencyResource($tenantId), null, true);
    }

    /**
     * GET /{code}/currency/settings — Currency settings.
     */
    public function currencySettings(Request $request, string $code): JsonResponse
    {
        $tenantId = TenantContext::getId();

        return $this->jsonApiResponse([
            'type' => 'currency-settings',
            'id' => "hours-{$tenantId}-settings",
            'attributes' => [
                'defaultAllowPayments' => true,
                'enableExternalPayments' => true,
                'defaultAllowTagPayments' => false,
                'defaultInitialCreditLimit' => KomunitinAdapter::hoursToMinorUnits(0),
                'externalTraderCreditLimit' => KomunitinAdapter::hoursToMinorUnits(0),
                'defaultAcceptPaymentsAfter' => 0,
                'defaultAllowPaymentRequests' => true,
                'defaultOnPaymentCreditLimit' => KomunitinAdapter::hoursToMinorUnits(0),
                'defaultAllowExternalPayments' => true,
                'enableExternalPaymentRequests' => true,
                'defaultAcceptPaymentsWhitelist' => [],
                'defaultAllowTagPaymentRequests' => false,
                'defaultAcceptPaymentsAutomatically' => true,
                'defaultAllowExternalPaymentRequests' => true,
                'defaultAcceptExternalPaymentsAutomatically' => true,
            ],
        ], null, true);
    }

    /**
     * POST /currencies — Create a currency (stub).
     *
     * NEXUS timebanks use a fixed 'hours' currency. This endpoint accepts the
     * request for protocol compliance but returns the existing currency.
     */
    public function createCurrency(Request $request): JsonResponse
    {
        $tenantId = TenantContext::getId();

        // NEXUS doesn't support multiple currencies per tenant — return existing
        return $this->jsonApiResponse(
            $this->buildCurrencyResource($tenantId),
            null, true, 201
        );
    }

    /**
     * PATCH /{code}/currency — Update currency metadata.
     *
     * Accepts name/namePlural updates. Currency code is immutable per Komunitin spec.
     */
    public function updateCurrency(Request $request, string $code): JsonResponse
    {
        $tenantId = TenantContext::getId();
        $payload = $request->json()->all();
        $attrs = $payload['data']['attributes'] ?? [];

        // Reject currency code changes (Komunitin spec: "Can't change currency code")
        if (isset($attrs['code'])) {
            return $this->jsonApiError('BadRequest', 'Bad Request',
                "Can't change currency code", 400);
        }

        // NEXUS uses a fixed currency — accept the request but no-op
        return $this->jsonApiResponse(
            $this->buildCurrencyResource($tenantId),
            null, true
        );
    }

    /**
     * PATCH /{code}/currency/settings — Update currency settings.
     */
    public function updateCurrencySettings(Request $request, string $code): JsonResponse
    {
        $tenantId = TenantContext::getId();
        $payload = $request->json()->all();
        $attrs = $payload['data']['attributes'] ?? [];

        // NEXUS doesn't persist per-currency settings externally — accept and return current
        return $this->jsonApiResponse([
            'type' => 'currency-settings',
            'id' => "hours-{$tenantId}-settings",
            'attributes' => array_merge([
                'defaultAllowPayments' => true,
                'enableExternalPayments' => true,
                'defaultAllowTagPayments' => false,
                'defaultInitialCreditLimit' => KomunitinAdapter::hoursToMinorUnits(0),
                'externalTraderCreditLimit' => KomunitinAdapter::hoursToMinorUnits(0),
                'defaultAcceptPaymentsAfter' => 0,
                'defaultAllowPaymentRequests' => true,
                'defaultOnPaymentCreditLimit' => KomunitinAdapter::hoursToMinorUnits(0),
                'defaultAllowExternalPayments' => true,
                'enableExternalPaymentRequests' => true,
                'defaultAcceptPaymentsWhitelist' => [],
                'defaultAllowTagPaymentRequests' => false,
                'defaultAcceptPaymentsAutomatically' => true,
                'defaultAllowExternalPaymentRequests' => true,
                'defaultAcceptExternalPaymentsAutomatically' => true,
            ], $attrs),
        ], null, true);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Accounts
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /{code}/accounts — List accounts with cursor pagination.
     *
     * Pagination: page[size] (default 25), page[after] (cursor = last ID seen)
     * Filter: filter[code] for autocomplete search
     */
    public function accounts(Request $request, string $code): JsonResponse
    {
        $tenantId = TenantContext::getId();
        $pageSize = min((int) ($request->query('page')['size'] ?? $request->query('page_size', '25')), 100);
        $afterCursor = $request->query('page')['after'] ?? $request->query('page_after');
        $filterCode = $request->query('filter')['code'] ?? $request->query('filter_code');
        $filterTag = $request->query('filter')['tag'] ?? $request->query('filter_tag');
        $baseUrl = $request->getSchemeAndHttpHost();

        $query = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active');

        // Federation opt-in filter
        if (DB::table('federation_user_settings')
            ->where('tenant_id', $tenantId)->exists()) {
            $query->whereIn('id', function ($sub) use ($tenantId) {
                $sub->select('user_id')
                    ->from('federation_user_settings')
                    ->where('tenant_id', $tenantId)
                    ->where('federation_optin', 1);
            });
        }

        if ($filterCode) {
            $query->where(function ($q) use ($filterCode) {
                $q->where('username', 'LIKE', "%{$filterCode}%")
                    ->orWhere('name', 'LIKE', "%{$filterCode}%");
            });
        }

        // Cursor pagination: page[after] = offset index
        $offset = $afterCursor ? (int) $afterCursor : 0;

        $users = $query->orderBy('id')
            ->offset($offset)
            ->limit($pageSize + 1) // Fetch one extra to check for next page
            ->get(['id', 'name', 'username', 'balance', 'created_at', 'updated_at']);

        $hasNext = $users->count() > $pageSize;
        if ($hasNext) {
            $users = $users->slice(0, $pageSize);
        }

        $resources = $users->map(function ($user) use ($tenantId, $code, $baseUrl) {
            return $this->buildAccountResource($user, $tenantId, $code, $baseUrl);
        })->values()->all();

        $nextOffset = $offset + $pageSize;
        $links = [
            'first' => $this->buildPaginationUrl($request, 0, $pageSize),
            'last' => null,
            'prev' => $offset > 0
                ? $this->buildPaginationUrl($request, max(0, $offset - $pageSize), $pageSize)
                : null,
            'next' => $hasNext
                ? $this->buildPaginationUrl($request, $nextOffset, $pageSize)
                : null,
        ];

        return $this->jsonApiResponse($resources, null, false, 200, $links);
    }

    /**
     * GET /{code}/accounts/{id} — Single account.
     */
    public function account(Request $request, string $code, string $id): JsonResponse
    {
        $tenantId = TenantContext::getId();
        $baseUrl = $request->getSchemeAndHttpHost();

        $user = DB::table('users')
            ->where('id', (int) $id)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->first(['id', 'name', 'username', 'balance', 'created_at', 'updated_at']);

        if (!$user) {
            return $this->jsonApiError('NotFound', 'Not Found',
                "Account id {$id} not found in currency {$code}", 404);
        }

        return $this->jsonApiResponse(
            $this->buildAccountResource($user, $tenantId, $code, $baseUrl),
            null, true
        );
    }

    /**
     * POST /{code}/accounts — Create an account (external federation partner registering).
     *
     * Komunitin spec expects a user relationship in the payload.
     * NEXUS creates a federated placeholder user for the external account.
     */
    public function createAccount(Request $request, string $code): JsonResponse
    {
        $tenantId = TenantContext::getId();
        $baseUrl = $request->getSchemeAndHttpHost();
        $payload = $request->json()->all();

        $rels = $payload['data']['relationships'] ?? [];
        $userRef = $rels['users']['data'][0] ?? null;

        if (!$userRef || empty($userRef['id'])) {
            return $this->jsonApiError('BadRequest', 'Bad Request',
                'Missing users relationship in request body', 400);
        }

        // Check if this external user already has an account
        $externalUserId = $userRef['id'];
        $existing = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('external_federation_id', $externalUserId)
            ->first();

        if ($existing) {
            return $this->jsonApiResponse(
                $this->buildAccountResource($existing, $tenantId, $code, $baseUrl),
                null, true, 200
            );
        }

        // NEXUS doesn't create real user accounts via federation API —
        // return 403 per Komunitin spec ("Insufficient Scope")
        return $this->jsonApiError('Forbidden', 'Forbidden',
            'Account creation via federation API is not supported. Users must register directly.', 403);
    }

    /**
     * PATCH /{code}/accounts/{id} — Update account attributes (e.g., credit limit).
     */
    public function updateAccount(Request $request, string $code, string $id): JsonResponse
    {
        $tenantId = TenantContext::getId();
        $baseUrl = $request->getSchemeAndHttpHost();
        $payload = $request->json()->all();

        $user = DB::table('users')
            ->where('id', (int) $id)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->first(['id', 'name', 'username', 'balance', 'created_at', 'updated_at']);

        if (!$user) {
            return $this->jsonApiError('NotFound', 'Not Found',
                "Account id {$id} not found in currency {$code}", 404);
        }

        $attrs = $payload['data']['attributes'] ?? [];

        // Komunitin allows updating creditLimit — NEXUS doesn't enforce per-user limits
        // via federation API, so we acknowledge the request but don't change state.
        // The balance field is read-only (managed by transfers).
        if (isset($attrs['balance'])) {
            return $this->jsonApiError('BadRequest', 'Bad Request',
                'Balance cannot be modified directly — use transfers', 400);
        }

        return $this->jsonApiResponse(
            $this->buildAccountResource($user, $tenantId, $code, $baseUrl),
            null, true
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Transfers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /{code}/transfers — List transfers with cursor pagination.
     *
     * Pagination: page[size], page[after]
     * Sort: sort=-created (default descending by created)
     * Filter: filter[account], filter[state], filter[after], filter[before]
     */
    public function transfers(Request $request, string $code): JsonResponse
    {
        $tenantId = TenantContext::getId();
        $pageSize = min((int) ($request->query('page')['size'] ?? $request->query('page_size', '25')), 100);
        $afterCursor = $request->query('page')['after'] ?? $request->query('page_after');
        $sort = $request->query('sort', '-created');
        $baseUrl = $request->getSchemeAndHttpHost();

        $query = DB::table('transactions')
            ->where('tenant_id', $tenantId);

        // Filters
        $filterAccount = $request->query('filter')['account'] ?? $request->query('filter_account');
        $filterState = $request->query('filter')['state'] ?? $request->query('filter_state');
        $filterAfter = $request->query('filter')['after'] ?? $request->query('filter_after');
        $filterBefore = $request->query('filter')['before'] ?? $request->query('filter_before');

        if ($filterAccount) {
            $query->where(function ($q) use ($filterAccount) {
                $q->where('sender_id', $filterAccount)
                    ->orWhere('receiver_id', $filterAccount);
            });
        }

        if ($filterState) {
            $nexusStatus = match ($filterState) {
                'committed', 'accepted' => 'completed',
                'pending' => 'pending',
                'rejected', 'deleted' => 'cancelled',
                default => null,
            };
            if ($nexusStatus) {
                $query->where('status', $nexusStatus);
            }
        }

        if ($filterAfter) {
            $query->where('created_at', '>=', $filterAfter);
        }
        if ($filterBefore) {
            $query->where('created_at', '<=', $filterBefore);
        }

        // Sort
        $sortDesc = str_starts_with($sort, '-');
        $sortField = ltrim($sort, '-+');
        $sortCol = match ($sortField) {
            'created' => 'created_at',
            'updated' => 'updated_at',
            'amount' => 'amount',
            default => 'created_at',
        };

        // Cursor pagination
        $offset = $afterCursor ? (int) $afterCursor : 0;

        $transactions = $query->orderBy($sortCol, $sortDesc ? 'desc' : 'asc')
            ->offset($offset)
            ->limit($pageSize + 1)
            ->get();

        $hasNext = $transactions->count() > $pageSize;
        if ($hasNext) {
            $transactions = $transactions->slice(0, $pageSize);
        }

        $resources = $transactions->map(function ($tx) use ($tenantId, $code, $baseUrl) {
            return $this->buildTransferResource($tx, $tenantId, $code, $baseUrl);
        })->values()->all();

        $nextOffset = $offset + $pageSize;
        $links = [
            'first' => $this->buildPaginationUrl($request, 0, $pageSize),
            'last' => null,
            'prev' => $offset > 0
                ? $this->buildPaginationUrl($request, max(0, $offset - $pageSize), $pageSize)
                : null,
            'next' => $hasNext
                ? $this->buildPaginationUrl($request, $nextOffset, $pageSize)
                : null,
        ];

        return $this->jsonApiResponse($resources, null, false, 200, $links);
    }

    /**
     * GET /{code}/transfers/{id} — Single transfer.
     */
    public function transfer(Request $request, string $code, string $id): JsonResponse
    {
        $tenantId = TenantContext::getId();
        $baseUrl = $request->getSchemeAndHttpHost();

        $tx = DB::table('transactions')
            ->where('id', (int) $id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$tx) {
            return $this->jsonApiError('NotFound', 'Not Found',
                "Transfer id {$id} not found in currency {$code}", 404);
        }

        return $this->jsonApiResponse(
            $this->buildTransferResource($tx, $tenantId, $code, $baseUrl),
            null, true
        );
    }

    /**
     * POST /{code}/transfers — Create a new transfer.
     *
     * Expects Komunitin JSON:API format with payer/payee relationships
     * and amount in minor currency units.
     */
    public function createTransfer(Request $request, string $code): JsonResponse
    {
        $tenantId = TenantContext::getId();
        $baseUrl = $request->getSchemeAndHttpHost();
        $payload = $request->json()->all();

        $data = $payload['data'] ?? [];
        $attrs = $data['attributes'] ?? [];
        $rels = $data['relationships'] ?? [];

        $amount = KomunitinAdapter::minorUnitsToHours((int) ($attrs['amount'] ?? 0));
        $description = $attrs['meta'] ?? $attrs['description'] ?? '';
        $state = $attrs['state'] ?? 'committed';
        $payerId = $rels['payer']['data']['id'] ?? null;
        $payeeId = $rels['payee']['data']['id'] ?? null;

        if (!$payerId || !$payeeId || $amount <= 0) {
            return $this->jsonApiError('BadRequest', 'Bad Request',
                'Missing required fields: payer, payee, and amount > 0', 400);
        }

        // Amount bounds validation
        if ($amount > 999999.99) {
            return $this->jsonApiError('BadRequest', 'Bad Request',
                'Amount exceeds maximum allowed value', 400);
        }

        // Validate accounts exist
        $payee = DB::table('users')
            ->where('id', (int) $payeeId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->first();

        if (!$payee) {
            return $this->jsonApiError('NotFound', 'Not Found',
                "Payee account not found in currency {$code}", 404);
        }

        $payerExists = DB::table('users')
            ->where('id', (int) $payerId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->exists();

        if (!$payerExists) {
            return $this->jsonApiError('NotFound', 'Not Found',
                "Payer account not found in currency {$code}", 404);
        }

        // Execute transfer with atomic balance check to prevent race conditions
        DB::beginTransaction();
        try {
            // Deduct from payer atomically — WHERE balance >= amount prevents overdraw
            $updated = DB::update(
                "UPDATE users SET balance = balance - ? WHERE id = ? AND tenant_id = ? AND balance >= ?",
                [$amount, (int) $payerId, $tenantId, $amount]
            );

            if ($updated === 0) {
                DB::rollBack();
                return $this->jsonApiError('Forbidden', 'Forbidden',
                    'Insufficient balance for this transfer', 403);
            }

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
            return $this->jsonApiError('InternalError', 'Internal Server Error',
                'Transfer processing failed', 500);
        }

        $tx = DB::table('transactions')
            ->where('id', $txId)
            ->first();

        return $this->jsonApiResponse(
            $this->buildTransferResource($tx, $tenantId, $code, $baseUrl),
            null, true, 201
        );
    }

    /**
     * PATCH /{code}/transfers/{id} — Update transfer state.
     */
    public function updateTransfer(Request $request, string $code, string $id): JsonResponse
    {
        $tenantId = TenantContext::getId();
        $baseUrl = $request->getSchemeAndHttpHost();
        $payload = $request->json()->all();

        $tx = DB::table('transactions')
            ->where('id', (int) $id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$tx) {
            return $this->jsonApiError('NotFound', 'Not Found',
                "Transfer id {$id} not found in currency {$code}", 404);
        }

        $newState = $payload['data']['attributes']['state'] ?? null;
        if (!$newState) {
            return $this->jsonApiError('BadRequest', 'Bad Request',
                'Missing state in request body', 400);
        }

        $newNexusStatus = match ($newState) {
            'committed' => 'completed',
            'pending' => 'pending',
            'rejected' => 'cancelled',
            default => null,
        };

        if (!$newNexusStatus) {
            return $this->jsonApiError('BadRequest', 'Bad Request',
                "Invalid state: {$newState}. Must be committed, pending, or rejected", 400);
        }

        DB::table('transactions')
            ->where('id', (int) $id)
            ->where('tenant_id', $tenantId)
            ->update([
                'status' => $newNexusStatus,
                'updated_at' => now(),
            ]);

        $tx = DB::table('transactions')
            ->where('id', (int) $id)
            ->first();

        return $this->jsonApiResponse(
            $this->buildTransferResource($tx, $tenantId, $code, $baseUrl),
            null, true
        );
    }

    /**
     * DELETE /{code}/transfers/{id} — Delete transfer.
     */
    public function deleteTransfer(Request $request, string $code, string $id): JsonResponse
    {
        $tenantId = TenantContext::getId();

        $tx = DB::table('transactions')
            ->where('id', (int) $id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$tx) {
            return $this->jsonApiError('NotFound', 'Not Found',
                "Transfer id {$id} not found in currency {$code}", 404);
        }

        // Only pending transfers can be deleted
        if (($tx->status ?? '') === 'completed') {
            return $this->jsonApiError('BadRequest', 'Bad Request',
                'Cannot delete a committed transfer', 400);
        }

        DB::table('transactions')
            ->where('id', (int) $id)
            ->where('tenant_id', $tenantId)
            ->delete();

        return response()->json(null, 204, [
            'Content-Type' => 'application/vnd.api+json',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Resource builders
    // ─────────────────────────────────────────────────────────────────────────

    private function buildCurrencyResource(int $tenantId): array
    {
        $tenant = DB::table('tenants')->where('id', $tenantId)->first(['slug', 'name']);
        $currencyCode = strtoupper($tenant->slug ?? 'HOURS');

        return [
            'type' => 'currencies',
            'id' => "hours-{$tenantId}",
            'attributes' => [
                'code' => $currencyCode,
                'status' => 'active',
                'name' => 'Hours',
                'namePlural' => 'Hours',
                'symbol' => 'h',
                'decimals' => 2,
                'scale' => KomunitinAdapter::MINOR_UNITS_PER_HOUR,
                'rate' => ['n' => 1, 'd' => 1],
                'created' => $tenant->created_at ?? now()->toIso8601String(),
                'updated' => now()->toIso8601String(),
            ],
            'links' => [
                'self' => "/api/v2/federation/komunitin/{$currencyCode}/currency",
            ],
        ];
    }

    private function buildAccountResource(object $user, int $tenantId, string $code, string $baseUrl): array
    {
        $accountCode = strtoupper($code) . str_pad((string) $user->id, 4, '0', STR_PAD_LEFT);

        return [
            'type' => 'accounts',
            'id' => (string) $user->id,
            'attributes' => [
                'code' => $accountCode,
                'balance' => KomunitinAdapter::hoursToMinorUnits((float) $user->balance),
                'creditLimit' => KomunitinAdapter::hoursToMinorUnits(-100.0),
                'created' => $user->created_at,
                'updated' => $user->updated_at ?? $user->created_at,
            ],
            'relationships' => [
                'currency' => [
                    'data' => ['type' => 'currencies', 'id' => "hours-{$tenantId}"],
                ],
                'users' => [
                    'data' => [
                        [
                            'type' => 'users',
                            'id' => (string) $user->id,
                            'meta' => ['external' => false, 'href' => null],
                        ],
                    ],
                ],
            ],
            'links' => [
                'self' => "{$baseUrl}/api/v2/federation/komunitin/{$code}/accounts/{$user->id}",
            ],
        ];
    }

    private function buildTransferResource(object $tx, int $tenantId, string $code, string $baseUrl): array
    {
        // Generate a hash for the transfer (matching Komunitin spec)
        $hash = hash('sha256', "{$tx->id}|{$tx->sender_id}|{$tx->receiver_id}|{$tx->amount}|{$tx->created_at}");

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
                'hash' => $hash,
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
            'links' => [
                'self' => "{$baseUrl}/api/v2/federation/komunitin/{$code}/transfers/{$tx->id}",
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // JSON:API response helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build a JSON:API-compliant response matching the Komunitin spec.
     */
    private function jsonApiResponse(
        array $data,
        ?array $meta = null,
        bool $isSingle = false,
        int $status = 200,
        ?array $links = null
    ): JsonResponse {
        $response = ['data' => $isSingle ? $data : array_values($data)];

        if ($links) {
            $response['links'] = $links;
        }

        if ($meta) {
            $response['meta'] = $meta;
        }

        // JSON:API spec: include empty included array for collections
        if (!$isSingle) {
            $response['included'] = [];
        }

        return response()->json($response, $status, [
            'Content-Type' => 'application/vnd.api+json',
            'API-Version' => '2.0',
        ]);
    }

    /**
     * Build a JSON:API-compliant error response matching the Komunitin spec.
     *
     * Format: {errors: [{status, code, title, detail}]}
     */
    private function jsonApiError(string $code, string $title, string $detail, int $status = 400): JsonResponse
    {
        return response()->json([
            'errors' => [
                [
                    'status' => (string) $status,
                    'code' => $code,
                    'title' => $title,
                    'detail' => $detail,
                ],
            ],
        ], $status, [
            'Content-Type' => 'application/vnd.api+json',
        ]);
    }

    /**
     * Build a pagination URL with page[size] and page[after] parameters.
     */
    private function buildPaginationUrl(Request $request, int $offset, int $pageSize): string
    {
        $baseUrl = $request->url();
        $params = $request->query();

        // Remove existing pagination params
        unset($params['page']);
        unset($params['page_size']);
        unset($params['page_after']);

        $params['page[size]'] = $pageSize;
        $params['page[after]'] = $offset;

        return $baseUrl . '?' . http_build_query($params);
    }
}

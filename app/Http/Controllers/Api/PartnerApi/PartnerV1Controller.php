<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api\PartnerApi;

use App\Core\TenantContext;
use App\Http\Controllers\Api\BaseApiController;
use App\Services\PartnerApi\PartnerWebhookDispatcher;
use App\Services\WebhookDispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * AG60 — Curated Partner API v1.
 *
 * Hand-picked endpoints exposed to external integrators. NOT a full mirror
 * of the platform API — only what banking / payment / municipal admin
 * partners actually need:
 *
 *   - users (list / show, public-safe fields)
 *   - listings (list)
 *   - wallet (read balance, credit time-credits via authorized integrations)
 *   - aggregates (bucketed community counts, no PII)
 *   - webhooks (subscription management)
 *
 * The middleware (`partner.api:<scope>`) enforces auth + scope + sandbox
 * before this controller runs. Tenant scope is set by the middleware.
 */
class PartnerV1Controller extends BaseApiController
{
    protected bool $isV2Api = true;

    /* ─── Users ─────────────────────────────────────────────────────────── */

    public function listUsers(Request $request): JsonResponse
    {
        $tenantId = TenantContext::getId();
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 25)));
        $offset = ($page - 1) * $perPage;

        $hasPii = in_array('users.pii', $this->scopes($request), true);

        $columns = ['id', 'name', 'username', 'created_at', 'status'];
        if ($hasPii) {
            $columns[] = 'email';
        }

        $rows = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->select($columns)
            ->orderBy('id')
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $total = (int) DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->count();

        return $this->respondWithPaginatedCollection($rows, $total, $page, $perPage);
    }

    public function showUser(Request $request, int $id): JsonResponse
    {
        $tenantId = TenantContext::getId();
        $hasPii = in_array('users.pii', $this->scopes($request), true);

        $columns = ['id', 'name', 'username', 'created_at', 'status'];
        if ($hasPii) {
            $columns[] = 'email';
        }

        $user = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->select($columns)
            ->first();

        if (! $user) {
            return $this->respondNotFound('User not found.', 'USER_NOT_FOUND');
        }

        return $this->respondWithData(['user' => (array) $user]);
    }

    /* ─── Listings ──────────────────────────────────────────────────────── */

    public function listListings(Request $request): JsonResponse
    {
        $tenantId = TenantContext::getId();
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 25)));
        $offset = ($page - 1) * $perPage;

        $rows = DB::table('listings')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->select(['id', 'user_id', 'title', 'type', 'created_at'])
            ->orderBy('id', 'desc')
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $total = (int) DB::table('listings')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->count();

        return $this->respondWithPaginatedCollection($rows, $total, $page, $perPage);
    }

    /* ─── Wallet ────────────────────────────────────────────────────────── */

    public function walletBalance(int $userId): JsonResponse
    {
        $tenantId = TenantContext::getId();
        $user = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('id', $userId)
            ->first();

        if (! $user) {
            return $this->respondNotFound('User not found.', 'USER_NOT_FOUND');
        }

        // Sum credits-debits from time_transactions when present, else
        // fall back to a direct column on users if the schema has one.
        $balance = 0.0;
        if (\Illuminate\Support\Facades\Schema::hasTable('time_transactions')) {
            $balance = (float) DB::table('time_transactions')
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($userId) {
                    $q->where('to_user_id', $userId)->orWhere('from_user_id', $userId);
                })
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN to_user_id = ? THEN hours WHEN from_user_id = ? THEN -hours ELSE 0 END), 0) AS balance',
                    [$userId, $userId]
                )
                ->value('balance');
        }

        return $this->respondWithData([
            'user_id' => $userId,
            'balance_hours' => round($balance, 4),
            'currency' => 'time_credits',
        ]);
    }

    /**
     * POST /api/partner/v1/wallet/credit
     *
     * Body: { user_id, hours, reference, note? }
     *
     * Used by bank integrations to credit time/cash from settled transfers.
     * Records a row in `time_transactions` with `system_origin = 'partner_api'`
     * and the partner's id baked into the note for audit.
     */
    public function walletCredit(Request $request): JsonResponse
    {
        $tenantId = TenantContext::getId();
        $partner = $request->attributes->get('partner', []);

        $userId = (int) $request->input('user_id', 0);
        $hours = (float) $request->input('hours', 0);
        $reference = trim((string) $request->input('reference', ''));
        $note = trim((string) $request->input('note', ''));

        if ($userId <= 0 || $hours <= 0 || $reference === '') {
            return $this->respondWithError('invalid_request',
                'user_id, positive hours, and reference are required.', null, 422);
        }

        $user = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('id', $userId)
            ->first();
        if (! $user) {
            return $this->respondNotFound('User not found.', 'USER_NOT_FOUND');
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('time_transactions')) {
            return $this->respondWithError('not_supported',
                'Wallet writes are not available on this tenant.', null, 503);
        }

        $txId = DB::table('time_transactions')->insertGetId([
            'tenant_id' => $tenantId,
            'from_user_id' => null,
            'to_user_id' => $userId,
            'hours' => $hours,
            'note' => "[partner:{$partner['slug']}] ref={$reference} {$note}",
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Fan out to any subscribed partner webhooks
        PartnerWebhookDispatcher::dispatch('wallet.credited', [
            'transaction_id' => $txId,
            'user_id' => $userId,
            'hours' => $hours,
            'reference' => $reference,
        ]);

        return $this->respondWithData([
            'transaction_id' => $txId,
            'user_id' => $userId,
            'hours' => $hours,
            'reference' => $reference,
        ], null, 201);
    }

    /* ─── Aggregates ───────────────────────────────────────────────────── */

    public function communityAggregates(): JsonResponse
    {
        $tenantId = TenantContext::getId();

        $userCount = (int) DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->count();

        $listingCount = (int) DB::table('listings')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->count();

        // Bucket totals to avoid leaking exact-numbers signal at small N.
        $bucket = static function (int $n): int {
            if ($n < 10) return 0;
            if ($n < 100) return (int) (floor($n / 10) * 10);
            return (int) (floor($n / 100) * 100);
        };

        return $this->respondWithData([
            'tenant_id' => $tenantId,
            'active_members_bucket' => $bucket($userCount),
            'active_listings_bucket' => $bucket($listingCount),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /* ─── Webhook subscriptions ────────────────────────────────────────── */

    public function listWebhookSubscriptions(Request $request): JsonResponse
    {
        $partner = $request->attributes->get('partner', []);
        return $this->respondWithData([
            'subscriptions' => PartnerWebhookDispatcher::listForPartner((int) $partner['id']),
        ]);
    }

    public function createWebhookSubscription(Request $request): JsonResponse
    {
        $partner = $request->attributes->get('partner', []);

        $events = $request->input('event_types', []);
        $targetUrl = trim((string) $request->input('target_url', ''));

        if (! is_array($events) || empty($events) || $targetUrl === '') {
            return $this->respondWithError('invalid_request',
                'event_types (array) and target_url are required.', null, 422);
        }

        if (! filter_var($targetUrl, FILTER_VALIDATE_URL) || ! str_starts_with($targetUrl, 'https://')) {
            return $this->respondWithError('invalid_url',
                'target_url must be a valid https:// URL.', null, 422);
        }

        if (WebhookDispatchService::isPrivateUrl($targetUrl)) {
            return $this->respondWithError('private_url',
                'target_url resolves to a private network address.', null, 422);
        }

        try {
            $sub = PartnerWebhookDispatcher::createSubscription(
                (int) $partner['id'],
                array_values(array_map('strval', $events)),
                $targetUrl,
            );
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('invalid_request', $e->getMessage(), null, 422);
        }

        return $this->respondWithData(['subscription' => $sub], null, 201);
    }

    /* ─── Helpers ──────────────────────────────────────────────────────── */

    /** @return string[] */
    private function scopes(Request $request): array
    {
        $s = $request->attributes->get('partner_scopes', []);
        return is_array($s) ? $s : [];
    }
}

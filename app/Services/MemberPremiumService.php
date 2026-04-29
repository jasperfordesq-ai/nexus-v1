<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * MemberPremiumService — AG58 Member Premium Tier paywall framework.
 *
 * Manages member-facing premium tiers (distinct from tenant billing under
 * StripeSubscriptionService). Each tenant defines its own tiers; members
 * subscribe via Stripe Checkout, and tier feature keys gate UI/server logic.
 *
 * Common feature keys (admin-defined, open-ended):
 *   - verified_badge
 *   - priority_matching
 *   - advanced_search
 *   - ad_free
 */
class MemberPremiumService
{
    /**
     * List active tiers for a tenant, sorted by sort_order.
     */
    public static function listTiers(int $tenantId, bool $includeInactive = false): array
    {
        $query = "SELECT * FROM member_premium_tiers WHERE tenant_id = ?";
        $params = [$tenantId];
        if (! $includeInactive) {
            $query .= " AND is_active = 1";
        }
        $query .= " ORDER BY sort_order ASC, id ASC";

        $rows = DB::select($query, $params);
        return array_map(fn ($r) => self::normalizeTier($r), $rows);
    }

    /**
     * Get a single tier (tenant-scoped).
     */
    public static function getTier(int $tenantId, int $tierId): ?array
    {
        $row = DB::selectOne(
            "SELECT * FROM member_premium_tiers WHERE id = ? AND tenant_id = ?",
            [$tierId, $tenantId]
        );
        return $row ? self::normalizeTier($row) : null;
    }

    /**
     * Create a new tier.
     *
     * @param array{slug:string,name:string,description?:?string,monthly_price_cents?:int,yearly_price_cents?:int,features?:array,sort_order?:int,is_active?:bool} $data
     */
    public static function createTier(int $tenantId, array $data): int
    {
        return (int) DB::table('member_premium_tiers')->insertGetId([
            'tenant_id' => $tenantId,
            'slug' => $data['slug'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'monthly_price_cents' => (int) ($data['monthly_price_cents'] ?? 0),
            'yearly_price_cents' => (int) ($data['yearly_price_cents'] ?? 0),
            'features' => json_encode(array_values($data['features'] ?? [])),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => ! empty($data['is_active']) ? 1 : 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Update a tier.
     */
    public static function updateTier(int $tenantId, int $tierId, array $data): bool
    {
        $update = ['updated_at' => now()];
        foreach (['slug', 'name', 'description'] as $k) {
            if (array_key_exists($k, $data)) {
                $update[$k] = $data[$k];
            }
        }
        foreach (['monthly_price_cents', 'yearly_price_cents', 'sort_order'] as $k) {
            if (array_key_exists($k, $data)) {
                $update[$k] = (int) $data[$k];
            }
        }
        if (array_key_exists('features', $data)) {
            $update['features'] = json_encode(array_values((array) $data['features']));
        }
        if (array_key_exists('is_active', $data)) {
            $update['is_active'] = $data['is_active'] ? 1 : 0;
        }

        $affected = DB::table('member_premium_tiers')
            ->where('id', $tierId)
            ->where('tenant_id', $tenantId)
            ->update($update);

        return $affected > 0;
    }

    /**
     * Delete a tier (only if no active subscriptions reference it).
     */
    public static function deleteTier(int $tenantId, int $tierId): bool
    {
        $hasSubs = DB::table('member_subscriptions')
            ->where('tenant_id', $tenantId)
            ->where('tier_id', $tierId)
            ->whereIn('status', ['active', 'trialing', 'past_due', 'grace'])
            ->exists();

        if ($hasSubs) {
            throw new RuntimeException('Cannot delete a tier with active subscribers. Deactivate it instead.');
        }

        return DB::table('member_premium_tiers')
            ->where('id', $tierId)
            ->where('tenant_id', $tenantId)
            ->delete() > 0;
    }

    /**
     * Get the user's currently active subscription (if any). Tenant-scoped via TenantContext.
     */
    public static function getMemberSubscription(int $userId): ?array
    {
        $tenantId = TenantContext::getId();

        $row = DB::selectOne(
            "SELECT ms.*, mpt.name AS tier_name, mpt.slug AS tier_slug,
                    mpt.features AS tier_features, mpt.monthly_price_cents,
                    mpt.yearly_price_cents
             FROM member_subscriptions ms
             JOIN member_premium_tiers mpt ON mpt.id = ms.tier_id
             WHERE ms.user_id = ? AND ms.tenant_id = ?
             ORDER BY ms.created_at DESC LIMIT 1",
            [$userId, $tenantId]
        );

        if (! $row) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'tier_id' => (int) $row->tier_id,
            'tier_name' => $row->tier_name,
            'tier_slug' => $row->tier_slug,
            'features' => self::decodeFeatures($row->tier_features),
            'status' => $row->status,
            'billing_interval' => $row->billing_interval,
            'current_period_start' => $row->current_period_start,
            'current_period_end' => $row->current_period_end,
            'canceled_at' => $row->canceled_at,
            'grace_period_ends_at' => $row->grace_period_ends_at,
            'is_active' => self::statusIsEntitled($row->status, $row->grace_period_ends_at),
        ];
    }

    /**
     * Get the user's currently entitled tier — null if no active premium.
     */
    public static function getMemberTier(int $userId): ?array
    {
        $sub = self::getMemberSubscription($userId);
        if (! $sub || ! $sub['is_active']) {
            return null;
        }
        return [
            'tier_id' => $sub['tier_id'],
            'tier_name' => $sub['tier_name'],
            'tier_slug' => $sub['tier_slug'],
            'features' => $sub['features'],
        ];
    }

    /**
     * Check if a user has unlocked a specific feature key via their tier.
     * Safe to call from anywhere — returns false if no active subscription.
     */
    public static function hasUnlocked(int $userId, string $featureKey): bool
    {
        $tier = self::getMemberTier($userId);
        if (! $tier) {
            return false;
        }
        return in_array($featureKey, $tier['features'], true);
    }

    /**
     * Sync a tier's prices to Stripe (creates Product + Prices if missing).
     */
    public static function syncTierToStripe(int $tenantId, int $tierId): void
    {
        $tier = DB::selectOne(
            "SELECT * FROM member_premium_tiers WHERE id = ? AND tenant_id = ?",
            [$tierId, $tenantId]
        );
        if (! $tier) {
            throw new RuntimeException("Tier {$tierId} not found");
        }

        $client = StripeService::client();
        $currency = TenantContext::getCurrency();
        $updates = [];
        $params = [];

        // Product is implicit per (tenant, tier). Always create a Stripe Product
        // since we don't store the product_id (we rely on price_id directly).
        $productName = sprintf('Premium: %s', $tier->name);
        $productDescription = $tier->description ?: $productName;

        $product = $client->products->create([
            'name' => $productName,
            'description' => $productDescription,
            'metadata' => [
                'nexus_tier_id' => (string) $tierId,
                'nexus_tenant_id' => (string) $tenantId,
                'nexus_kind' => 'member_premium',
            ],
        ]);

        if (empty($tier->stripe_price_id_monthly) && (int) $tier->monthly_price_cents > 0) {
            $price = $client->prices->create([
                'product' => $product->id,
                'unit_amount' => (int) $tier->monthly_price_cents,
                'currency' => $currency,
                'recurring' => ['interval' => 'month'],
                'metadata' => ['nexus_tier_id' => (string) $tierId, 'interval' => 'monthly'],
            ]);
            $updates[] = 'stripe_price_id_monthly = ?';
            $params[] = $price->id;
        }

        if (empty($tier->stripe_price_id_yearly) && (int) $tier->yearly_price_cents > 0) {
            $price = $client->prices->create([
                'product' => $product->id,
                'unit_amount' => (int) $tier->yearly_price_cents,
                'currency' => $currency,
                'recurring' => ['interval' => 'year'],
                'metadata' => ['nexus_tier_id' => (string) $tierId, 'interval' => 'yearly'],
            ]);
            $updates[] = 'stripe_price_id_yearly = ?';
            $params[] = $price->id;
        }

        if (! empty($updates)) {
            $updates[] = 'updated_at = NOW()';
            $params[] = $tierId;
            DB::update(
                "UPDATE member_premium_tiers SET " . implode(', ', $updates) . " WHERE id = ?",
                $params
            );
        }

        Log::info('MemberPremiumService::syncTierToStripe ok', [
            'tenant_id' => $tenantId,
            'tier_id' => $tierId,
            'product_id' => $product->id,
        ]);
    }

    /**
     * Create a Stripe Checkout session for a member to subscribe to a tier.
     *
     * @return array{checkout_url:string,session_id:string}
     */
    public static function createCheckoutSession(
        int $userId,
        int $tierId,
        string $interval,
        string $returnUrl
    ): array {
        $tenantId = TenantContext::getId();
        if (! in_array($interval, ['monthly', 'yearly'], true)) {
            throw new RuntimeException('Invalid billing interval');
        }

        $tier = DB::selectOne(
            "SELECT * FROM member_premium_tiers WHERE id = ? AND tenant_id = ? AND is_active = 1",
            [$tierId, $tenantId]
        );
        if (! $tier) {
            throw new RuntimeException("Tier {$tierId} not found or inactive");
        }

        $priceId = $interval === 'yearly' ? $tier->stripe_price_id_yearly : $tier->stripe_price_id_monthly;
        if (empty($priceId)) {
            throw new RuntimeException(
                "Tier '{$tier->name}' has no Stripe Price for {$interval} — admin must run Sync to Stripe first"
            );
        }

        $user = DB::selectOne(
            "SELECT id, email, name, first_name, stripe_customer_id FROM users WHERE id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        );
        if (! $user) {
            throw new RuntimeException("User {$userId} not found in tenant {$tenantId}");
        }

        $client = StripeService::client();

        // Get or create Stripe customer
        $customerId = $user->stripe_customer_id ?? null;
        if (empty($customerId)) {
            $customer = $client->customers->create([
                'email' => $user->email,
                'name' => $user->name ?: $user->first_name,
                'metadata' => [
                    'nexus_user_id' => (string) $userId,
                    'nexus_tenant_id' => (string) $tenantId,
                ],
            ]);
            $customerId = $customer->id;

            // Persist if column exists; ignore if it doesn't.
            try {
                DB::update("UPDATE users SET stripe_customer_id = ? WHERE id = ?", [$customerId, $userId]);
            } catch (\Throwable $e) {
                Log::info('users.stripe_customer_id missing — proceeding without persisting', [
                    'user_id' => $userId,
                ]);
            }
        }

        $session = $client->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $customerId,
            'line_items' => [['price' => $priceId, 'quantity' => 1]],
            'metadata' => [
                'nexus_user_id' => (string) $userId,
                'nexus_tenant_id' => (string) $tenantId,
                'nexus_tier_id' => (string) $tierId,
                'nexus_kind' => 'member_premium',
                'nexus_interval' => $interval,
            ],
            'subscription_data' => [
                'metadata' => [
                    'nexus_user_id' => (string) $userId,
                    'nexus_tenant_id' => (string) $tenantId,
                    'nexus_tier_id' => (string) $tierId,
                    'nexus_kind' => 'member_premium',
                    'nexus_interval' => $interval,
                ],
            ],
            'success_url' => rtrim($returnUrl, '/') . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => rtrim($returnUrl, '/') . '?cancelled=1',
        ]);

        Log::info('Member premium checkout session created', [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'tier_id' => $tierId,
            'session_id' => $session->id,
        ]);

        return [
            'checkout_url' => $session->url,
            'session_id' => $session->id,
        ];
    }

    /**
     * Cancel a subscription (default: at period end via Stripe).
     */
    public static function cancel(int $userId, bool $atPeriodEnd = true): bool
    {
        $tenantId = TenantContext::getId();
        $sub = DB::selectOne(
            "SELECT * FROM member_subscriptions
             WHERE user_id = ? AND tenant_id = ?
             ORDER BY created_at DESC LIMIT 1",
            [$userId, $tenantId]
        );
        if (! $sub || empty($sub->stripe_subscription_id)) {
            return false;
        }

        $client = StripeService::client();
        if ($atPeriodEnd) {
            $client->subscriptions->update($sub->stripe_subscription_id, [
                'cancel_at_period_end' => true,
            ]);
        } else {
            $client->subscriptions->cancel($sub->stripe_subscription_id);
        }

        DB::update(
            "UPDATE member_subscriptions
             SET canceled_at = NOW(), updated_at = NOW()
             WHERE id = ?",
            [$sub->id]
        );

        return true;
    }

    /**
     * Create a Stripe Billing Portal session for the member.
     *
     * @return array{portal_url:string}
     */
    public static function createBillingPortalSession(int $userId, string $returnUrl): array
    {
        $tenantId = TenantContext::getId();
        $sub = DB::selectOne(
            "SELECT stripe_customer_id FROM member_subscriptions
             WHERE user_id = ? AND tenant_id = ? AND stripe_customer_id IS NOT NULL
             ORDER BY created_at DESC LIMIT 1",
            [$userId, $tenantId]
        );
        if (! $sub || empty($sub->stripe_customer_id)) {
            throw new RuntimeException('No Stripe customer found — subscribe to a tier first');
        }

        $client = StripeService::client();
        $session = $client->billingPortal->sessions->create([
            'customer' => $sub->stripe_customer_id,
            'return_url' => $returnUrl,
        ]);

        return ['portal_url' => $session->url];
    }

    /**
     * Apply a Stripe webhook event for a member subscription.
     * Routed from StripeWebhookController for events whose metadata has nexus_kind=member_premium.
     */
    public static function applyWebhookEvent(object $event): void
    {
        $type = $event->type ?? '';
        $obj = $event->data->object ?? null;
        if (! $obj) {
            return;
        }

        try {
            switch ($type) {
                case 'checkout.session.completed':
                    self::handleCheckoutCompleted($obj, $event->id ?? null);
                    break;
                case 'customer.subscription.created':
                case 'customer.subscription.updated':
                    self::handleSubscriptionUpserted($obj, $event->id ?? null);
                    break;
                case 'customer.subscription.deleted':
                    self::handleSubscriptionDeleted($obj, $event->id ?? null);
                    break;
                case 'invoice.payment_failed':
                    self::handleInvoicePaymentFailed($obj, $event->id ?? null);
                    break;
                case 'invoice.paid':
                    self::handleInvoicePaid($obj, $event->id ?? null);
                    break;
                default:
                    // Unhandled — ignore
                    break;
            }
        } catch (\Throwable $e) {
            Log::error('MemberPremiumService::applyWebhookEvent failed', [
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Webhook handlers (private)
    // ─────────────────────────────────────────────────────────────────────────

    private static function handleCheckoutCompleted(object $session, ?string $eventId): void
    {
        $meta = $session->metadata ?? null;
        if (! self::isMemberPremiumMeta($meta)) {
            return;
        }

        $userId = (int) ($meta->nexus_user_id ?? 0);
        $tenantId = (int) ($meta->nexus_tenant_id ?? 0);
        $tierId = (int) ($meta->nexus_tier_id ?? 0);
        $interval = (string) ($meta->nexus_interval ?? 'monthly');

        if (! $userId || ! $tenantId || ! $tierId) {
            return;
        }

        $stripeSubId = $session->subscription ?? null;
        $stripeCustomerId = $session->customer ?? null;

        // Initial upsert — subscription.updated event will refine period dates.
        $existing = DB::selectOne(
            "SELECT id FROM member_subscriptions WHERE stripe_subscription_id = ?",
            [$stripeSubId]
        );

        if ($existing) {
            DB::update(
                "UPDATE member_subscriptions
                 SET tier_id = ?, status = 'active', billing_interval = ?,
                     stripe_customer_id = ?, updated_at = NOW()
                 WHERE id = ?",
                [$tierId, $interval, $stripeCustomerId, $existing->id]
            );
            $subId = (int) $existing->id;
        } else {
            $subId = (int) DB::table('member_subscriptions')->insertGetId([
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'tier_id' => $tierId,
                'stripe_subscription_id' => $stripeSubId,
                'stripe_customer_id' => $stripeCustomerId,
                'status' => 'active',
                'billing_interval' => $interval,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        self::recordEvent($subId, $tenantId, 'checkout.session.completed', $eventId, $session);
    }

    private static function handleSubscriptionUpserted(object $sub, ?string $eventId): void
    {
        $meta = $sub->metadata ?? null;
        if (! self::isMemberPremiumMeta($meta)) {
            return;
        }

        $stripeSubId = $sub->id ?? null;
        if (! $stripeSubId) {
            return;
        }

        $userId = (int) ($meta->nexus_user_id ?? 0);
        $tenantId = (int) ($meta->nexus_tenant_id ?? 0);
        $tierId = (int) ($meta->nexus_tier_id ?? 0);
        $interval = (string) ($meta->nexus_interval ?? 'monthly');

        $stripeStatus = (string) ($sub->status ?? 'active');
        $statusMap = [
            'active' => 'active',
            'trialing' => 'trialing',
            'past_due' => 'past_due',
            'canceled' => 'canceled',
            'unpaid' => 'past_due',
            'incomplete' => 'incomplete',
            'incomplete_expired' => 'canceled',
            'paused' => 'canceled',
        ];
        $status = $statusMap[$stripeStatus] ?? 'active';

        $periodStart = isset($sub->current_period_start)
            ? date('Y-m-d H:i:s', (int) $sub->current_period_start) : null;
        $periodEnd = isset($sub->current_period_end)
            ? date('Y-m-d H:i:s', (int) $sub->current_period_end) : null;
        $canceledAt = isset($sub->canceled_at) && $sub->canceled_at
            ? date('Y-m-d H:i:s', (int) $sub->canceled_at) : null;

        // Set 7-day grace period when entering past_due
        $graceEnds = null;
        if ($status === 'past_due') {
            $graceEnds = date('Y-m-d H:i:s', strtotime('+7 days'));
        }

        $existing = DB::selectOne(
            "SELECT id FROM member_subscriptions WHERE stripe_subscription_id = ?",
            [$stripeSubId]
        );

        if ($existing) {
            DB::update(
                "UPDATE member_subscriptions
                 SET tier_id = COALESCE(?, tier_id),
                     status = ?, billing_interval = ?,
                     current_period_start = ?, current_period_end = ?,
                     canceled_at = ?, grace_period_ends_at = ?,
                     updated_at = NOW()
                 WHERE id = ?",
                [
                    $tierId ?: null, $status, $interval,
                    $periodStart, $periodEnd, $canceledAt, $graceEnds,
                    $existing->id,
                ]
            );
            $subId = (int) $existing->id;
        } else {
            if (! $userId || ! $tenantId || ! $tierId) {
                return;
            }
            $subId = (int) DB::table('member_subscriptions')->insertGetId([
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'tier_id' => $tierId,
                'stripe_subscription_id' => $stripeSubId,
                'stripe_customer_id' => $sub->customer ?? null,
                'status' => $status,
                'billing_interval' => $interval,
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
                'canceled_at' => $canceledAt,
                'grace_period_ends_at' => $graceEnds,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        self::recordEvent($subId, $tenantId ?: self::lookupTenantBySub($subId), 'subscription.updated', $eventId, $sub);
    }

    private static function handleSubscriptionDeleted(object $sub, ?string $eventId): void
    {
        $meta = $sub->metadata ?? null;
        if (! self::isMemberPremiumMeta($meta)) {
            return;
        }

        $row = DB::selectOne(
            "SELECT id, tenant_id FROM member_subscriptions WHERE stripe_subscription_id = ?",
            [$sub->id ?? null]
        );
        if (! $row) {
            return;
        }

        DB::update(
            "UPDATE member_subscriptions
             SET status = 'canceled', canceled_at = COALESCE(canceled_at, NOW()), updated_at = NOW()
             WHERE id = ?",
            [$row->id]
        );

        self::recordEvent((int) $row->id, (int) $row->tenant_id, 'subscription.deleted', $eventId, $sub);
    }

    private static function handleInvoicePaid(object $invoice, ?string $eventId): void
    {
        $stripeSubId = $invoice->subscription ?? null;
        if (! $stripeSubId) {
            return;
        }

        $row = DB::selectOne(
            "SELECT id, tenant_id FROM member_subscriptions WHERE stripe_subscription_id = ?",
            [$stripeSubId]
        );
        if (! $row) {
            return;
        }

        DB::update(
            "UPDATE member_subscriptions
             SET status = 'active', grace_period_ends_at = NULL, updated_at = NOW()
             WHERE id = ?",
            [$row->id]
        );

        self::recordEvent((int) $row->id, (int) $row->tenant_id, 'invoice.paid', $eventId, $invoice);
    }

    private static function handleInvoicePaymentFailed(object $invoice, ?string $eventId): void
    {
        $stripeSubId = $invoice->subscription ?? null;
        if (! $stripeSubId) {
            return;
        }

        $row = DB::selectOne(
            "SELECT id, tenant_id FROM member_subscriptions WHERE stripe_subscription_id = ?",
            [$stripeSubId]
        );
        if (! $row) {
            return;
        }

        $graceEnds = date('Y-m-d H:i:s', strtotime('+7 days'));

        DB::update(
            "UPDATE member_subscriptions
             SET status = 'past_due', grace_period_ends_at = ?, updated_at = NOW()
             WHERE id = ?",
            [$graceEnds, $row->id]
        );

        self::recordEvent((int) $row->id, (int) $row->tenant_id, 'invoice.payment_failed', $eventId, $invoice);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Determine whether a Stripe object's metadata identifies it as a
     * member-premium subscription (vs tenant billing/marketplace/donation).
     */
    public static function isMemberPremiumMeta($meta): bool
    {
        if (! $meta) {
            return false;
        }
        $kind = is_object($meta) ? ($meta->nexus_kind ?? null) : ($meta['nexus_kind'] ?? null);
        return $kind === 'member_premium';
    }

    /**
     * Determine if a Stripe event should be routed to MemberPremiumService.
     * Inspects the event payload's nexus_kind metadata.
     */
    public static function eventBelongsHere(object $event): bool
    {
        $obj = $event->data->object ?? null;
        if (! $obj) {
            return false;
        }
        return self::isMemberPremiumMeta($obj->metadata ?? null);
    }

    /**
     * List subscribers for admin (tenant-scoped).
     *
     * @return array{rows:array,total:int}
     */
    public static function listSubscribersForAdmin(int $tenantId, int $page = 1, int $perPage = 25, ?string $statusFilter = null): array
    {
        $where = "ms.tenant_id = ?";
        $params = [$tenantId];

        if ($statusFilter && in_array($statusFilter, ['active', 'past_due', 'canceled', 'grace', 'trialing', 'incomplete'], true)) {
            $where .= " AND ms.status = ?";
            $params[] = $statusFilter;
        }

        $total = (int) DB::selectOne(
            "SELECT COUNT(*) AS c FROM member_subscriptions ms WHERE {$where}",
            $params
        )->c;

        $offset = max(0, ($page - 1) * $perPage);
        $rows = DB::select(
            "SELECT ms.id, ms.user_id, ms.tier_id, ms.status, ms.billing_interval,
                    ms.current_period_end, ms.canceled_at, ms.grace_period_ends_at,
                    ms.created_at,
                    mpt.name AS tier_name, mpt.slug AS tier_slug,
                    u.email, u.name AS user_name, u.first_name
             FROM member_subscriptions ms
             JOIN member_premium_tiers mpt ON mpt.id = ms.tier_id
             LEFT JOIN users u ON u.id = ms.user_id
             WHERE {$where}
             ORDER BY ms.created_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        return [
            'rows' => array_map(fn ($r) => (array) $r, $rows),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Count active subscribers per tier (admin overview).
     */
    public static function subscriberCountsByTier(int $tenantId): array
    {
        $rows = DB::select(
            "SELECT tier_id, COUNT(*) AS active_count
             FROM member_subscriptions
             WHERE tenant_id = ? AND status IN ('active','trialing','past_due','grace')
             GROUP BY tier_id",
            [$tenantId]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->tier_id] = (int) $r->active_count;
        }
        return $out;
    }

    private static function recordEvent(int $subId, int $tenantId, string $type, ?string $eventId, object $payload): void
    {
        try {
            DB::table('member_subscription_events')->insertOrIgnore([
                'subscription_id' => $subId,
                'tenant_id' => $tenantId,
                'event_type' => $type,
                'stripe_event_id' => $eventId,
                'payload' => json_encode($payload),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('MemberPremiumService::recordEvent failed: ' . $e->getMessage());
        }
    }

    private static function lookupTenantBySub(int $subId): int
    {
        $row = DB::selectOne("SELECT tenant_id FROM member_subscriptions WHERE id = ?", [$subId]);
        return (int) ($row->tenant_id ?? 0);
    }

    private static function statusIsEntitled(string $status, ?string $graceEnd): bool
    {
        if (in_array($status, ['active', 'trialing'], true)) {
            return true;
        }
        if ($status === 'past_due' && $graceEnd && strtotime($graceEnd) > time()) {
            return true;
        }
        if ($status === 'grace' && $graceEnd && strtotime($graceEnd) > time()) {
            return true;
        }
        return false;
    }

    private static function decodeFeatures($value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? array_values($decoded) : [];
        }
        return [];
    }

    private static function normalizeTier(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'tenant_id' => (int) $row->tenant_id,
            'slug' => $row->slug,
            'name' => $row->name,
            'description' => $row->description,
            'monthly_price_cents' => (int) $row->monthly_price_cents,
            'yearly_price_cents' => (int) $row->yearly_price_cents,
            'stripe_price_id_monthly' => $row->stripe_price_id_monthly,
            'stripe_price_id_yearly' => $row->stripe_price_id_yearly,
            'features' => self::decodeFeatures($row->features),
            'sort_order' => (int) $row->sort_order,
            'is_active' => (bool) $row->is_active,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }
}

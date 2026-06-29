<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DonationOperationsService
{
    /**
     * @return array<string, mixed>
     */
    public static function overview(int $tenantId): array
    {
        $hasGiftAid = Schema::hasColumn('vol_donations', 'gift_aid_claim_status');
        $hasRoute = Schema::hasColumn('vol_donations', 'payment_route');
        $hasAccount = Schema::hasColumn('vol_donations', 'stripe_account_id');

        $base = DB::table('vol_donations')->where('tenant_id', $tenantId);

        $completed = (clone $base)->where('status', 'completed');
        $refunded = (clone $base)->where('status', 'refunded');
        $pending = (clone $base)->where('status', 'pending');
        $failed = (clone $base)->where('status', 'failed');

        $platform = (clone $completed);
        if ($hasRoute) {
            $platform->where(function ($query) use ($hasAccount): void {
                $query->where('payment_route', DonationStripeAccountService::ROUTE_PLATFORM_DEFAULT);
                if ($hasAccount) {
                    $query->orWhereNull('stripe_account_id');
                }
            });
        } elseif ($hasAccount) {
            $platform->whereNull('stripe_account_id');
        }

        $connect = (clone $completed);
        if ($hasRoute) {
            $connect->where('payment_route', DonationStripeAccountService::ROUTE_TENANT_CONNECT);
        } elseif ($hasAccount) {
            $connect->whereNotNull('stripe_account_id');
        } else {
            $connect->whereRaw('1 = 0');
        }

        $giftAidReady = (clone $completed);
        if ($hasGiftAid) {
            $giftAidReady->where('gift_aid_claim_status', 'ready');
        } else {
            $giftAidReady->whereRaw('1 = 0');
        }

        return [
            'totals' => [
                'completed_cents' => self::sumCents($completed),
                'refunded_cents' => self::sumCents($refunded),
                'pending_cents' => self::sumCents($pending),
                'failed_count' => (int) $failed->count(),
            ],
            'routing' => [
                'platform_fallback_cents' => self::sumCents($platform),
                'tenant_connect_cents' => self::sumCents($connect),
                'platform_fallback_count' => (int) $platform->count(),
                'tenant_connect_count' => (int) $connect->count(),
            ],
            'gift_aid' => [
                'ready_cents' => self::sumCents($giftAidReady),
                'ready_count' => (int) $giftAidReady->count(),
            ],
            'recurring' => [
                'active_count' => self::subscriptionCount($tenantId, ['active', 'trialing']),
                'past_due_count' => self::subscriptionCount($tenantId, ['past_due', 'grace']),
                'canceled_count' => self::subscriptionCount($tenantId, ['canceled']),
            ],
            'disputes' => [
                'open_count' => self::openDisputeCount($tenantId),
            ],
            'receipts' => [
                'failed_email_count' => Schema::hasColumn('vol_donations', 'receipt_email_failed_at')
                    ? (int) (clone $base)->whereNotNull('receipt_email_failed_at')->count()
                    : 0,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function giftAidExportRows(int $tenantId): array
    {
        if (!Schema::hasColumn('vol_donations', 'gift_aid_claim_status')) {
            return [];
        }

        return DB::table('vol_donations')
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where('gift_aid_claim_status', 'ready')
            ->orderBy('created_at')
            ->get([
                'id',
                'donor_name',
                'donor_email',
                'amount',
                'currency',
                'gift_aid_declaration_name',
                'gift_aid_address_line1',
                'gift_aid_address_line2',
                'gift_aid_town',
                'gift_aid_postcode',
                'gift_aid_country',
                'gift_aid_consented_at',
                'created_at',
            ])
            ->map(fn ($row): array => [
                'donation_id' => (int) $row->id,
                'donor_name' => (string) ($row->donor_name ?? ''),
                'donor_email' => (string) ($row->donor_email ?? ''),
                'amount' => number_format((float) $row->amount, 2, '.', ''),
                'currency' => strtoupper((string) $row->currency),
                'declaration_name' => (string) ($row->gift_aid_declaration_name ?? ''),
                'address_line1' => (string) ($row->gift_aid_address_line1 ?? ''),
                'address_line2' => (string) ($row->gift_aid_address_line2 ?? ''),
                'town' => (string) ($row->gift_aid_town ?? ''),
                'postcode' => (string) ($row->gift_aid_postcode ?? ''),
                'country' => (string) ($row->gift_aid_country ?? ''),
                'consented_at' => (string) ($row->gift_aid_consented_at ?? ''),
                'donation_date' => (string) ($row->created_at ?? ''),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function annualReceiptRows(int $tenantId, int $year): array
    {
        $query = DB::table('vol_donations')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['completed', 'refunded'])
            ->whereYear('created_at', $year)
            ->orderBy('created_at');

        $columns = [
            'id',
            'user_id',
            'donor_name',
            'donor_email',
            'amount',
            'currency',
            'status',
            'payment_method',
            'created_at',
        ];
        foreach (['fund_code', 'payment_route', 'stripe_account_id', 'stripe_payment_intent_id', 'gift_aid_claim_status'] as $column) {
            if (Schema::hasColumn('vol_donations', $column)) {
                $columns[] = $column;
            }
        }

        return $query->get($columns)
            ->map(fn ($row): array => [
                'donation_id' => (int) $row->id,
                'user_id' => $row->user_id !== null ? (int) $row->user_id : null,
                'donor_name' => (string) ($row->donor_name ?? ''),
                'donor_email' => (string) ($row->donor_email ?? ''),
                'amount' => number_format((float) $row->amount, 2, '.', ''),
                'currency' => strtoupper((string) $row->currency),
                'status' => (string) $row->status,
                'payment_method' => (string) ($row->payment_method ?? ''),
                'fund_code' => (string) ($row->fund_code ?? 'general'),
                'payment_route' => (string) ($row->payment_route ?? DonationStripeAccountService::ROUTE_PLATFORM_DEFAULT),
                'stripe_account_id' => $row->stripe_account_id ?? null,
                'stripe_payment_intent_id' => $row->stripe_payment_intent_id ?? null,
                'gift_aid_claim_status' => (string) ($row->gift_aid_claim_status ?? 'not_eligible'),
                'donation_date' => (string) ($row->created_at ?? ''),
            ])
            ->values()
            ->all();
    }

    public static function recordStripeDispute(object $dispute): void
    {
        if (!Schema::hasTable('donation_disputes')) {
            return;
        }

        $disputeId = (string) ($dispute->id ?? '');
        if ($disputeId === '') {
            return;
        }

        $paymentIntentId = (string) ($dispute->payment_intent ?? '');
        $metaTenantId = isset($dispute->metadata->nexus_tenant_id)
            ? (int) $dispute->metadata->nexus_tenant_id
            : null;

        $donation = null;
        if ($paymentIntentId !== '') {
            $query = DB::table('vol_donations')->where('stripe_payment_intent_id', $paymentIntentId);
            if ($metaTenantId) {
                $query->where('tenant_id', $metaTenantId);
            }
            $donation = $query->first();
        }

        $tenantId = (int) ($donation->tenant_id ?? $metaTenantId ?? 0);
        if ($tenantId <= 0) {
            return;
        }

        $dueBy = $dispute->evidence_details->due_by ?? null;
        DB::table('donation_disputes')->updateOrInsert(
            ['stripe_dispute_id' => $disputeId],
            [
                'tenant_id' => $tenantId,
                'vol_donation_id' => $donation->id ?? null,
                'payment_intent_id' => $paymentIntentId !== '' ? $paymentIntentId : null,
                'charge_id' => $dispute->charge ?? null,
                'amount' => (int) ($dispute->amount ?? 0),
                'currency' => strtolower((string) ($dispute->currency ?? 'gbp')),
                'status' => (string) ($dispute->status ?? 'needs_response'),
                'reason' => $dispute->reason ?? null,
                'evidence_due_at' => is_numeric($dueBy) ? date('Y-m-d H:i:s', (int) $dueBy) : null,
                'payment_route' => (string) ($donation->payment_route ?? DonationStripeAccountService::ROUTE_PLATFORM_DEFAULT),
                'stripe_account_id' => $donation->stripe_account_id ?? null,
                'payload' => json_encode($dispute),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function disputes(int $tenantId, int $limit = 50): array
    {
        if (!Schema::hasTable('donation_disputes')) {
            return [];
        }

        return DB::table('donation_disputes')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->limit(max(1, min($limit, 200)))
            ->get()
            ->map(fn ($row): array => (array) $row)
            ->values()
            ->all();
    }

    private static function sumCents($query): int
    {
        return (int) round(((float) $query->sum('amount')) * 100);
    }

    /**
     * @param array<int, string> $statuses
     */
    private static function subscriptionCount(int $tenantId, array $statuses): int
    {
        if (!Schema::hasTable('member_subscriptions')) {
            return 0;
        }

        return (int) DB::table('member_subscriptions')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', $statuses)
            ->count();
    }

    private static function openDisputeCount(int $tenantId): int
    {
        if (!Schema::hasTable('donation_disputes')) {
            return 0;
        }

        return (int) DB::table('donation_disputes')
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['won', 'lost', 'warning_closed'])
            ->count();
    }
}

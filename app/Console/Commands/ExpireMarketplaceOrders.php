<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\TenantContext;
use App\Models\MarketplaceOrder;
use App\Services\MarketplaceOrderService;
use App\Services\MarketplacePaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** Release inventory and discounts reserved by abandoned marketplace checkouts. */
final class ExpireMarketplaceOrders extends Command
{
    protected $signature = 'marketplace:expire-pending-orders
        {--limit=250 : Maximum pending orders processed}';

    protected $description = 'Cancel expired unpaid marketplace orders after reconciling Stripe state.';

    public function handle(): int
    {
        if (! Schema::hasTable('marketplace_orders')
            || ! Schema::hasColumn('marketplace_orders', 'payment_expires_at')) {
            $this->warn('Marketplace checkout expiry schema is unavailable.');
            return self::SUCCESS;
        }

        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 1000],
        ]);
        if ($limit === false) {
            $this->error('The --limit option must be an integer between 1 and 1000.');
            return self::INVALID;
        }

        $due = MarketplaceOrder::withoutGlobalScopes()
            ->where('status', 'pending_payment')
            ->whereNotNull('payment_expires_at')
            ->where('payment_expires_at', '<=', now())
            ->orderBy('payment_expires_at')
            ->orderBy('id')
            ->limit((int) $limit)
            ->get();

        $previousTenantId = TenantContext::currentId();
        $expired = 0;
        $deferred = 0;
        $errors = 0;
        try {
            foreach ($due as $order) {
                try {
                    if (! TenantContext::setById((int) $order->tenant_id)) {
                        $deferred++;
                        continue;
                    }
                    $fresh = MarketplaceOrder::find($order->id);
                    if (! $fresh || $fresh->status !== 'pending_payment') {
                        continue;
                    }
                    if (! MarketplacePaymentService::prepareOrderForExpiry($fresh)) {
                        $deferred++;
                        continue;
                    }

                    MarketplaceOrderService::cancel($fresh, 'payment_expired');
                    $expired++;
                } catch (Throwable $exception) {
                    $errors++;
                    Log::error('[MarketplaceOrderExpiry] order failure', [
                        'order_id' => $order->id,
                        'tenant_id' => $order->tenant_id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }

            if (Schema::hasTable('caring_loyalty_redemptions')
                && Schema::hasColumn('caring_loyalty_redemptions', 'expires_at')) {
                DB::table('caring_loyalty_redemptions')
                    ->where('status', 'pending')
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<=', now())
                    ->update([
                        'status' => 'reversed',
                        'updated_at' => now(),
                    ]);
            }
        } finally {
            TenantContext::restoreAfterScopedListener($previousTenantId);
        }

        $this->info(sprintf(
            'Marketplace pending orders: expired=%d deferred=%d errors=%d',
            $expired,
            $deferred,
            $errors,
        ));

        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }
}

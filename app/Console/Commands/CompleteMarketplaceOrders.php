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
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/** Complete delivered marketplace orders after the confirmation window. */
final class CompleteMarketplaceOrders extends Command
{
    protected $signature = 'marketplace:complete-orders {--limit=200}';

    protected $description = 'Complete delivered marketplace orders whose confirmation window has elapsed';

    public function handle(): int
    {
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $orders = MarketplaceOrder::withoutGlobalScopes()
            ->where('status', 'delivered')
            ->whereNotNull('auto_complete_at')
            ->where('auto_complete_at', '<=', now())
            ->orderBy('auto_complete_at')
            ->limit($limit)
            ->get(['id', 'tenant_id']);

        $completed = 0;
        foreach ($orders as $candidate) {
            try {
                TenantContext::runForTenant((int) $candidate->tenant_id, function () use ($candidate, &$completed): void {
                    $order = MarketplaceOrder::query()
                        ->whereKey($candidate->id)
                        ->where('status', 'delivered')
                        ->where('auto_complete_at', '<=', now())
                        ->first();
                    if ($order) {
                        $completedOrder = MarketplaceOrderService::complete($order);
                        if ($completedOrder->status === 'completed') {
                            $completed++;
                        }
                    }
                });
            } catch (\Throwable $exception) {
                Log::error('Marketplace order auto-completion failed', [
                    'order_id' => $candidate->id,
                    'tenant_id' => $candidate->tenant_id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->info((string) $completed);

        return self::SUCCESS;
    }
}

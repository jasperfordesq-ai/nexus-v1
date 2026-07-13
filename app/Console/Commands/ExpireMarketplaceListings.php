<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\TenantContext;
use App\Models\MarketplaceListing;
use App\Services\SearchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** Mark active marketplace listings expired once their advertised lifetime ends. */
final class ExpireMarketplaceListings extends Command
{
    protected $signature = 'marketplace:expire-listings
        {--limit=500 : Maximum listings processed per run}';

    protected $description = 'Expire active marketplace listings whose expires_at timestamp has passed.';

    public function handle(): int
    {
        if (! Schema::hasTable('marketplace_listings')
            || ! Schema::hasColumn('marketplace_listings', 'expires_at')) {
            $this->warn('Marketplace listing expiry schema is unavailable.');
            return self::SUCCESS;
        }

        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 5000],
        ]);
        if ($limit === false) {
            $this->error('The --limit option must be an integer between 1 and 5000.');
            return self::INVALID;
        }

        $due = MarketplaceListing::withoutGlobalScopes()
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('expires_at')
            ->orderBy('id')
            ->limit((int) $limit)
            ->get(['id', 'tenant_id']);

        $previousTenantId = TenantContext::currentId();
        $expired = 0;
        $errors = 0;

        try {
            foreach ($due as $listing) {
                try {
                    if (! TenantContext::setById((int) $listing->tenant_id)) {
                        $errors++;
                        continue;
                    }

                    $updated = MarketplaceListing::query()
                        ->whereKey($listing->id)
                        ->where('status', 'active')
                        ->whereNotNull('expires_at')
                        ->where('expires_at', '<=', now())
                        ->update(['status' => 'expired']);

                    if ($updated === 1) {
                        SearchService::removeMarketplaceListing((int) $listing->id);
                        $expired++;
                    }
                } catch (Throwable $exception) {
                    $errors++;
                    Log::error('[MarketplaceListingExpiry] listing failure', [
                        'listing_id' => $listing->id,
                        'tenant_id' => $listing->tenant_id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        } finally {
            TenantContext::restoreAfterScopedListener($previousTenantId);
        }

        $this->info(sprintf('Marketplace listings: expired=%d errors=%d', $expired, $errors));

        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class MarketplaceListingPrerenderObserver extends PrerenderInvalidationObserver
{
    protected function routesFor(Model $model): array
    {
        // The map route redirects unless both build-time and tenant map
        // capabilities are enabled, so it is deliberately not a snapshot.
        $routes = ['/marketplace', '/marketplace/free'];
        $id = $model->getKey();
        if ($id !== null) $routes[] = '/marketplace/' . $id;

        // Category pages contain their listings. On a category move, refresh
        // both the old and new category so neither keeps a stale card.
        $categoryIds = array_values(array_unique(array_filter([
            (int) ($model->category_id ?? 0),
            (int) ($model->getRawOriginal('category_id') ?? 0),
        ], static fn (int $categoryId): bool => $categoryId > 0)));
        $tenantId = (int) ($model->tenant_id ?? 0);
        if ($tenantId > 0 && $categoryIds !== []) {
            $slugs = DB::table('marketplace_categories')
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $categoryIds)
                ->pluck('slug');
            foreach ($slugs as $slug) {
                if (is_string($slug) && $slug !== '') {
                    $routes[] = '/marketplace/category/' . $slug;
                }
            }
        }

        return $routes;
    }
}

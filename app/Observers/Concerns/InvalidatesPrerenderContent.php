<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Observers\Concerns;

use App\Services\PrerenderContentInvalidator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Failure-isolated bridge from Eloquent lifecycle events to prerender refreshes.
 *
 * Content writes are authoritative and must never fail just because the
 * best-effort prerender side channel is temporarily unavailable.
 */
trait InvalidatesPrerenderContent
{
    /**
     * @param list<string> $routes
     */
    final protected function refreshPrerenderRoutes(Model $model, array $routes, string $event): void
    {
        $tenantId = (int) ($model->getAttribute('tenant_id') ?? 0);
        $routes = array_values(array_unique(array_filter(
            $routes,
            static fn (mixed $route): bool => is_string($route) && $route !== ''
        )));

        if ($tenantId <= 0 || $routes === []) {
            return;
        }

        try {
            // PrerenderContentInvalidator is the single entry point: it clears
            // the tenant sitemap/inventory caches before deleting and requeueing
            // the affected snapshots.
            app(PrerenderContentInvalidator::class)->refreshRoutes($tenantId, $routes);
        } catch (\Throwable $e) {
            Log::warning('Prerender content observer failed', [
                'model' => $model::class,
                'event' => $event,
                'id' => $model->getKey(),
                'tenant_id' => $tenantId,
                'routes' => $routes,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @return list<string> */
    final protected function originalAndCurrentString(Model $model, string $attribute): array
    {
        $values = [$model->getOriginal($attribute), $model->getAttribute($attribute)];

        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $value): string => trim((string) $value), $values),
            static fn (string $value): bool => $value !== ''
        )));
    }

    /** @return list<int> */
    final protected function originalAndCurrentId(Model $model, string $attribute): array
    {
        $values = array_map('intval', [$model->getOriginal($attribute), $model->getAttribute($attribute)]);

        return array_values(array_unique(array_filter(
            $values,
            static fn (int $value): bool => $value > 0
        )));
    }
}

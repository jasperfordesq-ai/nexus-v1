<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Observers;

use App\Services\PrerenderService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Generic prerender-cache invalidation observer.
 *
 * Subclasses define `routesFor()` returning the public routes that depend on
 * a given model instance. Whenever a model is saved or deleted, those routes
 * are invalidated and re-rendered at low priority.
 *
 * Why a base class and not an interface: the observer methods (saved/deleted)
 * need to be present even if a model has no per-instance public URL — the
 * base class always invalidates the index route, which is the common case.
 */
abstract class PrerenderInvalidationObserver
{
    public function saved(Model $model): void
    {
        $this->dispatch($model, 'saved');
    }

    public function deleted(Model $model): void
    {
        $this->dispatch($model, 'deleted');
    }

    /**
     * Return the routes that should be re-rendered when this model changes.
     * Always include the index (e.g. /blog) plus the detail page if any.
     *
     * @return array<int,string>
     */
    abstract protected function routesFor(Model $model): array;

    protected function tenantId(Model $model): ?int
    {
        $tid = $model->tenant_id ?? null;
        return $tid !== null ? (int) $tid : null;
    }

    private function dispatch(Model $model, string $event): void
    {
        try {
            $tenantId = $this->tenantId($model);
            if ($tenantId === null || $tenantId === 0) return;
            $routes = $this->routesFor($model);
            if (empty($routes)) return;
            app(PrerenderService::class)->invalidateRoutes($tenantId, $routes, true);
        } catch (\Throwable $e) {
            // Observer failures must NEVER block model writes. Log and move on.
            Log::warning('Prerender invalidation failed', [
                'model'  => $model::class,
                'event'  => $event,
                'id'     => $model->getKey(),
                'error'  => $e->getMessage(),
            ]);
        }
    }
}

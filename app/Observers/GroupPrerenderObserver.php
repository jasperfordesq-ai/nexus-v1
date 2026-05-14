<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;

class GroupPrerenderObserver extends PrerenderInvalidationObserver
{
    protected function routesFor(Model $model): array
    {
        // Only public groups are in the sitemap, so invalidating private group
        // routes is a no-op (the snapshot won't exist). Cheap to attempt either
        // way — the deletion is idempotent.
        $routes = ['/groups'];
        $id = $model->getKey();
        if ($id !== null) $routes[] = '/groups/' . $id;
        return $routes;
    }
}

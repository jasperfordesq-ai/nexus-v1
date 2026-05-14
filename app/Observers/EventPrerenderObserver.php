<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;

class EventPrerenderObserver extends PrerenderInvalidationObserver
{
    protected function routesFor(Model $model): array
    {
        $routes = ['/events'];
        $id = $model->getKey();
        if ($id !== null) $routes[] = '/events/' . $id;
        return $routes;
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\GovukAlpha;

use Illuminate\Support\Facades\Route;
use Tests\Laravel\TestCase;

class GovukAlphaCsrfMiddlewareTest extends TestCase
{
    public function test_state_changing_alpha_routes_have_csrf_middleware(): void
    {
        $csrfMiddleware = array_values(array_filter([
            'Illuminate\Foundation\Http\Middleware\ValidateCsrfToken',
            'Illuminate\Foundation\Http\Middleware\VerifyCsrfToken',
        ], static fn (string $class): bool => class_exists($class)));

        $stateChangingRoutes = collect(Route::getRoutes())
            ->filter(static fn ($route): bool => str_starts_with((string) $route->getName(), 'govuk-alpha.')
                && in_array('POST', $route->methods(), true));

        $this->assertNotEmpty($stateChangingRoutes);

        foreach ($stateChangingRoutes as $route) {
            $middleware = $route->gatherMiddleware();
            $hasCsrfMiddleware = in_array('web', $middleware, true)
                || count(array_intersect($csrfMiddleware, $middleware)) > 0;

            $this->assertTrue(
                $hasCsrfMiddleware,
                sprintf('Route %s must include CSRF middleware.', $route->getName())
            );
        }
    }
}

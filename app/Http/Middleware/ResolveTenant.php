<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Nexus\Core\TenantContext;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!TenantContext::getId()) {
            TenantContext::resolve();
        }
        return $next($request);
    }
}

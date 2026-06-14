<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

/**
 * BroadcastServiceProvider
 *
 * Configures Pusher broadcasting and loads the channel authorization routes.
 * All private/presence channels are tenant-scoped to prevent cross-tenant
 * data leakage via WebSockets.
 */
class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Registering channels resolves the default broadcaster at boot (the
        // installed BroadcastManager lacks a channel() method, so the call is
        // proxied to driver()). If broadcasting is misconfigured — e.g. the
        // default is 'pusher' but no PUSHER key is set (dev / missing env /
        // stale config cache) — that resolution throws and would otherwise 500
        // every page. Real-time broadcasting is an enhancement, so degrade
        // gracefully instead of taking the whole site down.
        try {
            Broadcast::routes(['middleware' => ['api']]);

            if (file_exists(base_path('routes/channels.php'))) {
                require base_path('routes/channels.php');
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Broadcasting channels not registered: ' . $e->getMessage());
        }
    }
}

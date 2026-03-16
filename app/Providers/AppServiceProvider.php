<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Providers;

use App\Models\Listing;
use App\Services\ListingService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ListingService::class, function ($app) {
            return new ListingService(new Listing());
        });
    }

    public function boot(): void
    {
        //
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Providers;

use App\Providers\BroadcastServiceProvider;
use Illuminate\Support\ServiceProvider;
use Tests\Laravel\TestCase;

class BroadcastServiceProviderTest extends TestCase
{
    public function test_extends_service_provider(): void
    {
        $this->assertTrue(
            is_subclass_of(BroadcastServiceProvider::class, ServiceProvider::class)
        );
    }

    public function test_provider_can_be_instantiated(): void
    {
        $provider = new BroadcastServiceProvider($this->app);
        $this->assertInstanceOf(BroadcastServiceProvider::class, $provider);
    }

    public function test_boot_does_not_throw(): void
    {
        $provider = new BroadcastServiceProvider($this->app);

        // boot() should run without exceptions
        $provider->boot();

        $this->assertTrue(true);
    }
}

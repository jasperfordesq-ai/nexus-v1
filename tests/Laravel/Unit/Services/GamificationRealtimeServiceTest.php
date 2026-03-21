<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\GamificationRealtimeService;
use App\Services\PusherService;

class GamificationRealtimeServiceTest extends TestCase
{
    private GamificationRealtimeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GamificationRealtimeService();
    }

    public function test_broadcastBadgeEarned_returns_boolean(): void
    {
        // Without Pusher configured, this should fail gracefully
        $result = $this->service->broadcastBadgeEarned(1, [
            'key' => 'first_exchange',
            'name' => 'First Exchange',
            'icon' => '🏆',
            'xp' => 25,
        ]);
        $this->assertIsBool($result);
    }

    public function test_broadcastXPGained_returns_boolean(): void
    {
        $result = $this->service->broadcastXPGained(1, 50, 'Exchange completed', [
            'total_xp' => 200,
            'level' => 3,
            'progress' => 0.6,
        ]);
        $this->assertIsBool($result);
    }
}

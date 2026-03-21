<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Models\User;
use App\Services\NexusScoreService;
use Tests\Laravel\TestCase;

class NexusScoreServiceTest extends TestCase
{
    private NexusScoreService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NexusScoreService(new User());
    }

    public function test_score_constants(): void
    {
        $this->assertSame(250, NexusScoreService::MAX_ENGAGEMENT);
        $this->assertSame(200, NexusScoreService::MAX_QUALITY);
        $this->assertSame(200, NexusScoreService::MAX_VOLUNTEER);
        $this->assertSame(150, NexusScoreService::MAX_ACTIVITY);
        $this->assertSame(100, NexusScoreService::MAX_BADGES);
        $this->assertSame(100, NexusScoreService::MAX_IMPACT);
    }

    public function test_calculateTier_novice(): void
    {
        $tier = $this->service->calculateTier(50);
        $this->assertSame('Novice', $tier['name']);
    }

    public function test_calculateTier_beginner(): void
    {
        $tier = $this->service->calculateTier(200);
        $this->assertSame('Beginner', $tier['name']);
    }

    public function test_calculateTier_developing(): void
    {
        $tier = $this->service->calculateTier(300);
        $this->assertSame('Developing', $tier['name']);
    }

    public function test_calculateTier_intermediate(): void
    {
        $tier = $this->service->calculateTier(400);
        $this->assertSame('Intermediate', $tier['name']);
    }

    public function test_calculateTier_proficient(): void
    {
        $tier = $this->service->calculateTier(500);
        $this->assertSame('Proficient', $tier['name']);
    }

    public function test_calculateTier_advanced(): void
    {
        $tier = $this->service->calculateTier(600);
        $this->assertSame('Advanced', $tier['name']);
    }

    public function test_calculateTier_expert(): void
    {
        $tier = $this->service->calculateTier(700);
        $this->assertSame('Expert', $tier['name']);
    }

    public function test_calculateTier_elite(): void
    {
        $tier = $this->service->calculateTier(800);
        $this->assertSame('Elite', $tier['name']);
    }

    public function test_calculateTier_legendary(): void
    {
        $tier = $this->service->calculateTier(900);
        $this->assertSame('Legendary', $tier['name']);
    }

    public function test_calculateTier_returns_color_and_icon(): void
    {
        $tier = $this->service->calculateTier(500);
        $this->assertArrayHasKey('color', $tier);
        $this->assertArrayHasKey('icon', $tier);
        $this->assertNotEmpty($tier['color']);
    }
}

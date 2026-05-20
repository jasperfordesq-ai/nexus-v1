<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\GamificationEmailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GamificationEmailServiceTest extends TestCase
{
    private GamificationEmailService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GamificationEmailService();
    }

    public function test_sendWeeklyDigests_returns_expected_structure(): void
    {
        DB::shouldReceive('table->where->select->get')->andReturn(collect([]));

        $result = $this->service->sendWeeklyDigests();
        $this->assertArrayHasKey('sent', $result);
        $this->assertArrayHasKey('skipped', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertEquals(0, $result['sent']);
    }

    public function test_sendWeeklyDigests_handles_tenant_query_failure(): void
    {
        DB::shouldReceive('table->where->select->get')->andThrow(new \Exception('DB error'));
        Log::shouldReceive('error')->once();

        $result = $this->service->sendWeeklyDigests();
        $this->assertEquals(1, $result['errors']);
    }

    public function test_gamification_email_fallback_copy_uses_translations(): void
    {
        $source = file_get_contents(app_path('Services/GamificationEmailService.php'));

        $this->assertStringContainsString("__('emails.common.fallback_name')", $source);
        $this->assertStringContainsString("__('emails.gamification_digest.badge_fallback')", $source);
        $this->assertStringContainsString("__('emails.gamification_milestone.badge_fallback')", $source);
        $this->assertStringNotContainsString("?: 'there'", $source);
        $this->assertStringNotContainsString("?? 'Badge'", $source);
        $this->assertStringNotContainsString("?? 'a new badge'", $source);
    }

    public function test_gamification_milestone_email_service_is_wired_to_badge_and_level_events(): void
    {
        $source = file_get_contents(app_path('Services/GamificationService.php'));

        $this->assertStringContainsString("sendMilestoneEmail(\$userId, 'badge_earned'", $source);
        $this->assertStringContainsString("sendMilestoneEmail(\$userId, 'level_up'", $source);
        $this->assertStringContainsString('GamificationService: badge milestone email failed', $source);
        $this->assertStringContainsString('GamificationService: level milestone email failed', $source);
    }
}

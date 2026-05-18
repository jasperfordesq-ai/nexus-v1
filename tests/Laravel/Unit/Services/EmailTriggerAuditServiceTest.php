<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\EmailTriggerAuditService;
use Tests\Laravel\TestCase;

class EmailTriggerAuditServiceTest extends TestCase
{
    public function test_event_matrix_covers_critical_enterprise_email_flows(): void
    {
        $matrix = app(EmailTriggerAuditService::class)->eventMatrix();
        $keys = array_map(
            fn (array $row): string => $row['module'] . ':' . $row['event'] . ':' . $row['category'],
            $matrix
        );

        $this->assertContains('auth:password_reset_requested:password_reset', $keys);
        $this->assertContains('registration:email_verification_required:email_verification', $keys);
        $this->assertContains('groups:group_email_invite:group_invite', $keys);
        $this->assertContains('safeguarding:incident_flag_vetting_guardian_training:safeguarding', $keys);
        $this->assertContains('newsletter:newsletter_queue_dispatch:newsletter', $keys);
    }

    public function test_run_returns_score_and_issue_structure(): void
    {
        $result = app(EmailTriggerAuditService::class)->run(2, 24);

        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('matrix', $result);
        $this->assertArrayHasKey('issues', $result);
        $this->assertArrayHasKey('issues_by_severity', $result);
        $this->assertGreaterThanOrEqual(0, $result['score']);
        $this->assertLessThanOrEqual(1000, $result['score']);
    }
}

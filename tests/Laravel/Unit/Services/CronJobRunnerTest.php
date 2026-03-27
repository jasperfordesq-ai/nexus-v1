<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CronJobRunnerTest extends TestCase
{
    public function test_cron_job_runner_exists(): void
    {
        $this->markTestIncomplete(
            'CronJobRunner orchestrates many external services (Mailer, Meilisearch, FCM, etc.) '
            . 'and requires a full integration test environment.'
        );
    }
}

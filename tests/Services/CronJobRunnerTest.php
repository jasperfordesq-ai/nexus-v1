<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\CronJobRunner;

class CronJobRunnerTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(CronJobRunner::class));
    }

    public function testCanBeInstantiated(): void
    {
        $runner = new CronJobRunner();
        $this->assertInstanceOf(CronJobRunner::class, $runner);
    }

    public function testCheckAccessMethodIsPrivate(): void
    {
        $ref = new \ReflectionMethod(CronJobRunner::class, 'checkAccess');
        $this->assertTrue($ref->isPrivate());
    }

    public function testEnsureLogsTableMethodIsPrivate(): void
    {
        $ref = new \ReflectionMethod(CronJobRunner::class, 'ensureLogsTable');
        $this->assertTrue($ref->isPrivate());
    }

    public function testIsInternalRunMethodIsPrivate(): void
    {
        $ref = new \ReflectionMethod(CronJobRunner::class, 'isInternalRun');
        $this->assertTrue($ref->isPrivate());
    }

    public function testIsInternalRunReturnsFalseByDefault(): void
    {
        $runner = new CronJobRunner();
        $result = $this->callPrivateMethod($runner, 'isInternalRun');
        $this->assertFalse($result);
    }

    public function testStartJobSetsJobState(): void
    {
        $runner = new CronJobRunner();
        $this->callPrivateMethod($runner, 'startJob', ['test_job']);

        $currentJobId = $this->getPrivateProperty($runner, 'currentJobId');
        $jobStartTime = $this->getPrivateProperty($runner, 'jobStartTime');

        $this->assertEquals('test_job', $currentJobId);
        $this->assertIsFloat($jobStartTime);
    }

    public function testLogJobResetsStateAfterLogging(): void
    {
        $runner = new CronJobRunner();
        // First set up job state
        $this->setPrivateProperty($runner, 'currentJobId', 'test_job');
        $this->setPrivateProperty($runner, 'jobStartTime', microtime(true));

        // logJob should reset state (even if DB insert fails in test env)
        $this->callPrivateMethod($runner, 'logJob', ['success', 'test output']);

        $currentJobId = $this->getPrivateProperty($runner, 'currentJobId');
        $jobStartTime = $this->getPrivateProperty($runner, 'jobStartTime');

        $this->assertNull($currentJobId);
        $this->assertNull($jobStartTime);
    }

    public function testLogJobSkipsWhenNoJobStarted(): void
    {
        $runner = new CronJobRunner();
        // Should not throw when called without startJob
        $this->callPrivateMethod($runner, 'logJob', ['success', 'output']);

        $this->assertNull($this->getPrivateProperty($runner, 'currentJobId'));
    }

    public function testAllPublicCronMethodsExist(): void
    {
        $methods = [
            'dailyDigest', 'weeklyDigest', 'runInstantQueue',
            'processNewsletters', 'processRecurring', 'processNewsletterQueue',
            'cleanup', 'runAll', 'matchDigestDaily', 'matchDigestWeekly',
            'notifyHotMatches', 'geocodeBatch', 'federationWeeklyDigest',
            'verificationReminders', 'expireVerifications',
            'purgeVerificationSessions', 'volunteerPreShiftReminders',
            'volunteerPostShiftFeedback', 'volunteerLapsedNudge',
            'volunteerExpiryWarnings', 'volunteerExpireConsents',
            'retryFailedWebhooks',
        ];
        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(CronJobRunner::class, $method),
                "Public method {$method} should exist on CronJobRunner"
            );
        }
    }

    public function testRunSubTaskCapturesOutput(): void
    {
        $runner = new CronJobRunner();
        $output = $this->callPrivateMethod($runner, 'runSubTask', [
            'test_subtask',
            function () { echo 'hello'; },
        ]);
        $this->assertStringContainsString('hello', $output);
    }

    public function testRunSubTaskCapturesErrors(): void
    {
        $runner = new CronJobRunner();
        $output = $this->callPrivateMethod($runner, 'runSubTask', [
            'test_subtask_error',
            function () { throw new \RuntimeException('test error'); },
        ]);
        $this->assertStringContainsString('Error:', $output);
        $this->assertStringContainsString('test error', $output);
    }

    public function testInitialJobStateIsNull(): void
    {
        $runner = new CronJobRunner();
        $this->assertNull($this->getPrivateProperty($runner, 'jobStartTime'));
        $this->assertNull($this->getPrivateProperty($runner, 'currentJobId'));
    }
}

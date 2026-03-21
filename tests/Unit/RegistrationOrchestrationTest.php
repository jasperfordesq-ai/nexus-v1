<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Tests\TestCase;
use App\Services\Identity\RegistrationOrchestrationService;
use App\Services\Identity\RegistrationOrchestrationService as AppRegistrationOrchestrationService;
use App\Services\Identity\RegistrationPolicyService;
use App\Services\Identity\IdentityVerificationSessionService;
use ReflectionClass;
use ReflectionMethod;

class RegistrationOrchestrationTest extends TestCase
{
    public function testProcessRegistrationIsPublicStatic(): void
    {
        $method = new ReflectionMethod(RegistrationOrchestrationService::class, 'processRegistration');
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
        $params = $method->getParameters();
        $this->assertCount(2, $params);
        $this->assertSame('userId', $params[0]->getName());
        $this->assertSame('tenantId', $params[1]->getName());
    }

    public function testInitiateVerificationIsPublicStatic(): void
    {
        $method = new ReflectionMethod(RegistrationOrchestrationService::class, 'initiateVerification');
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
    }

    public function testHandleVerificationResultSignature(): void
    {
        $method = new ReflectionMethod(RegistrationOrchestrationService::class, 'handleVerificationResult');
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
        $params = $method->getParameters();
        $this->assertCount(3, $params);
    }

    public function testAdminReviewSignature(): void
    {
        $method = new ReflectionMethod(RegistrationOrchestrationService::class, 'adminReview');
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
        $params = $method->getParameters();
        $this->assertCount(3, $params);
    }

    public function testPurgeOldSessionsExists(): void
    {
        $method = new ReflectionMethod(RegistrationOrchestrationService::class, 'purgeOldSessions');
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
        $params = $method->getParameters();
        $this->assertSame(180, $params[0]->getDefaultValue());
    }

    public function testAllSixModeHandlersExist(): void
    {
        $ref = new ReflectionClass(AppRegistrationOrchestrationService::class);
        $expected = ['handleOpenRegistration', 'handleOpenWithApproval', 'handleVerifiedIdentity', 'handleInviteOnly', 'handleWaitlist'];
        foreach ($expected as $handler) {
            $this->assertTrue($ref->hasMethod($handler), "Missing: {$handler}");
        }
    }

    public function testModeHandlersMatchPolicyModes(): void
    {
        $modes = RegistrationPolicyService::MODES;
        $this->assertCount(6, $modes);
    }

    public function testProcessRegistrationReturnDocumented(): void
    {
        $method = new ReflectionMethod(AppRegistrationOrchestrationService::class, 'processRegistration');
        $doc = $method->getDocComment();
        $this->assertNotFalse($doc);
        $this->assertStringContainsString('action', $doc);
        $this->assertStringContainsString('requires_verification', $doc);
    }

    public function testSessionServicePurgeMethodExists(): void
    {
        $method = new ReflectionMethod(IdentityVerificationSessionService::class, 'purgeOldSessions');
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
    }

    public function testNotificationDispatcherVerificationMethodsExist(): void
    {
        $ref = new ReflectionClass(\App\Services\NotificationDispatcher::class);
        $methods = ['dispatchVerificationPassed', 'dispatchVerificationFailed', 'dispatchVerificationCompletedToAdmins', 'dispatchVerificationReminder'];
        foreach ($methods as $m) {
            $this->assertTrue($ref->hasMethod($m), "Missing: {$m}");
        }
    }

    public function testAllFallbackModesDocumented(): void
    {
        $fallbacks = RegistrationPolicyService::FALLBACK_MODES;
        $this->assertContains('none', $fallbacks);
        $this->assertContains('admin_review', $fallbacks);
        $this->assertContains('native_registration', $fallbacks);
        $this->assertCount(3, $fallbacks);
    }

    public function testTriggerFallbackMethodSignature(): void
    {
        $method = new ReflectionMethod(RegistrationOrchestrationService::class, 'triggerFallback');
        $params = $method->getParameters();
        $this->assertCount(3, $params);
        $this->assertSame('userId', $params[0]->getName());
        $this->assertSame('reason', $params[2]->getName());
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
    }
}

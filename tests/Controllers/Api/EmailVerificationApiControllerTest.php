<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

use PHPUnit\Framework\TestCase;
use Nexus\Controllers\Api\EmailVerificationApiController;

class EmailVerificationApiControllerTest extends TestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(EmailVerificationApiController::class));
    }

    public function testExtendsBaseApiController(): void
    {
        $reflection = new \ReflectionClass(EmailVerificationApiController::class);
        $this->assertTrue($reflection->isSubclassOf(\Nexus\Controllers\Api\BaseApiController::class));
    }

    public function testHasVerifyEmailMethod(): void
    {
        $reflection = new \ReflectionClass(EmailVerificationApiController::class);
        $this->assertTrue($reflection->hasMethod('verifyEmail'));
        $this->assertTrue($reflection->getMethod('verifyEmail')->isPublic());
    }

    public function testHasResendVerificationMethod(): void
    {
        $reflection = new \ReflectionClass(EmailVerificationApiController::class);
        $this->assertTrue($reflection->hasMethod('resendVerification'));
        $this->assertTrue($reflection->getMethod('resendVerification')->isPublic());
    }
}

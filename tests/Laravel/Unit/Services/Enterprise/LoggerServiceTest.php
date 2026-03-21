<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\Enterprise;

use Tests\Laravel\TestCase;
use App\Services\Enterprise\LoggerService;

class LoggerServiceTest extends TestCase
{
    public function test_getInstance_returns_singleton(): void
    {
        $a = LoggerService::getInstance();
        $b = LoggerService::getInstance();
        $this->assertSame($a, $b);
    }

    public function test_channel_returns_new_instance(): void
    {
        $logger = LoggerService::channel('test');
        $this->assertInstanceOf(LoggerService::class, $logger);
    }

    public function test_level_constants_are_defined(): void
    {
        $this->assertEquals('emergency', LoggerService::EMERGENCY);
        $this->assertEquals('alert', LoggerService::ALERT);
        $this->assertEquals('critical', LoggerService::CRITICAL);
        $this->assertEquals('error', LoggerService::ERROR);
        $this->assertEquals('warning', LoggerService::WARNING);
        $this->assertEquals('notice', LoggerService::NOTICE);
        $this->assertEquals('info', LoggerService::INFO);
        $this->assertEquals('debug', LoggerService::DEBUG);
    }

    public function test_withContext_returns_self(): void
    {
        $logger = LoggerService::getInstance();
        $result = $logger->withContext(['test_key' => 'test_value']);
        $this->assertSame($logger, $result);
    }

    public function test_clearContext_returns_self(): void
    {
        $logger = LoggerService::getInstance();
        $result = $logger->clearContext();
        $this->assertSame($logger, $result);
    }
}

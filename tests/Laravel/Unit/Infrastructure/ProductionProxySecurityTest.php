<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;

class ProductionProxySecurityTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 4);
    }

    public function test_php_images_use_the_host_appended_forwarded_chain_and_one_csp_authority(): void
    {
        foreach (['Dockerfile.bluegreen', 'Dockerfile.prod'] as $file) {
            $source = (string) file_get_contents($this->root . DIRECTORY_SEPARATOR . $file);

            self::assertStringContainsString('RemoteIPHeader X-Forwarded-For', $source, $file);
            self::assertStringNotContainsString('RemoteIPHeader CF-Connecting-IP', $source, $file);
            self::assertStringNotContainsString('Header always set Content-Security-Policy', $source, $file);
        }
    }

    public function test_production_origin_ports_are_loopback_bound(): void
    {
        $blueGreen = (string) file_get_contents($this->root . DIRECTORY_SEPARATOR . 'compose.bluegreen.yml');
        self::assertStringContainsString('127.0.0.1:${NEXUS_API_PORT:-8190}:80', $blueGreen);
        self::assertStringContainsString('127.0.0.1:${NEXUS_FRONTEND_PORT:-3100}:80', $blueGreen);

        $fallback = (string) file_get_contents($this->root . DIRECTORY_SEPARATOR . 'compose.prod.yml');
        self::assertStringContainsString('127.0.0.1:8090:80', $fallback);
        self::assertStringContainsString('127.0.0.1:3000:80', $fallback);
    }
}

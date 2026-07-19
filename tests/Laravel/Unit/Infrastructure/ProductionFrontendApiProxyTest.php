<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;

class ProductionFrontendApiProxyTest extends TestCase
{
    public function test_every_react_nginx_config_proxies_same_origin_api_resources(): void
    {
        $root = dirname(__DIR__, 4);

        foreach (['nginx.conf', 'nginx.bluegreen.conf'] as $config) {
            $source = (string) file_get_contents($root . '/react-frontend/' . $config);

            self::assertStringContainsString('location ^~ /api/ {', $source, $config);
            self::assertStringContainsString('proxy_set_header Host api.project-nexus.ie;', $source, $config);
            self::assertStringContainsString('proxy_set_header X-Forwarded-Host $host;', $source, $config);
        }
    }
}

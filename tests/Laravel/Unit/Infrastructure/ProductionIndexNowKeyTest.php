<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;

class ProductionIndexNowKeyTest extends TestCase
{
    private const KEY = 'b0c4bb7b09d91e2a7c335f81399e94f4';

    public function test_every_react_nginx_config_serves_the_shipped_indexnow_key(): void
    {
        $root = dirname(__DIR__, 4);
        $keyFile = $root . '/react-frontend/public/' . self::KEY . '.txt';

        self::assertFileExists($keyFile);
        self::assertSame(self::KEY, trim((string) file_get_contents($keyFile)));

        foreach (['nginx.conf', 'nginx.bluegreen.conf'] as $config) {
            $source = (string) file_get_contents($root . '/react-frontend/' . $config);
            self::assertStringContainsString('location = /' . self::KEY . '.txt {', $source, $config);
            self::assertStringContainsString(
                'try_files /' . self::KEY . '.txt =404;',
                $source,
                $config,
            );
            self::assertStringContainsString('default_type text/plain;', $source, $config);
        }
    }

    public function test_seo_ping_uses_the_same_key(): void
    {
        $script = (string) file_get_contents(dirname(__DIR__, 4) . '/scripts/seo-ping.sh');

        self::assertStringContainsString('INDEXNOW_KEY="${INDEXNOW_KEY:-' . self::KEY . '}"', $script);
    }
}

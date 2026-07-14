<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;

class ProductionWorkerShutdownTest extends TestCase
{
    public function test_horizon_master_is_signalled_by_its_root_process_owner(): void
    {
        $script = (string) file_get_contents(
            dirname(__DIR__, 4) . '/scripts/deploy/bluegreen-deploy.sh',
        );

        self::assertStringContainsString(
            'docker exec "$container" php /var/www/html/artisan horizon:terminate',
            $script,
        );
        self::assertStringContainsString("grep -q 'Failed to kill process'", $script);
        self::assertStringNotContainsString(
            'docker_exec_app_user "$queue" php /var/www/html/artisan horizon:terminate',
            $script,
        );
        self::assertStringNotContainsString(
            'docker_exec_app_user nexus-php-queue php /var/www/html/artisan horizon:terminate',
            $script,
        );
    }
}

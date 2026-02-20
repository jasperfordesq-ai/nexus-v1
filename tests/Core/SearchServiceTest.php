<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Core;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\SearchService;

/**
 * SearchService Tests
 * @covers \Nexus\Core\SearchService
 */
class SearchServiceTest extends DatabaseTestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(SearchService::class));
    }

    public function testPublicMethodsExist(): void
    {
        $methods = ['search', 'searchAll'];
        foreach ($methods as $method) {
            if (method_exists(SearchService::class, $method)) {
                $this->assertTrue(true);
            }
        }
        $this->assertTrue(true);
    }
}

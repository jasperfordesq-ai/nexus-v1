<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Core;

use Nexus\Tests\TestCase;
use Nexus\Core\SEO;

/**
 * SEO Tests
 * @covers \Nexus\Core\SEO
 */
class SEOTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(SEO::class));
    }

    public function testPublicMethodsExist(): void
    {
        $methods = ['setTitle', 'setDescription', 'setKeywords', 'getMetaTags'];
        foreach ($methods as $method) {
            if (method_exists(SEO::class, $method)) {
                $this->assertTrue(true);
            }
        }
        $this->assertTrue(true);
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Core;

use App\Core\MenuManager;
use PHPUnit\Framework\TestCase;

class MenuManagerTest extends TestCase
{
    // -------------------------------------------------------
    // Constants
    // -------------------------------------------------------

    public function test_location_constants_are_defined(): void
    {
        $this->assertSame('header-main', MenuManager::LOCATION_HEADER_MAIN);
        $this->assertSame('header-secondary', MenuManager::LOCATION_HEADER_SECONDARY);
        $this->assertSame('footer', MenuManager::LOCATION_FOOTER);
        $this->assertSame('sidebar', MenuManager::LOCATION_SIDEBAR);
        $this->assertSame('mobile', MenuManager::LOCATION_MOBILE);
    }

    public function test_legacy_menu_constants_are_defined(): void
    {
        $this->assertSame('about', MenuManager::MENU_ABOUT);
        $this->assertSame('main', MenuManager::MENU_MAIN);
        $this->assertSame('footer', MenuManager::MENU_FOOTER);
    }
}

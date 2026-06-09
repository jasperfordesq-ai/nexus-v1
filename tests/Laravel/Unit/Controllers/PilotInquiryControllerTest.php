<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Controllers;

use App\Http\Controllers\Api\PilotInquiryController;
use ReflectionMethod;
use Tests\Laravel\TestCase;

class PilotInquiryControllerTest extends TestCase
{
    public function test_csv_escape_neutralizes_formula_like_cells(): void
    {
        $controller = new PilotInquiryController();
        $method = new ReflectionMethod($controller, 'csvEscape');
        $method->setAccessible(true);

        $this->assertSame('"\'=IMPORTXML(""https://example.test"")"', $method->invoke($controller, '=IMPORTXML("https://example.test")'));
        $this->assertSame('"\' +SUM(1,1)"', $method->invoke($controller, ' +SUM(1,1)'));
        $this->assertSame('ordinary', $method->invoke($controller, 'ordinary'));
    }
}

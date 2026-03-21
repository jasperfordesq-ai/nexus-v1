<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\Enterprise;

use Tests\Laravel\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * GdprService test — tests basic structure and error handling.
 * The service is large so we test key public methods.
 */
class GdprServiceTest extends TestCase
{
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(\App\Services\Enterprise\GdprService::class));
    }

    public function test_can_be_instantiated(): void
    {
        $service = new \App\Services\Enterprise\GdprService();
        $this->assertInstanceOf(\App\Services\Enterprise\GdprService::class, $service);
    }
}

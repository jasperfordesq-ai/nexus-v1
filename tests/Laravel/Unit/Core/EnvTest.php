<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Core;

use App\Core\Env;
use Tests\Laravel\TestCase;

class EnvTest extends TestCase
{
    // -------------------------------------------------------
    // load()
    // -------------------------------------------------------

    public function test_load_with_laravel_booted_is_noop(): void
    {
        // When Laravel is booted, load() should do nothing and not throw
        Env::load('/nonexistent/.env');
        $this->assertTrue(true); // No exception = pass
    }

    public function test_load_nonexistent_file_does_not_throw(): void
    {
        // Even outside Laravel, a missing file should not throw
        Env::load('/tmp/definitely-does-not-exist-' . uniqid() . '/.env');
        $this->assertTrue(true);
    }

    // -------------------------------------------------------
    // get()
    // -------------------------------------------------------

    public function test_get_returns_env_value_when_set(): void
    {
        // APP_KEY is always set in Laravel test environment
        $result = Env::get('APP_KEY');
        $this->assertNotNull($result);
        $this->assertNotEmpty($result);
    }

    public function test_get_returns_default_when_key_not_found(): void
    {
        $result = Env::get('TOTALLY_NONEXISTENT_VAR_' . uniqid(), 'my_default');
        $this->assertSame('my_default', $result);
    }

    public function test_get_returns_null_when_key_not_found_and_no_default(): void
    {
        $result = Env::get('TOTALLY_NONEXISTENT_VAR_' . uniqid());
        $this->assertNull($result);
    }
}

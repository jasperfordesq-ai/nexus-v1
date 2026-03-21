<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\EmailSettings;
use Tests\Laravel\TestCase;

/**
 * EmailSettings is NOT an Eloquent model — it is a plain utility class
 * with static methods. This test verifies its class structure.
 */
class EmailSettingsTest extends TestCase
{
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(EmailSettings::class));
    }

    public function test_is_not_an_eloquent_model(): void
    {
        $this->assertFalse(is_subclass_of(EmailSettings::class, \Illuminate\Database\Eloquent\Model::class));
    }

    public function test_has_get_static_method(): void
    {
        $this->assertTrue(method_exists(EmailSettings::class, 'get'));
    }

    public function test_has_set_static_method(): void
    {
        $this->assertTrue(method_exists(EmailSettings::class, 'set'));
    }

    public function test_has_delete_static_method(): void
    {
        $this->assertTrue(method_exists(EmailSettings::class, 'delete'));
    }

    public function test_has_get_all_for_tenant_static_method(): void
    {
        $this->assertTrue(method_exists(EmailSettings::class, 'getAllForTenant'));
    }

    public function test_has_set_multiple_static_method(): void
    {
        $this->assertTrue(method_exists(EmailSettings::class, 'setMultiple'));
    }

    public function test_has_get_masked_static_method(): void
    {
        $this->assertTrue(method_exists(EmailSettings::class, 'getMasked'));
    }

    public function test_has_has_static_method(): void
    {
        $this->assertTrue(method_exists(EmailSettings::class, 'has'));
    }
}

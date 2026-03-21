<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\EmailSetting;
use App\Models\Concerns\HasTenantScope;
use Tests\Laravel\TestCase;

class EmailSettingTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new EmailSetting();
        $this->assertEquals('email_settings', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new EmailSetting();
        $expected = [
            'tenant_id', 'setting_key', 'setting_value', 'is_encrypted',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new EmailSetting();
        $casts = $model->getCasts();
        $this->assertEquals('boolean', $casts['is_encrypted']);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(EmailSetting::class)
        );
    }
}

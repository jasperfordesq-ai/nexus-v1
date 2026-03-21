<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\VolEmergencyAlertRecipient;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class VolEmergencyAlertRecipientTest extends TestCase
{
    private VolEmergencyAlertRecipient $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new VolEmergencyAlertRecipient();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('vol_emergency_alert_recipients', $this->model->getTable());
    }

    public function test_timestamps_disabled(): void
    {
        $this->assertFalse($this->model->usesTimestamps());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = [
            'alert_id', 'tenant_id', 'user_id', 'notified_at',
            'response', 'responded_at',
        ];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('datetime', $casts['notified_at']);
        $this->assertEquals('datetime', $casts['responded_at']);
    }

    public function test_does_not_use_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(VolEmergencyAlertRecipient::class);
        $this->assertNotContains(\App\Models\Concerns\HasTenantScope::class, $traits);
    }

    public function test_alert_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->alert());
    }

    public function test_user_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->user());
    }
}

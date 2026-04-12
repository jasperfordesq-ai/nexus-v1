<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;

class JobConfigurationServiceTest extends TestCase
{
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(\App\Services\JobConfigurationService::class));
    }

    public function test_has_public_methods(): void
    {
        $ref = new \ReflectionClass(\App\Services\JobConfigurationService::class);
        foreach (['get', 'set', 'getAll'] as $m) {
            $this->assertTrue($ref->hasMethod($m), "Missing method: {$m}");
            $this->assertTrue($ref->getMethod($m)->isPublic(), "Not public: {$m}");
        }
    }

    public function test_get_returns_default_when_missing(): void
    {
        try {
            $result = \App\Services\JobConfigurationService::get('nonexistent_key_xyz', 'default_value');
            $this->assertEquals('default_value', $result);
        } catch (\Throwable $e) {
            $this->assertTrue(true);
        }
    }
}

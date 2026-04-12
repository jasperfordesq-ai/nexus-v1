<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\SafeguardingPreferenceService;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;
use Mockery;

/**
 * @covers \App\Services\SafeguardingPreferenceService
 *
 * Tests focus on pure-logic methods and validation paths that don't require
 * Eloquent model alias mocking (which conflicts with autoloaded classes).
 */
class SafeguardingPreferenceServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    // =========================================================================
    // URL validation (tested via reflection since validateUrl is private)
    // =========================================================================

    public function test_validateUrl_acceptsHttpsUrl(): void
    {
        $method = new \ReflectionMethod(SafeguardingPreferenceService::class, 'validateUrl');
        $method->setAccessible(true);

        $result = $method->invoke(null, 'https://example.com/help');

        $this->assertEquals('https://example.com/help', $result);
    }

    public function test_validateUrl_acceptsHttpUrl(): void
    {
        $method = new \ReflectionMethod(SafeguardingPreferenceService::class, 'validateUrl');
        $method->setAccessible(true);

        $result = $method->invoke(null, 'http://example.com/help');

        $this->assertEquals('http://example.com/help', $result);
    }

    public function test_validateUrl_rejectsJavascriptUrl(): void
    {
        $method = new \ReflectionMethod(SafeguardingPreferenceService::class, 'validateUrl');
        $method->setAccessible(true);

        $result = $method->invoke(null, 'javascript:alert(1)');

        $this->assertNull($result);
    }

    public function test_validateUrl_rejectsDataUrl(): void
    {
        $method = new \ReflectionMethod(SafeguardingPreferenceService::class, 'validateUrl');
        $method->setAccessible(true);

        $result = $method->invoke(null, 'data:text/html,<h1>test</h1>');

        $this->assertNull($result);
    }

    public function test_validateUrl_returnsNullForEmptyString(): void
    {
        $method = new \ReflectionMethod(SafeguardingPreferenceService::class, 'validateUrl');
        $method->setAccessible(true);

        $this->assertNull($method->invoke(null, ''));
        $this->assertNull($method->invoke(null, '   '));
        $this->assertNull($method->invoke(null, null));
    }

    // =========================================================================
    // getAvailablePresets — pure config-reading method
    // =========================================================================

    public function test_getAvailablePresets_returnsFormattedPresetList(): void
    {
        config(['safeguarding_presets' => [
            'ireland' => [
                'name' => 'Ireland (Garda Vetting)',
                'vetting_authority' => 'National Vetting Bureau',
                'help_text' => 'Required for working with children.',
                'options' => [
                    ['option_key' => 'garda_vetting', 'label' => 'Garda Vetting'],
                    ['option_key' => 'children_first', 'label' => 'Children First'],
                ],
            ],
            'uk' => [
                'name' => 'United Kingdom (DBS)',
                'vetting_authority' => 'Disclosure and Barring Service',
                'help_text' => 'DBS checks for regulated activity.',
                'options' => [
                    ['option_key' => 'dbs_enhanced', 'label' => 'Enhanced DBS Check'],
                ],
            ],
        ]]);

        $result = SafeguardingPreferenceService::getAvailablePresets();

        $this->assertCount(2, $result);

        $this->assertEquals('ireland', $result[0]['key']);
        $this->assertEquals('Ireland (Garda Vetting)', $result[0]['name']);
        $this->assertEquals('National Vetting Bureau', $result[0]['vetting_authority']);
        $this->assertEquals(2, $result[0]['option_count']);

        $this->assertEquals('uk', $result[1]['key']);
        $this->assertEquals(1, $result[1]['option_count']);
    }

    public function test_getAvailablePresets_returnsEmptyWhenNoPresetsConfigured(): void
    {
        config(['safeguarding_presets' => []]);

        $result = SafeguardingPreferenceService::getAvailablePresets();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // =========================================================================
    // applyCountryPreset — validation edge case
    // =========================================================================

    public function test_applyCountryPreset_returnsEmptyArrayWhenPresetNotFound(): void
    {
        config(['safeguarding_presets' => []]);

        $result = SafeguardingPreferenceService::applyCountryPreset($this->testTenantId, 'nonexistent');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_applyCountryPreset_returnsEmptyArrayWhenPresetHasNoOptions(): void
    {
        config(['safeguarding_presets' => [
            'empty_preset' => [
                'name' => 'Empty',
                'options' => [],
            ],
        ]]);

        $result = SafeguardingPreferenceService::applyCountryPreset($this->testTenantId, 'empty_preset');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}

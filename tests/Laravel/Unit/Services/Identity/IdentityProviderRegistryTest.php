<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\Identity;

use Tests\Laravel\TestCase;
use App\Services\Identity\IdentityProviderRegistry;
use App\Services\Identity\MockIdentityProvider;

class IdentityProviderRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        IdentityProviderRegistry::reset();
    }

    public function test_register_and_get_provider(): void
    {
        $mock = new MockIdentityProvider();
        IdentityProviderRegistry::register($mock);

        $retrieved = IdentityProviderRegistry::get('mock');
        $this->assertSame($mock, $retrieved);
    }

    public function test_has_returns_true_for_registered_provider(): void
    {
        $this->assertTrue(IdentityProviderRegistry::has('mock'));
    }

    public function test_has_returns_false_for_unregistered_provider(): void
    {
        IdentityProviderRegistry::reset();
        // After reset, accessing has() triggers ensureInitialized which registers built-ins
        // So 'mock' should be registered via ensureInitialized
        $this->assertTrue(IdentityProviderRegistry::has('mock'));
        $this->assertFalse(IdentityProviderRegistry::has('nonexistent_provider'));
    }

    public function test_get_throws_for_unregistered_provider(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        IdentityProviderRegistry::get('completely_fake_provider');
    }

    public function test_all_returns_array_of_providers(): void
    {
        $all = IdentityProviderRegistry::all();
        $this->assertIsArray($all);
        $this->assertArrayHasKey('mock', $all);
    }

    public function test_listForAdmin_returns_structured_array(): void
    {
        $list = IdentityProviderRegistry::listForAdmin();

        $this->assertIsArray($list);
        $this->assertNotEmpty($list);

        $first = $list[0];
        $this->assertArrayHasKey('slug', $first);
        $this->assertArrayHasKey('name', $first);
        $this->assertArrayHasKey('levels', $first);
    }

    public function test_reset_clears_all_providers(): void
    {
        IdentityProviderRegistry::reset();

        // After reset, providers are re-registered on next access
        $reflector = new \ReflectionClass(IdentityProviderRegistry::class);
        $prop = $reflector->getProperty('providers');
        $prop->setAccessible(true);
        $this->assertEmpty($prop->getValue());
    }

    public function test_builtin_providers_registered_on_first_access(): void
    {
        IdentityProviderRegistry::reset();

        // First access triggers ensureInitialized
        $all = IdentityProviderRegistry::all();

        $this->assertArrayHasKey('mock', $all);
        $this->assertArrayHasKey('stripe_identity', $all);
        $this->assertArrayHasKey('veriff', $all);
    }

    protected function tearDown(): void
    {
        IdentityProviderRegistry::reset();
        parent::tearDown();
    }
}

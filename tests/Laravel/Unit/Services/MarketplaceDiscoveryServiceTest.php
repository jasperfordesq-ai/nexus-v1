<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;

class MarketplaceDiscoveryServiceTest extends TestCase
{
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(\App\Services\MarketplaceDiscoveryService::class));
    }

    public function test_has_public_methods(): void
    {
        $ref = new \ReflectionClass(\App\Services\MarketplaceDiscoveryService::class);
        foreach (['createSavedSearch', 'getSavedSearches', 'deleteSavedSearch', 'toggleSavedSearch', 'createCollection', 'getCollections', 'updateCollection', 'deleteCollection', 'addToCollection', 'removeFromCollection', 'getCollectionItems'] as $m) {
            $this->assertTrue($ref->hasMethod($m), "Missing method: {$m}");
            $this->assertTrue($ref->getMethod($m)->isPublic(), "Not public: {$m}");
        }
    }
}

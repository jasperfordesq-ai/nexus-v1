<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Models;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\SeoMetadata;

/**
 * SeoMetadata Model Tests
 *
 * Tests SEO metadata get/save (upsert) for entity types.
 */
class SeoMetadataTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);
    }

    public static function tearDownAfterClass(): void
    {
        try {
            Database::query("DELETE FROM seo_metadata WHERE tenant_id = ? AND entity_type LIKE 'test_%'", [2]);
        } catch (\Exception $e) {
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);
    }

    // ==========================================
    // Get Tests
    // ==========================================

    public function testGetReturnsFalseForNonExistent(): void
    {
        $result = SeoMetadata::get('test_nonexistent_' . time());
        $this->assertFalse($result);
    }

    public function testGetWithEntityIdReturnsFalseForNonExistent(): void
    {
        $result = SeoMetadata::get('test_type', 999999999);
        $this->assertFalse($result);
    }

    // ==========================================
    // Save and Get Tests
    // ==========================================

    public function testSaveCreatesNewMetadata(): void
    {
        $entityType = 'test_save_' . time();

        SeoMetadata::save($entityType, null, [
            'meta_title' => 'Test Title',
            'meta_description' => 'Test description',
            'meta_keywords' => 'test, keywords',
        ]);

        $result = SeoMetadata::get($entityType);
        $this->assertNotFalse($result);
        $this->assertEquals('Test Title', $result['meta_title']);
        $this->assertEquals('Test description', $result['meta_description']);
        $this->assertEquals('test, keywords', $result['meta_keywords']);
    }

    public function testSaveWithEntityId(): void
    {
        $entityType = 'test_entity_' . time();

        SeoMetadata::save($entityType, 42, [
            'meta_title' => 'Entity Title',
            'meta_description' => 'Entity description',
        ]);

        $result = SeoMetadata::get($entityType, 42);
        $this->assertNotFalse($result);
        $this->assertEquals('Entity Title', $result['meta_title']);
    }

    public function testSaveUpdatesExistingMetadata(): void
    {
        $entityType = 'test_update_' . time();

        SeoMetadata::save($entityType, null, [
            'meta_title' => 'Original Title',
        ]);

        SeoMetadata::save($entityType, null, [
            'meta_title' => 'Updated Title',
            'meta_description' => 'Added description',
        ]);

        $result = SeoMetadata::get($entityType);
        $this->assertEquals('Updated Title', $result['meta_title']);
        $this->assertEquals('Added description', $result['meta_description']);
    }

    public function testSaveWithAllFields(): void
    {
        $entityType = 'test_all_fields_' . time();

        SeoMetadata::save($entityType, null, [
            'meta_title' => 'Full SEO Title',
            'meta_description' => 'Full SEO description',
            'meta_keywords' => 'full, seo, test',
            'canonical_url' => 'https://example.com/page',
            'og_image_url' => 'https://example.com/image.jpg',
            'noindex' => true,
        ]);

        $result = SeoMetadata::get($entityType);
        $this->assertNotFalse($result);
        $this->assertEquals('Full SEO Title', $result['meta_title']);
        $this->assertEquals('https://example.com/page', $result['canonical_url']);
        $this->assertEquals('https://example.com/image.jpg', $result['og_image_url']);
        $this->assertEquals(1, (int)$result['noindex']);
    }
}

<?php

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\ListingRiskTagService;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * ListingRiskTagServiceTest
 *
 * Tests for the listing risk tag service.
 * Covers tagging, retrieving, updating, and removing risk tags.
 */
class ListingRiskTagServiceTest extends TestCase
{
    private static $testTenantId = 1;
    private static $testBrokerId;
    private static $testListingId;
    private static $testTagId;

    public static function setUpBeforeClass(): void
    {
        TenantContext::setById(self::$testTenantId);

        $timestamp = time() . rand(1000, 9999);

        // Create test broker
        Database::query(
            "INSERT INTO users (tenant_id, email, name, first_name, last_name, role, is_approved, status, created_at)
             VALUES (?, ?, 'Test RiskBroker', 'Test', 'RiskBroker', 'broker', 1, 'active', NOW())",
            [self::$testTenantId, 'risk_broker_' . $timestamp . '@test.com']
        );
        self::$testBrokerId = Database::getInstance()->lastInsertId();

        // Create test listing
        Database::query(
            "INSERT INTO listings (tenant_id, user_id, title, description, type, status, created_at)
             VALUES (?, ?, 'Risk Test Listing', 'Description for risk tag test', 'offer', 'active', NOW())",
            [self::$testTenantId, self::$testBrokerId]
        );
        self::$testListingId = Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up risk tags
        if (self::$testListingId) {
            Database::query("DELETE FROM listing_risk_tags WHERE listing_id = ?", [self::$testListingId]);
        }

        // Clean up test listing
        if (self::$testListingId) {
            Database::query("DELETE FROM listings WHERE id = ?", [self::$testListingId]);
        }

        // Clean up test user
        if (self::$testBrokerId) {
            Database::query("DELETE FROM users WHERE id = ?", [self::$testBrokerId]);
        }
    }

    /**
     * Test tagging a listing with risk
     */
    public function testTagListing(): int
    {
        $tagData = [
            'risk_level' => 'medium',
            'risk_category' => 'safeguarding',
            'risk_notes' => 'Service involves vulnerable adults',
            'requires_approval' => true,
        ];

        $tagId = ListingRiskTagService::tagListing(
            self::$testListingId,
            $tagData,
            self::$testBrokerId
        );

        $this->assertNotNull($tagId, 'Should return a tag ID');
        $this->assertIsInt($tagId, 'Tag ID should be an integer');

        self::$testTagId = $tagId;
        return $tagId;
    }

    /**
     * Test getting tag for listing
     * @depends testTagListing
     */
    public function testGetTagForListing(int $tagId): void
    {
        $tag = ListingRiskTagService::getTagForListing(self::$testListingId);

        $this->assertIsArray($tag, 'Should return an array');
        $this->assertEquals('medium', $tag['risk_level']);
        $this->assertEquals('safeguarding', $tag['risk_category']);
        $this->assertStringContainsString('vulnerable adults', $tag['risk_notes']);
        $this->assertTrue((bool)$tag['requires_approval']);
        $this->assertEquals(self::$testBrokerId, $tag['tagged_by']);
    }

    /**
     * Test getting tag for untagged listing
     */
    public function testGetTagForUntaggedListing(): void
    {
        // Create an untagged listing
        Database::query(
            "INSERT INTO listings (tenant_id, user_id, title, type, status, created_at)
             VALUES (?, ?, 'Untagged Listing', 'offer', 'active', NOW())",
            [self::$testTenantId, self::$testBrokerId]
        );
        $untaggedListingId = Database::getInstance()->lastInsertId();

        $tag = ListingRiskTagService::getTagForListing($untaggedListingId);
        $this->assertNull($tag, 'Should return null for untagged listing');

        // Clean up
        Database::query("DELETE FROM listings WHERE id = ?", [$untaggedListingId]);
    }

    /**
     * Test updating existing tag
     * @depends testGetTagForListing
     */
    public function testUpdateTag(): void
    {
        $updateData = [
            'risk_level' => 'high',
            'risk_category' => 'health_safety',
            'risk_notes' => 'Updated: Requires physical assessment before exchange',
            'requires_approval' => true,
        ];

        $tagId = ListingRiskTagService::tagListing(
            self::$testListingId,
            $updateData,
            self::$testBrokerId
        );

        // Should return the existing tag ID (update)
        $this->assertNotNull($tagId, 'Should return a tag ID');

        // Verify update
        $tag = ListingRiskTagService::getTagForListing(self::$testListingId);
        $this->assertEquals('high', $tag['risk_level']);
        $this->assertEquals('health_safety', $tag['risk_category']);
    }

    /**
     * Test requiresApproval check
     * @depends testUpdateTag
     */
    public function testRequiresApproval(): void
    {
        $tag = ListingRiskTagService::getTagForListing(self::$testListingId);
        // The requires_approval field is set to true
        $this->assertTrue((bool)$tag['requires_approval'], 'Should require approval');
    }

    /**
     * Test requiresApproval for untagged listing
     */
    public function testRequiresApprovalForUntaggedListing(): void
    {
        $requires = ListingRiskTagService::requiresApproval(999999999);
        $this->assertFalse($requires, 'Untagged listing should not require approval');
    }

    /**
     * Test getting high risk listings
     * @depends testRequiresApproval
     */
    public function testGetHighRiskListings(): void
    {
        $highRisk = ListingRiskTagService::getHighRiskListings();

        $this->assertIsArray($highRisk, 'Should return an array');

        // Find our test listing
        $found = false;
        foreach ($highRisk as $listing) {
            if ($listing['listing_id'] == self::$testListingId) {
                $found = true;
                $this->assertEquals('high', $listing['risk_level']);
                break;
            }
        }
        $this->assertTrue($found, 'Test listing should be in high risk list');
    }

    /**
     * Test getting tagged listings (using getTaggedListings)
     * @depends testGetHighRiskListings
     */
    public function testGetTaggedListings(): void
    {
        $result = ListingRiskTagService::getTaggedListings('high');

        $this->assertIsArray($result, 'Should return an array');
        $this->assertArrayHasKey('items', $result);
        foreach ($result['items'] as $listing) {
            $this->assertEquals('high', $listing['risk_level']);
        }
    }

    /**
     * Test risk statistics
     * @depends testGetTaggedListings
     */
    public function testGetStatistics(): void
    {
        $stats = ListingRiskTagService::getStatistics();

        $this->assertIsArray($stats, 'Should return an array');
        $this->assertArrayHasKey('high_count', $stats);
        $this->assertArrayHasKey('low_count', $stats);
        $this->assertArrayHasKey('total', $stats);
    }

    /**
     * Test removing tag
     */
    public function testRemoveTag(): void
    {
        // First ensure a tag exists
        $tagData = [
            'risk_level' => 'low',
            'risk_category' => 'other',
            'risk_notes' => 'Tag to remove',
            'requires_approval' => false,
        ];
        ListingRiskTagService::tagListing(self::$testListingId, $tagData, self::$testBrokerId);

        $result = ListingRiskTagService::removeTag(self::$testListingId);
        $this->assertTrue($result, 'Remove should succeed');

        $tag = ListingRiskTagService::getTagForListing(self::$testListingId);
        $this->assertNull($tag, 'Tag should be removed');
    }

    /**
     * Test removing non-existent tag
     */
    public function testRemoveNonExistentTag(): void
    {
        $result = ListingRiskTagService::removeTag(999999999);
        $this->assertFalse($result, 'Should return false for non-existent tag');
    }

    /**
     * Test all valid risk levels
     */
    public function testAllRiskLevels(): void
    {
        $levels = ['low', 'medium', 'high', 'critical'];

        foreach ($levels as $level) {
            $tagData = [
                'risk_level' => $level,
                'risk_category' => 'other',
                'risk_notes' => "Testing $level risk level",
                'requires_approval' => false,
            ];

            $tagId = ListingRiskTagService::tagListing(
                self::$testListingId,
                $tagData,
                self::$testBrokerId
            );

            $this->assertNotNull($tagId, "Should create tag for $level level");

            $tag = ListingRiskTagService::getTagForListing(self::$testListingId);
            $this->assertEquals($level, $tag['risk_level']);
        }

        // Clean up
        ListingRiskTagService::removeTag(self::$testListingId);
    }

    /**
     * Test all valid risk categories
     */
    public function testAllRiskCategories(): void
    {
        $categories = [
            'safeguarding',
            'financial',
            'health_safety',
            'legal',
            'reputation',
            'fraud',
            'other',
        ];

        foreach ($categories as $category) {
            $tagData = [
                'risk_level' => 'medium',
                'risk_category' => $category,
                'risk_notes' => "Testing $category category",
                'requires_approval' => false,
            ];

            $tagId = ListingRiskTagService::tagListing(
                self::$testListingId,
                $tagData,
                self::$testBrokerId
            );

            $tag = ListingRiskTagService::getTagForListing(self::$testListingId);
            $this->assertEquals($category, $tag['risk_category'], "Category should be $category");
        }

        // Clean up
        ListingRiskTagService::removeTag(self::$testListingId);
    }
}

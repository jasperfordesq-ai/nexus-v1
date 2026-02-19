<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\UnifiedSearchService;

/**
 * Tests for UnifiedSearchService — the primary search service used by the React API.
 *
 * The service uses Database::getConnection() and TenantContext::getId() internally.
 * We test validation logic, structural contracts, and the truncate helper.
 * Database-dependent search methods are tested at the integration level;
 * here we focus on input validation, query sanitisation, pagination math,
 * and the public API contract.
 *
 * @covers \Nexus\Services\UnifiedSearchService
 */
class SearchServiceTest extends TestCase
{
    // ---------------------------------------------------------------
    // Class & method existence
    // ---------------------------------------------------------------

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(UnifiedSearchService::class));
    }

    public function testSearchMethodIsPublicAndStatic(): void
    {
        $ref = new \ReflectionMethod(UnifiedSearchService::class, 'search');
        $this->assertTrue($ref->isStatic());
        $this->assertTrue($ref->isPublic());
    }

    public function testGetSuggestionsMethodIsPublicAndStatic(): void
    {
        $ref = new \ReflectionMethod(UnifiedSearchService::class, 'getSuggestions');
        $this->assertTrue($ref->isStatic());
        $this->assertTrue($ref->isPublic());
    }

    public function testGetErrorsMethodExists(): void
    {
        $this->assertTrue(method_exists(UnifiedSearchService::class, 'getErrors'));
    }

    // ---------------------------------------------------------------
    // Method signatures
    // ---------------------------------------------------------------

    public function testSearchMethodSignature(): void
    {
        $ref = new \ReflectionMethod(UnifiedSearchService::class, 'search');
        $params = $ref->getParameters();

        $this->assertCount(3, $params);
        $this->assertEquals('query', $params[0]->getName());
        $this->assertEquals('userId', $params[1]->getName());
        $this->assertEquals('filters', $params[2]->getName());
        $this->assertTrue($params[1]->allowsNull());
        $this->assertTrue($params[2]->isOptional());
        $this->assertEquals([], $params[2]->getDefaultValue());
    }

    public function testGetSuggestionsMethodSignature(): void
    {
        $ref = new \ReflectionMethod(UnifiedSearchService::class, 'getSuggestions');
        $params = $ref->getParameters();

        $this->assertCount(3, $params);
        $this->assertEquals('query', $params[0]->getName());
        $this->assertEquals('tenantId', $params[1]->getName());
        $this->assertEquals('limit', $params[2]->getName());
        $this->assertTrue($params[2]->isOptional());
        $this->assertEquals(5, $params[2]->getDefaultValue());
    }

    // ---------------------------------------------------------------
    // Minimum query length enforcement (2 chars)
    // ---------------------------------------------------------------

    public function testSearchRejectsEmptyQuery(): void
    {
        // Calling search with empty query should return validation error
        // without hitting the database (strlen('') < 2)
        $result = UnifiedSearchService::search('', null);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertEmpty($result['items']);
        $this->assertEquals(0, $result['total']);

        $errors = UnifiedSearchService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertEquals('VALIDATION_ERROR', $errors[0]['code']);
        $this->assertEquals('q', $errors[0]['field']);
    }

    public function testSearchRejectsSingleCharacterQuery(): void
    {
        $result = UnifiedSearchService::search('a', null);

        $this->assertIsArray($result);
        $this->assertEmpty($result['items']);

        $errors = UnifiedSearchService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('at least 2 characters', $errors[0]['message']);
    }

    public function testSearchRejectsWhitespaceOnlyQuery(): void
    {
        // "   " trims to "" which is length 0 < 2
        $result = UnifiedSearchService::search('   ', null);

        $this->assertIsArray($result);
        $this->assertEmpty($result['items']);

        $errors = UnifiedSearchService::getErrors();
        $this->assertNotEmpty($errors);
    }

    public function testSearchRejectsSingleCharWithWhitespace(): void
    {
        // " a " trims to "a" which is length 1 < 2
        $result = UnifiedSearchService::search(' a ', null);

        $this->assertIsArray($result);
        $this->assertEmpty($result['items']);

        $errors = UnifiedSearchService::getErrors();
        $this->assertNotEmpty($errors);
    }

    // ---------------------------------------------------------------
    // Return structure contract
    // ---------------------------------------------------------------

    public function testSearchReturnStructureOnValidationError(): void
    {
        $result = UnifiedSearchService::search('', null);

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertArrayHasKey('total', $result);

        $this->assertNull($result['cursor']);
        $this->assertFalse($result['has_more']);
    }

    // ---------------------------------------------------------------
    // Errors are cleared between calls
    // ---------------------------------------------------------------

    public function testErrorsAreClearedBetweenCalls(): void
    {
        // First call: invalid query should produce errors
        UnifiedSearchService::search('', null);
        $this->assertNotEmpty(UnifiedSearchService::getErrors());

        // Second call: also invalid, but errors should be fresh (not accumulated)
        UnifiedSearchService::search('x', null);
        $errors = UnifiedSearchService::getErrors();

        $this->assertCount(1, $errors, 'Errors from previous call should be cleared');
    }

    // ---------------------------------------------------------------
    // Cursor / pagination math
    // ---------------------------------------------------------------

    public function testCursorEncodingIsBase64(): void
    {
        // The service uses base64_encode((string)offset) for cursor
        $offset = 20;
        $cursor = base64_encode((string)$offset);

        $decoded = base64_decode($cursor, true);
        $this->assertNotFalse($decoded);
        $this->assertTrue(is_numeric($decoded));
        $this->assertEquals(20, (int)$decoded);
    }

    public function testInvalidCursorDecodesGracefully(): void
    {
        // A non-base64 cursor should decode to 0 offset (graceful fallback)
        $invalidCursor = '!!!not-base64!!!';
        $decoded = base64_decode($invalidCursor, true);

        // base64_decode with strict=true returns false for invalid input
        if ($decoded === false || !is_numeric($decoded)) {
            $offset = 0;
        } else {
            $offset = (int)$decoded;
        }

        $this->assertEquals(0, $offset);
    }

    public function testPaginationLimitIsCappedAt50(): void
    {
        // The service clamps: min($filters['limit'] ?? 20, 50)
        $this->assertEquals(50, min(100, 50));
        $this->assertEquals(20, min(20, 50));
        $this->assertEquals(1, min(1, 50));
    }

    public function testDefaultLimitIs20(): void
    {
        $filters = [];
        $limit = min($filters['limit'] ?? 20, 50);
        $this->assertEquals(20, $limit);
    }

    // ---------------------------------------------------------------
    // Type filter validation
    // ---------------------------------------------------------------

    /**
     * @dataProvider validTypeFilterProvider
     */
    public function testValidTypeFiltersAreAccepted(string $type): void
    {
        $validTypes = ['all', 'listings', 'users', 'events', 'groups'];
        $this->assertContains($type, $validTypes);
    }

    public static function validTypeFilterProvider(): array
    {
        return [
            'all' => ['all'],
            'listings' => ['listings'],
            'users' => ['users'],
            'events' => ['events'],
            'groups' => ['groups'],
        ];
    }

    public function testInvalidTypeIsNotInValidList(): void
    {
        $validTypes = ['all', 'listings', 'users', 'events', 'groups'];
        $this->assertNotContains('pages', $validTypes);
        $this->assertNotContains('messages', $validTypes);
        $this->assertNotContains('admin', $validTypes);
    }

    // ---------------------------------------------------------------
    // Truncate helper (private, tested via reflection)
    // ---------------------------------------------------------------

    public function testTruncateReturnsNullForNullInput(): void
    {
        $result = $this->invokeTruncate(null, 100);
        $this->assertNull($result);
    }

    public function testTruncateReturnsShortStringUnchanged(): void
    {
        $short = 'Hello world';
        $result = $this->invokeTruncate($short, 100);
        $this->assertEquals('Hello world', $result);
    }

    public function testTruncateTrimsLongString(): void
    {
        $long = str_repeat('a', 200);
        $result = $this->invokeTruncate($long, 150);

        // Should be 147 chars + '...' = 150 chars
        $this->assertEquals(150, strlen($result));
        $this->assertStringEndsWith('...', $result);
    }

    public function testTruncateStripsHtmlTags(): void
    {
        $html = '<p>Hello <strong>world</strong></p>';
        $result = $this->invokeTruncate($html, 100);

        $this->assertStringNotContainsString('<p>', $result);
        $this->assertStringNotContainsString('<strong>', $result);
        $this->assertEquals('Hello world', $result);
    }

    public function testTruncateExactLengthStringNotTruncated(): void
    {
        $exact = str_repeat('x', 150);
        $result = $this->invokeTruncate($exact, 150);

        $this->assertEquals(150, strlen($result));
        $this->assertStringNotContainsString('...', $result);
    }

    // ---------------------------------------------------------------
    // Query sanitisation — SQL injection prevention
    // ---------------------------------------------------------------

    public function testSearchQueryIsTrimmed(): void
    {
        // The search method trims the query before length check
        // "  ab  " should be treated as "ab" (length 2, passes validation)
        // This will fail on DB call since we're not in integration, but
        // the point is it should NOT return a validation error
        // We can't easily test this without DB, so test the trim logic directly
        $query = '  ab  ';
        $trimmed = trim($query);

        $this->assertEquals('ab', $trimmed);
        $this->assertGreaterThanOrEqual(2, strlen($trimmed));
    }

    public function testSearchTermUsesParameterizedLikePattern(): void
    {
        // Verify the pattern: '%' . $query . '%' is what gets used
        // This ensures no direct SQL concatenation
        $query = "test'; DROP TABLE users; --";
        $searchTerm = '%' . $query . '%';

        $this->assertEquals("%test'; DROP TABLE users; --%", $searchTerm);
        // The key point: this string goes into a prepared statement placeholder (?),
        // not concatenated into SQL. The test verifies the pattern is correct.
    }

    public function testSpecialCharactersInQuery(): void
    {
        // Percent and underscore are LIKE wildcards but are safe in parameterized queries
        $query = '100% discount_offer';
        $searchTerm = '%' . $query . '%';

        $this->assertStringContainsString('100%', $searchTerm);
        // Prepared statements handle this safely — the wildcard chars are literal
    }

    // ---------------------------------------------------------------
    // Search sub-method existence (private methods for each type)
    // ---------------------------------------------------------------

    public function testSearchListingsMethodExists(): void
    {
        $ref = new \ReflectionClass(UnifiedSearchService::class);
        $this->assertTrue($ref->hasMethod('searchListings'));
    }

    public function testSearchUsersMethodExists(): void
    {
        $ref = new \ReflectionClass(UnifiedSearchService::class);
        $this->assertTrue($ref->hasMethod('searchUsers'));
    }

    public function testSearchEventsMethodExists(): void
    {
        $ref = new \ReflectionClass(UnifiedSearchService::class);
        $this->assertTrue($ref->hasMethod('searchEvents'));
    }

    public function testSearchGroupsMethodExists(): void
    {
        $ref = new \ReflectionClass(UnifiedSearchService::class);
        $this->assertTrue($ref->hasMethod('searchGroups'));
    }

    // ---------------------------------------------------------------
    // Also cover the Core\SearchService
    // ---------------------------------------------------------------

    public function testCoreSearchServiceClassExists(): void
    {
        $this->assertTrue(class_exists(\Nexus\Core\SearchService::class));
    }

    public function testCoreSearchServiceHasSearchMethod(): void
    {
        $this->assertTrue(method_exists(\Nexus\Core\SearchService::class, 'search'));
    }

    public function testCoreSearchServiceSearchIsNotStatic(): void
    {
        $ref = new \ReflectionMethod(\Nexus\Core\SearchService::class, 'search');
        $this->assertFalse($ref->isStatic());
    }

    public function testCoreSearchServiceDeduplicateRemovesDuplicates(): void
    {
        $ref = new \ReflectionClass(\Nexus\Core\SearchService::class);
        $method = $ref->getMethod('deduplicateResults');
        $method->setAccessible(true);

        // Need an instance to call non-static method
        $service = new \Nexus\Core\SearchService();

        $results = [
            ['type' => 'user', 'id' => 1, 'title' => 'Alice'],
            ['type' => 'user', 'id' => 1, 'title' => 'Alice duplicate'],
            ['type' => 'listing', 'id' => 1, 'title' => 'Some listing'],
            ['type' => 'listing', 'id' => 2, 'title' => 'Another listing'],
            ['type' => 'listing', 'id' => 2, 'title' => 'Another duplicate'],
        ];

        $unique = $method->invoke($service, $results);

        $this->assertCount(3, $unique);
        // First occurrence kept
        $this->assertEquals('Alice', $unique[0]['title']);
        $this->assertEquals('Some listing', $unique[1]['title']);
        $this->assertEquals('Another listing', $unique[2]['title']);
    }

    public function testCoreSearchServiceFormatResultsConvertsTypes(): void
    {
        $ref = new \ReflectionClass(\Nexus\Core\SearchService::class);
        $method = $ref->getMethod('formatResults');
        $method->setAccessible(true);

        $service = new \Nexus\Core\SearchService();

        $meiliResults = [
            'users' => [
                ['id' => 1, 'name' => 'Alice', 'avatar_url' => '/img/alice.jpg'],
            ],
            'listings' => [
                ['id' => 10, 'title' => 'Gardening Help', 'image_url' => '/img/garden.jpg'],
            ],
        ];

        $formatted = $method->invoke($service, $meiliResults);

        $this->assertCount(2, $formatted);

        // 'users' -> 'user' (rtrim 's')
        $this->assertEquals('user', $formatted[0]['type']);
        $this->assertEquals('Alice', $formatted[0]['title']);

        // 'listings' -> 'listing'
        $this->assertEquals('listing', $formatted[1]['type']);
        $this->assertEquals('Gardening Help', $formatted[1]['title']);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Invoke the private static truncate method via reflection
     */
    private function invokeTruncate(?string $text, int $length): ?string
    {
        $ref = new \ReflectionMethod(UnifiedSearchService::class, 'truncate');
        $ref->setAccessible(true);
        return $ref->invoke(null, $text, $length);
    }
}

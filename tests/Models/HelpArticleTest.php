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
use Nexus\Models\HelpArticle;

/**
 * HelpArticle Model Tests
 *
 * Tests article retrieval, search, related articles, popularity,
 * view count tracking, feedback recording, and feedback stats.
 */
class HelpArticleTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testArticleId = null;
    protected static ?int $testUserId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);

        $timestamp = time();

        // Create a test user for feedback
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 100, 1, NOW())",
            [self::$testTenantId, "help_art_test_{$timestamp}@test.com", "help_art_test_{$timestamp}", 'Help', 'Tester', 'Help Tester']
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Create a test help article
        Database::query(
            "INSERT INTO help_articles (title, slug, content, module_tag, is_public, view_count, created_at)
             VALUES (?, ?, ?, ?, 1, 0, NOW())",
            ["Test Help Article {$timestamp}", "test-help-article-{$timestamp}", '<p>This is a test help article about listings.</p>', 'core']
        );
        self::$testArticleId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        try {
            if (self::$testArticleId) {
                Database::query("DELETE FROM help_article_feedback WHERE article_id = ?", [self::$testArticleId]);
                Database::query("DELETE FROM help_articles WHERE id = ?", [self::$testArticleId]);
            }
            if (self::$testUserId) {
                Database::query("DELETE FROM activity_log WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            }
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
    // GetAll Tests
    // ==========================================

    public function testGetAllReturnsArray(): void
    {
        $articles = HelpArticle::getAll(['core']);
        $this->assertIsArray($articles);
    }

    public function testGetAllFiltersToAllowedModules(): void
    {
        $articles = HelpArticle::getAll(['core']);
        foreach ($articles as $article) {
            $this->assertContains($article['module_tag'], ['core', 'getting_started']);
        }
    }

    // ==========================================
    // FindBySlug Tests
    // ==========================================

    public function testFindBySlugReturnsArticle(): void
    {
        $article = Database::query("SELECT slug FROM help_articles WHERE id = ?", [self::$testArticleId])->fetch();

        $found = HelpArticle::findBySlug($article['slug']);
        $this->assertNotFalse($found);
        $this->assertEquals(self::$testArticleId, $found['id']);
    }

    public function testFindBySlugReturnsFalseForNonExistent(): void
    {
        $article = HelpArticle::findBySlug('nonexistent-slug-xyz-' . time());
        $this->assertFalse($article);
    }

    // ==========================================
    // FindById Tests
    // ==========================================

    public function testFindByIdReturnsArticle(): void
    {
        $article = HelpArticle::findById(self::$testArticleId);
        $this->assertNotFalse($article);
        $this->assertEquals(self::$testArticleId, $article['id']);
    }

    public function testFindByIdReturnsFalseForNonExistent(): void
    {
        $article = HelpArticle::findById(999999999);
        $this->assertFalse($article);
    }

    // ==========================================
    // Search Tests
    // ==========================================

    public function testSearchReturnsArray(): void
    {
        $results = HelpArticle::search('test', ['core']);
        $this->assertIsArray($results);
    }

    public function testSearchFindsArticleByTitle(): void
    {
        $article = Database::query("SELECT title FROM help_articles WHERE id = ?", [self::$testArticleId])->fetch();
        $searchTerm = substr($article['title'], 0, 10);

        $results = HelpArticle::search($searchTerm, ['core']);
        $this->assertIsArray($results);

        $found = false;
        foreach ($results as $r) {
            if ($r['id'] == self::$testArticleId) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Search should find article by title');
    }

    // ==========================================
    // GetRelated Tests
    // ==========================================

    public function testGetRelatedReturnsArray(): void
    {
        $related = HelpArticle::getRelated('core', self::$testArticleId);
        $this->assertIsArray($related);
    }

    public function testGetRelatedExcludesCurrentArticle(): void
    {
        $related = HelpArticle::getRelated('core', self::$testArticleId);
        $this->assertIsArray($related);
        foreach ($related as $article) {
            $this->assertNotEquals(self::$testArticleId, $article['id']);
        }
    }

    // ==========================================
    // GetPopular Tests
    // ==========================================

    public function testGetPopularReturnsArray(): void
    {
        $popular = HelpArticle::getPopular(['core']);
        $this->assertIsArray($popular);
    }

    public function testGetPopularRespectsLimit(): void
    {
        $popular = HelpArticle::getPopular(['core'], 3);
        $this->assertIsArray($popular);
        $this->assertLessThanOrEqual(3, count($popular));
    }

    // ==========================================
    // IncrementViewCount Tests
    // ==========================================

    public function testIncrementViewCountReturnsTrue(): void
    {
        $before = HelpArticle::findById(self::$testArticleId);
        $beforeCount = (int)$before['view_count'];

        $result = HelpArticle::incrementViewCount(self::$testArticleId);
        $this->assertTrue($result);

        $after = HelpArticle::findById(self::$testArticleId);
        $this->assertEquals($beforeCount + 1, (int)$after['view_count']);
    }

    // ==========================================
    // RecordFeedback Tests
    // ==========================================

    public function testRecordFeedbackReturnsTrue(): void
    {
        $result = HelpArticle::recordFeedback(self::$testArticleId, true, self::$testUserId);
        $this->assertTrue($result);
    }

    public function testRecordFeedbackReturnsFalseForDuplicate(): void
    {
        // First feedback should succeed (may already exist from previous test)
        HelpArticle::recordFeedback(self::$testArticleId, true, self::$testUserId);

        // Second feedback from same user should fail
        $result = HelpArticle::recordFeedback(self::$testArticleId, false, self::$testUserId);
        $this->assertFalse($result);
    }

    // ==========================================
    // GetFeedbackStats Tests
    // ==========================================

    public function testGetFeedbackStatsReturnsStructure(): void
    {
        $stats = HelpArticle::getFeedbackStats(self::$testArticleId);
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('helpful', $stats);
        $this->assertArrayHasKey('not_helpful', $stats);
        $this->assertArrayHasKey('total', $stats);
    }

    public function testGetFeedbackStatsReturnsZerosForNoFeedback(): void
    {
        $stats = HelpArticle::getFeedbackStats(999999999);
        $this->assertEquals(0, $stats['helpful']);
        $this->assertEquals(0, $stats['not_helpful']);
        $this->assertEquals(0, $stats['total']);
    }
}

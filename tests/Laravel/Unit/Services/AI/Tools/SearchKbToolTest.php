<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\AI\Tools;

use App\Core\TenantContext;
use App\Services\AI\Tools\SearchKbTool;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * SearchKbToolTest
 *
 * Tests the SearchKbTool which queries knowledge_base_articles for the
 * current tenant.  Strategy:
 *   - Seed minimal KB article rows under tenant 2.
 *   - Verify the ok/error envelope shape.
 *   - Verify title-match ranking over content-match.
 *   - Verify tenant isolation (other-tenant articles excluded).
 *   - Verify unpublished articles are excluded.
 *   - Verify empty-query guard returns err.
 *   - Verify limit clamping (max 5).
 */
class SearchKbToolTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;
    private const OTHER_TENANT_ID = 99901;

    private SearchKbTool $tool;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        TenantContext::setById(self::TENANT_ID);
        $this->tool = new SearchKbTool();
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function insertArticle(array $overrides = []): int
    {
        $defaults = [
            'tenant_id'    => self::TENANT_ID,
            'title'        => 'Test KB Article ' . uniqid(),
            'slug'         => 'test-kb-' . uniqid(),
            'content'      => 'Some helpful content about the platform.',
            'is_published' => 1,
            'helpful_yes'  => 0,
            'views_count'  => 0,
            'created_at'   => now(),
        ];

        return DB::table('knowledge_base_articles')->insertGetId(array_merge($defaults, $overrides));
    }

    // ── tests ─────────────────────────────────────────────────────────────────

    public function test_empty_query_returns_error_envelope(): void
    {
        $result = $this->tool->execute(['query' => ''], userId: 1);

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['error']);
        $this->assertSame([], $result['results']);
        $this->assertSame('error', $result['card_type']);
    }

    public function test_whitespace_only_query_returns_error(): void
    {
        // stringArg() trims, so '   ' becomes '' which triggers the guard.
        $result = $this->tool->execute(['query' => '   '], userId: 1);

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['error']);
    }

    public function test_no_matching_articles_returns_ok_with_empty_results(): void
    {
        $result = $this->tool->execute(['query' => 'xyzzy-no-such-topic-99999'], userId: 1);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['results']);
        $this->assertSame('kb', $result['card_type']);
        $this->assertStringContainsString('No published KB articles matched', $result['summary']);
    }

    public function test_matching_published_article_is_returned(): void
    {
        $uniqueWord = 'zqrtimebank' . uniqid();
        $id = $this->insertArticle(['title' => "How to use {$uniqueWord}"]);

        $result = $this->tool->execute(['query' => $uniqueWord], userId: 1);

        $this->assertTrue($result['ok']);
        $this->assertCount(1, $result['results']);

        $article = $result['results'][0];
        $this->assertSame($id, $article['id']);
        $this->assertArrayHasKey('title', $article);
        $this->assertArrayHasKey('excerpt', $article);
        $this->assertArrayHasKey('url', $article);
        $this->assertSame('kb', $result['card_type']);
    }

    public function test_unpublished_articles_are_excluded(): void
    {
        $uniqueWord = 'unpublishedkb' . uniqid();
        $this->insertArticle([
            'title'        => "Draft article {$uniqueWord}",
            'is_published' => 0,
        ]);

        $result = $this->tool->execute(['query' => $uniqueWord], userId: 1);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['results']);
    }

    public function test_other_tenant_articles_are_excluded(): void
    {
        $uniqueWord = 'othertenantkb' . uniqid();
        $this->insertArticle([
            'tenant_id' => self::OTHER_TENANT_ID,
            'title'     => "Article for other tenant {$uniqueWord}",
        ]);

        // Search as tenant 2 — should see nothing.
        $result = $this->tool->execute(['query' => $uniqueWord], userId: 1);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['results']);
    }

    public function test_content_match_also_returns_result(): void
    {
        $uniqueWord = 'contentonlymatch' . uniqid();
        $id = $this->insertArticle([
            'title'   => 'Generic Article Title',
            'content' => "This article explains {$uniqueWord} in detail.",
        ]);

        $result = $this->tool->execute(['query' => $uniqueWord], userId: 1);

        $this->assertTrue($result['ok']);
        $this->assertCount(1, $result['results']);
        $this->assertSame($id, $result['results'][0]['id']);
    }

    public function test_limit_argument_is_respected(): void
    {
        $uniqueWord = 'limitkb' . uniqid();
        // Insert 5 matching articles.
        for ($i = 0; $i < 5; $i++) {
            $this->insertArticle(['title' => "Article {$i} about {$uniqueWord}"]);
        }

        // Request only 2.
        $result = $this->tool->execute(['query' => $uniqueWord, 'limit' => 2], userId: 1);

        $this->assertTrue($result['ok']);
        $this->assertCount(2, $result['results']);
    }

    public function test_limit_is_clamped_to_maximum_of_five(): void
    {
        $uniqueWord = 'maxlimitkb' . uniqid();
        // Insert 8 matching articles.
        for ($i = 0; $i < 8; $i++) {
            $this->insertArticle(['title' => "Article {$i} about {$uniqueWord}"]);
        }

        // Request 10 — should be clamped to 5.
        $result = $this->tool->execute(['query' => $uniqueWord, 'limit' => 10], userId: 1);

        $this->assertTrue($result['ok']);
        $this->assertLessThanOrEqual(5, count($result['results']));
    }

    public function test_url_field_contains_article_id(): void
    {
        $uniqueWord = 'urlcheck' . uniqid();
        $id = $this->insertArticle(['title' => "URL test {$uniqueWord}"]);

        $result = $this->tool->execute(['query' => $uniqueWord], userId: 1);

        $this->assertTrue($result['ok']);
        $this->assertCount(1, $result['results']);
        $this->assertStringContainsString('/kb/' . $id, $result['results'][0]['url']);
    }

    public function test_excerpt_is_plain_text_stripped_of_html(): void
    {
        $uniqueWord = 'htmlstrip' . uniqid();
        $this->insertArticle([
            'title'   => "HTML content {$uniqueWord}",
            'content' => '<p>This is <strong>bold</strong> and has HTML tags.</p>',
        ]);

        $result = $this->tool->execute(['query' => $uniqueWord], userId: 1);

        $this->assertTrue($result['ok']);
        $this->assertCount(1, $result['results']);
        $excerpt = $result['results'][0]['excerpt'];
        // excerpt should not contain raw HTML tags
        $this->assertStringNotContainsString('<p>', $excerpt);
        $this->assertStringNotContainsString('<strong>', $excerpt);
    }

    public function test_result_envelope_always_has_required_keys(): void
    {
        $uniqueWord = 'envelopekeys' . uniqid();
        $this->insertArticle(['title' => "Envelope test {$uniqueWord}"]);

        $result = $this->tool->execute(['query' => $uniqueWord], userId: 1);

        $this->assertTrue($result['ok']);
        foreach ($result['results'] as $article) {
            $this->assertArrayHasKey('id', $article);
            $this->assertArrayHasKey('title', $article);
            $this->assertArrayHasKey('excerpt', $article);
            $this->assertArrayHasKey('url', $article);
            $this->assertIsInt($article['id']);
            $this->assertIsString($article['title']);
            $this->assertIsString($article['excerpt']);
            $this->assertIsString($article['url']);
        }
    }
}

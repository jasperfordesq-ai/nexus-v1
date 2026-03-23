<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\LinkPreviewService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Laravel\TestCase;

/**
 * Unit tests for LinkPreviewService — URL extraction, HTML parsing, caching, SSRF protection.
 */
class LinkPreviewServiceTest extends TestCase
{
    private LinkPreviewService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LinkPreviewService();
    }

    // ------------------------------------------------------------------
    //  extractUrls()
    // ------------------------------------------------------------------

    public function test_extractUrls_extracts_single_http_url(): void
    {
        $result = $this->service->extractUrls('Check out https://example.com for more info.');
        $this->assertCount(1, $result);
        $this->assertEquals('https://example.com', $result[0]);
    }

    public function test_extractUrls_extracts_multiple_urls(): void
    {
        $text = 'Visit https://example.com and http://test.org for details.';
        $result = $this->service->extractUrls($text);
        $this->assertCount(2, $result);
        $this->assertEquals('https://example.com', $result[0]);
        $this->assertEquals('http://test.org', $result[1]);
    }

    public function test_extractUrls_deduplicates_urls(): void
    {
        $text = 'Visit https://example.com and also https://example.com again.';
        $result = $this->service->extractUrls($text);
        $this->assertCount(1, $result);
    }

    public function test_extractUrls_strips_trailing_punctuation(): void
    {
        $text = 'See https://example.com/page. Also https://test.org/path, and https://foo.com!';
        $result = $this->service->extractUrls($text);
        $this->assertEquals('https://example.com/page', $result[0]);
        $this->assertEquals('https://test.org/path', $result[1]);
        $this->assertEquals('https://foo.com', $result[2]);
    }

    public function test_extractUrls_returns_empty_for_text_without_urls(): void
    {
        $result = $this->service->extractUrls('No URLs in this text at all.');
        $this->assertCount(0, $result);
    }

    public function test_extractUrls_strips_html_tags_before_extraction(): void
    {
        $html = '<p>Check <a href="https://example.com">https://example.com</a></p>';
        $result = $this->service->extractUrls($html);
        $this->assertCount(1, $result);
        $this->assertEquals('https://example.com', $result[0]);
    }

    public function test_extractUrls_handles_url_with_query_parameters(): void
    {
        $text = 'Visit https://example.com/search?q=test&page=1 for results.';
        $result = $this->service->extractUrls($text);
        $this->assertCount(1, $result);
        $this->assertStringContainsString('q=test', $result[0]);
    }

    public function test_extractUrls_handles_url_with_path(): void
    {
        $text = 'Read https://example.com/blog/my-article today.';
        $result = $this->service->extractUrls($text);
        $this->assertCount(1, $result);
        $this->assertEquals('https://example.com/blog/my-article', $result[0]);
    }

    // ------------------------------------------------------------------
    //  fetchPreview() — cache and SSRF protection
    // ------------------------------------------------------------------

    public function test_fetchPreview_returns_null_for_non_http_scheme(): void
    {
        $result = $this->service->fetchPreview('ftp://example.com/file');
        $this->assertNull($result);
    }

    public function test_fetchPreview_returns_null_for_empty_url(): void
    {
        $result = $this->service->fetchPreview('');
        $this->assertNull($result);
    }

    public function test_fetchPreview_returns_null_for_url_without_host(): void
    {
        $result = $this->service->fetchPreview('https://');
        $this->assertNull($result);
    }

    public function test_fetchPreview_returns_cached_data_when_available(): void
    {
        $urlHash = hash('sha256', 'https://example.com/');

        $cachedRow = (object) [
            'url' => 'https://example.com',
            'title' => 'Cached Title',
            'description' => 'Cached description',
            'image_url' => 'https://example.com/img.jpg',
            'site_name' => 'Example',
            'favicon_url' => 'https://example.com/favicon.ico',
            'domain' => 'example.com',
            'content_type' => 'website',
            'embed_html' => null,
        ];

        DB::shouldReceive('table->where->where->first')
            ->once()
            ->andReturn($cachedRow);

        $result = $this->service->fetchPreview('https://example.com');
        $this->assertNotNull($result);
        $this->assertEquals('Cached Title', $result['title']);
        $this->assertEquals('example.com', $result['domain']);
    }

    public function test_fetchPreview_returns_null_for_file_protocol(): void
    {
        $result = $this->service->fetchPreview('file:///etc/passwd');
        $this->assertNull($result);
    }

    public function test_fetchPreview_returns_null_for_javascript_protocol(): void
    {
        $result = $this->service->fetchPreview('javascript:alert(1)');
        $this->assertNull($result);
    }

    public function test_fetchPreview_returns_null_for_data_protocol(): void
    {
        $result = $this->service->fetchPreview('data:text/html,<h1>Hi</h1>');
        $this->assertNull($result);
    }

    // ------------------------------------------------------------------
    //  getPreviewsForPost()
    // ------------------------------------------------------------------

    public function test_getPreviewsForPost_returns_array(): void
    {
        $previewRow = (object) [
            'id' => 1,
            'url' => 'https://example.com',
            'title' => 'Example',
            'description' => 'Desc',
            'image_url' => null,
            'site_name' => 'Example',
            'favicon_url' => null,
            'domain' => 'example.com',
            'content_type' => 'website',
            'embed_html' => null,
        ];

        $collection = collect([$previewRow]);

        DB::shouldReceive('table->join->where->orderBy->select->get')
            ->once()
            ->andReturn($collection);

        $result = $this->service->getPreviewsForPost(1);
        $this->assertCount(1, $result);
        $this->assertEquals('https://example.com', $result[0]['url']);
    }

    // ------------------------------------------------------------------
    //  getPreviewsForMessage()
    // ------------------------------------------------------------------

    public function test_getPreviewsForMessage_returns_array(): void
    {
        $previewRow = (object) [
            'id' => 1,
            'url' => 'https://example.com',
            'title' => 'Example',
            'description' => 'Desc',
            'image_url' => null,
            'site_name' => 'Example',
            'favicon_url' => null,
            'domain' => 'example.com',
            'content_type' => 'website',
            'embed_html' => null,
        ];

        $collection = collect([$previewRow]);

        DB::shouldReceive('table->join->where->select->get')
            ->once()
            ->andReturn($collection);

        $result = $this->service->getPreviewsForMessage(42);
        $this->assertCount(1, $result);
        $this->assertEquals('Example', $result[0]['title']);
    }

    // ------------------------------------------------------------------
    //  attachPreviewToPost()
    // ------------------------------------------------------------------

    public function test_attachPreviewToPost_inserts_record(): void
    {
        DB::shouldReceive('table->insertOrIgnore')
            ->once()
            ->with([
                'post_id' => 10,
                'link_preview_id' => 5,
                'display_order' => 0,
            ])
            ->andReturn(1);

        $this->service->attachPreviewToPost(10, 5, 0);
        // No exception = pass
        $this->assertTrue(true);
    }

    // ------------------------------------------------------------------
    //  attachPreviewToMessage()
    // ------------------------------------------------------------------

    public function test_attachPreviewToMessage_inserts_record(): void
    {
        DB::shouldReceive('table->insertOrIgnore')
            ->once()
            ->with([
                'message_id' => 20,
                'link_preview_id' => 3,
            ])
            ->andReturn(1);

        $this->service->attachPreviewToMessage(20, 3);
        $this->assertTrue(true);
    }

    // ------------------------------------------------------------------
    //  batchLoadPostPreviews()
    // ------------------------------------------------------------------

    public function test_batchLoadPostPreviews_returns_empty_for_empty_input(): void
    {
        $result = $this->service->batchLoadPostPreviews([]);
        $this->assertEmpty($result);
    }

    public function test_batchLoadPostPreviews_groups_by_post_id(): void
    {
        $row1 = (object) [
            'post_id' => 10,
            'url' => 'https://a.com',
            'title' => 'A',
            'description' => null,
            'image_url' => null,
            'site_name' => null,
            'favicon_url' => null,
            'domain' => 'a.com',
            'content_type' => 'website',
            'embed_html' => null,
        ];
        $row2 = (object) [
            'post_id' => 20,
            'url' => 'https://b.com',
            'title' => 'B',
            'description' => null,
            'image_url' => null,
            'site_name' => null,
            'favicon_url' => null,
            'domain' => 'b.com',
            'content_type' => 'website',
            'embed_html' => null,
        ];

        $collection = collect([$row1, $row2]);

        DB::shouldReceive('table->join->whereIn->orderBy->orderBy->select->get')
            ->once()
            ->andReturn($collection);

        $result = $this->service->batchLoadPostPreviews([10, 20]);
        $this->assertArrayHasKey(10, $result);
        $this->assertArrayHasKey(20, $result);
        $this->assertCount(1, $result[10]);
        $this->assertEquals('A', $result[10][0]['title']);
    }

    // ------------------------------------------------------------------
    //  processPostUrls()
    // ------------------------------------------------------------------

    public function test_processPostUrls_returns_empty_when_no_urls(): void
    {
        $result = $this->service->processPostUrls(1, 'No links here.');
        $this->assertEmpty($result);
    }

    // ------------------------------------------------------------------
    //  Protocol allowlist (SSRF protection)
    // ------------------------------------------------------------------

    public function test_fetchPreview_blocks_malformed_url(): void
    {
        $result = $this->service->fetchPreview('://missing-scheme.com');
        $this->assertNull($result);
    }

    public function test_fetchPreview_blocks_url_without_scheme(): void
    {
        $result = $this->service->fetchPreview('example.com');
        $this->assertNull($result);
    }
}

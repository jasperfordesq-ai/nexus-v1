<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\UgcTranslationService;
use Illuminate\Support\Facades\Cache;
use Tests\Laravel\TestCase;

/**
 * Covers the caching / shortcut wrapper logic of UgcTranslationService.
 * The live LLM path (TranscriptionService::translate) is exercised elsewhere;
 * these tests pin the deterministic behaviour the wrapper adds: empty/same-locale
 * shortcuts, the 30-day cache, getCached(), and the canonical cache key.
 */
class UgcTranslationServiceTest extends TestCase
{
    private UgcTranslationService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->svc = new UgcTranslationService();
    }

    public function test_cache_key_is_prefixed_and_deterministic(): void
    {
        $a = UgcTranslationService::cacheKey('Hello', 'en', 'de');
        $b = UgcTranslationService::cacheKey('Hello', 'en', 'de');
        $this->assertStringStartsWith('ugc_xlat:', $a);
        $this->assertSame($a, $b);
    }

    public function test_cache_key_varies_by_target_locale(): void
    {
        $this->assertNotSame(
            UgcTranslationService::cacheKey('Hello', 'en', 'de'),
            UgcTranslationService::cacheKey('Hello', 'en', 'fr'),
        );
    }

    public function test_empty_text_short_circuits_to_source(): void
    {
        $result = $this->svc->translate('feed_post', 1, '   ', 'en', 'de');
        $this->assertSame('', $result['translated_text']);
        $this->assertSame('en', $result['source_locale']);
        $this->assertSame('de', $result['target_locale']);
        $this->assertFalse($result['cached']);
    }

    public function test_same_source_and_target_locale_short_circuits(): void
    {
        $result = $this->svc->translate('feed_post', 1, 'Hallo Welt', 'de', 'DE');
        $this->assertSame('Hallo Welt', $result['translated_text']);
        $this->assertSame('de', $result['source_locale']);
        $this->assertSame('de', $result['target_locale']);
        $this->assertFalse($result['cached']);
    }

    public function test_cache_hit_returns_cached_translation(): void
    {
        Cache::put(UgcTranslationService::cacheKey('Hello', 'en', 'de'), 'Hallo', 60);

        $result = $this->svc->translate('feed_post', 1, 'Hello', 'en', 'de');

        $this->assertSame('Hallo', $result['translated_text']);
        $this->assertTrue($result['cached']);
        $this->assertSame('en', $result['source_locale']);
        $this->assertSame('de', $result['target_locale']);
    }

    public function test_target_locale_is_lowercased_for_lookup(): void
    {
        Cache::put(UgcTranslationService::cacheKey('Hello', 'en', 'de'), 'Hallo', 60);

        $result = $this->svc->translate('feed_post', 1, 'Hello', 'en', 'DE');

        $this->assertSame('Hallo', $result['translated_text']);
        $this->assertSame('de', $result['target_locale']);
        $this->assertTrue($result['cached']);
    }

    public function test_getCached_returns_null_when_absent(): void
    {
        $this->assertNull($this->svc->getCached('Nothing here', 'en', 'de'));
    }

    public function test_getCached_returns_null_for_empty_text(): void
    {
        $this->assertNull($this->svc->getCached('   ', 'en', 'de'));
    }

    public function test_getCached_returns_value_when_present(): void
    {
        Cache::put(UgcTranslationService::cacheKey('Hi', 'en', 'fr'), 'Salut', 60);

        $result = $this->svc->getCached('Hi', 'en', 'fr');

        $this->assertNotNull($result);
        $this->assertSame('Salut', $result['translated_text']);
        $this->assertSame('fr', $result['target_locale']);
        $this->assertTrue($result['cached']);
    }
}

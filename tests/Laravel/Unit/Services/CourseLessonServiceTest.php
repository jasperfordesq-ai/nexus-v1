<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\CourseLessonService;
use PHPUnit\Framework\TestCase;

class CourseLessonServiceTest extends TestCase
{
    public function test_normalize_media_url_allows_http_and_https(): void
    {
        $this->assertSame('https://example.com/lesson.pdf', CourseLessonService::normalizeMediaUrl('https://example.com/lesson.pdf'));
        $this->assertSame('http://example.com/video.mp4', CourseLessonService::normalizeMediaUrl('http://example.com/video.mp4'));
    }

    public function test_normalize_media_url_blocks_scriptable_and_local_schemes(): void
    {
        $this->assertNull(CourseLessonService::normalizeMediaUrl('javascript:alert(1)'));
        $this->assertNull(CourseLessonService::normalizeMediaUrl('data:text/html,<script>alert(1)</script>'));
        $this->assertNull(CourseLessonService::normalizeMediaUrl('file:///C:/secret.pdf'));
        $this->assertNull(CourseLessonService::normalizeMediaUrl('not a url'));
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\BookmarkService;
use Tests\Laravel\TestCase;

class BookmarkServiceTest extends TestCase
{
    private BookmarkService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BookmarkService();
    }

    // ── validateType (indirect via toggle) ───────────────────────────

    public function test_toggle_throws_InvalidArgument_for_unsupported_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid bookmarkable type: not_a_thing');

        $this->service->toggle(1, 'not_a_thing', 42);
    }

    public function test_getUserBookmarks_throws_on_invalid_type_filter(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid bookmarkable type: bogus');

        $this->service->getUserBookmarks(1, 'bogus');
    }

    public function test_toggle_accepts_all_whitelisted_types(): void
    {
        // Reflection to verify the VALID_TYPES constant exposes the expected set.
        $ref = new \ReflectionClass(BookmarkService::class);
        $validTypes = $ref->getConstant('VALID_TYPES');

        $this->assertContains('post', $validTypes);
        $this->assertContains('listing', $validTypes);
        $this->assertContains('event', $validTypes);
        $this->assertContains('job', $validTypes);
        $this->assertContains('blog', $validTypes);
        $this->assertContains('discussion', $validTypes);
    }

    public function test_validateType_is_case_sensitive(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        // 'Post' with a capital P is NOT in the whitelist
        $this->service->toggle(1, 'Post', 42);
    }

    public function test_getUserBookmarks_accepts_null_type_without_throwing(): void
    {
        // The validation only fires when type is non-null. With null, the
        // method must proceed to the paginate() call, which will hit the DB.
        // We only assert that no InvalidArgumentException is thrown for the
        // null case — any DB-level error is fine for this unit boundary.
        try {
            $this->service->getUserBookmarks(1, null);
            $this->assertTrue(true);
        } catch (\InvalidArgumentException $e) {
            $this->fail('Should not throw InvalidArgumentException for null type');
        } catch (\Throwable $e) {
            // Any other error (e.g. DB/schema) is outside the scope of this guard test.
            $this->assertTrue(true);
        }
    }
}

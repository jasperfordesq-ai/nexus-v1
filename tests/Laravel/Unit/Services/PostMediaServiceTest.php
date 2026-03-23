<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\PostMediaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Laravel\TestCase;

/**
 * Unit tests for PostMediaService — media management, ownership checks, reorder, alt text.
 */
class PostMediaServiceTest extends TestCase
{
    private PostMediaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PostMediaService();
    }

    // ------------------------------------------------------------------
    //  isPostOwnedByUser()
    // ------------------------------------------------------------------

    public function test_isPostOwnedByUser_returns_true_when_owned(): void
    {
        DB::shouldReceive('table->where->where->where->exists')
            ->once()
            ->andReturn(true);

        $result = $this->service->isPostOwnedByUser(10, 5);
        $this->assertTrue($result);
    }

    public function test_isPostOwnedByUser_returns_false_when_not_owned(): void
    {
        DB::shouldReceive('table->where->where->where->exists')
            ->once()
            ->andReturn(false);

        $result = $this->service->isPostOwnedByUser(10, 999);
        $this->assertFalse($result);
    }

    // ------------------------------------------------------------------
    //  isMediaOwnedByUser()
    // ------------------------------------------------------------------

    public function test_isMediaOwnedByUser_returns_true_when_owned(): void
    {
        DB::shouldReceive('table->join->where->where->where->exists')
            ->once()
            ->andReturn(true);

        $result = $this->service->isMediaOwnedByUser(1, 5);
        $this->assertTrue($result);
    }

    public function test_isMediaOwnedByUser_returns_false_when_not_owned(): void
    {
        DB::shouldReceive('table->join->where->where->where->exists')
            ->once()
            ->andReturn(false);

        $result = $this->service->isMediaOwnedByUser(1, 999);
        $this->assertFalse($result);
    }

    // ------------------------------------------------------------------
    //  getMediaForPost()
    // ------------------------------------------------------------------

    public function test_getMediaForPost_returns_formatted_array(): void
    {
        $row = (object) [
            'id' => 1,
            'media_type' => 'image',
            'file_url' => '/uploads/posts/2/10/img.jpg',
            'thumbnail_url' => '/uploads/posts/2/10/thumbs/img.jpg',
            'alt_text' => 'A test image',
            'width' => 800,
            'height' => 600,
            'file_size' => 150000,
            'display_order' => 0,
        ];

        $collection = collect([$row]);

        DB::shouldReceive('table->where->where->orderBy->get')
            ->once()
            ->andReturn($collection);

        $result = $this->service->getMediaForPost(10);
        $this->assertCount(1, $result);
        $this->assertEquals(1, $result[0]['id']);
        $this->assertEquals('image', $result[0]['media_type']);
        $this->assertEquals('/uploads/posts/2/10/img.jpg', $result[0]['file_url']);
        $this->assertEquals('A test image', $result[0]['alt_text']);
        $this->assertEquals(800, $result[0]['width']);
        $this->assertEquals(600, $result[0]['height']);
        $this->assertEquals(150000, $result[0]['file_size']);
        $this->assertEquals(0, $result[0]['display_order']);
    }

    public function test_getMediaForPost_returns_empty_array_when_none(): void
    {
        $collection = collect([]);

        DB::shouldReceive('table->where->where->orderBy->get')
            ->once()
            ->andReturn($collection);

        $result = $this->service->getMediaForPost(999);
        $this->assertEmpty($result);
    }

    // ------------------------------------------------------------------
    //  getMediaForPosts() — bulk loading
    // ------------------------------------------------------------------

    public function test_getMediaForPosts_returns_empty_for_empty_input(): void
    {
        $result = $this->service->getMediaForPosts([]);
        $this->assertEmpty($result);
    }

    public function test_getMediaForPosts_groups_by_post_id(): void
    {
        $row1 = (object) [
            'post_id' => 10,
            'id' => 1,
            'media_type' => 'image',
            'file_url' => '/uploads/posts/2/10/a.jpg',
            'thumbnail_url' => null,
            'alt_text' => null,
            'width' => 400,
            'height' => 300,
            'file_size' => 50000,
            'display_order' => 0,
        ];
        $row2 = (object) [
            'post_id' => 20,
            'id' => 2,
            'media_type' => 'image',
            'file_url' => '/uploads/posts/2/20/b.jpg',
            'thumbnail_url' => null,
            'alt_text' => null,
            'width' => 1024,
            'height' => 768,
            'file_size' => 200000,
            'display_order' => 0,
        ];

        $collection = collect([$row1, $row2]);

        DB::shouldReceive('table->where->whereIn->orderBy->orderBy->get')
            ->once()
            ->andReturn($collection);

        $result = $this->service->getMediaForPosts([10, 20]);
        $this->assertArrayHasKey(10, $result);
        $this->assertArrayHasKey(20, $result);
        $this->assertCount(1, $result[10]);
        $this->assertCount(1, $result[20]);
    }

    // ------------------------------------------------------------------
    //  reorderMedia()
    // ------------------------------------------------------------------

    public function test_reorderMedia_updates_display_order(): void
    {
        // Expect 3 update calls, one per media ID
        DB::shouldReceive('table->where->where->where->update')
            ->times(3);

        $this->service->reorderMedia(10, [5, 3, 7]);
        // No exception = pass
        $this->assertTrue(true);
    }

    // ------------------------------------------------------------------
    //  updateAltText()
    // ------------------------------------------------------------------

    public function test_updateAltText_updates_record(): void
    {
        DB::shouldReceive('table->where->where->update')
            ->once()
            ->with(['alt_text' => 'Updated description']);

        $this->service->updateAltText(1, 'Updated description');
        $this->assertTrue(true);
    }

    public function test_updateAltText_truncates_long_text(): void
    {
        $longText = str_repeat('a', 600);
        $expected = mb_substr($longText, 0, 500);

        DB::shouldReceive('table->where->where->update')
            ->once()
            ->with(['alt_text' => $expected]);

        $this->service->updateAltText(1, $longText);
        $this->assertTrue(true);
    }

    // ------------------------------------------------------------------
    //  removeMedia()
    // ------------------------------------------------------------------

    public function test_removeMedia_deletes_record_and_file(): void
    {
        $mediaRow = (object) [
            'id' => 1,
            'file_url' => '/uploads/posts/2/10/test.jpg',
            'thumbnail_url' => '/uploads/posts/2/10/thumbs/test.jpg',
        ];

        DB::shouldReceive('table->where->where->first')
            ->once()
            ->andReturn($mediaRow);

        DB::shouldReceive('table->where->where->delete')
            ->once();

        $this->service->removeMedia(1);
        $this->assertTrue(true);
    }

    public function test_removeMedia_does_nothing_when_media_not_found(): void
    {
        DB::shouldReceive('table->where->where->first')
            ->once()
            ->andReturn(null);

        // delete should NOT be called
        DB::shouldNotReceive('table->where->where->delete');

        $this->service->removeMedia(999);
        $this->assertTrue(true);
    }

    // ------------------------------------------------------------------
    //  attachMedia() — validation without file system
    // ------------------------------------------------------------------

    public function test_attachMedia_returns_empty_when_max_slots_reached(): void
    {
        // Existing count already at max (10)
        DB::shouldReceive('table->where->where->count')
            ->once()
            ->andReturn(10);

        $result = $this->service->attachMedia(10, []);
        $this->assertEmpty($result);
    }

    public function test_attachMedia_returns_empty_when_no_valid_files(): void
    {
        // Existing count is 0 — slots are available
        DB::shouldReceive('table->where->where->count')
            ->once()
            ->andReturn(0);

        DB::shouldReceive('table->where->where->max')
            ->once()
            ->andReturn(0);

        // Pass an array with a non-UploadedFile item
        $result = $this->service->attachMedia(10, ['not-a-file']);
        $this->assertEmpty($result);
    }

    // ------------------------------------------------------------------
    //  formatMedia() — null handling
    // ------------------------------------------------------------------

    public function test_getMediaForPost_handles_null_dimensions(): void
    {
        $row = (object) [
            'id' => 1,
            'media_type' => 'image',
            'file_url' => '/uploads/posts/2/10/img.jpg',
            'thumbnail_url' => null,
            'alt_text' => null,
            'width' => null,
            'height' => null,
            'file_size' => null,
            'display_order' => 0,
        ];

        $collection = collect([$row]);

        DB::shouldReceive('table->where->where->orderBy->get')
            ->once()
            ->andReturn($collection);

        $result = $this->service->getMediaForPost(10);
        $this->assertNull($result[0]['width']);
        $this->assertNull($result[0]['height']);
        $this->assertNull($result[0]['file_size']);
        $this->assertNull($result[0]['alt_text']);
    }
}

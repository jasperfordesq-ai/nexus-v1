<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\ImageUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Laravel\TestCase;

class ImageUploadServiceTest extends TestCase
{
    private ImageUploadService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ImageUploadService();
    }

    // ─── upload ──────────────────────────────────────────────────

    /** @var list<string> Temp files created during a test, cleaned up in tearDown. */
    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
        $this->tmpFiles = [];
        parent::tearDown();
    }

    /**
     * Create a real temp file of the requested byte size and track it for cleanup.
     * The service reads the real path via getPathname() + filesize().
     */
    private function makeTempFile(int $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'img_upload_test_');
        $this->tmpFiles[] = $path;
        // Allocate the requested size without writing every byte.
        $fh = fopen($path, 'wb');
        if ($bytes > 0) {
            fseek($fh, $bytes - 1);
            fwrite($fh, "\0");
        }
        fclose($fh);
        return $path;
    }

    public function test_upload_exceeds_max_size_throws(): void
    {
        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('getPathname')->andReturn($this->makeTempFile(11 * 1024 * 1024)); // > 10MB limit

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File exceeds maximum size');

        $this->service->upload($file);
    }

    public function test_upload_invalid_mime_type_throws(): void
    {
        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('getPathname')->andReturn($this->makeTempFile(1024));
        $file->shouldReceive('getMimeType')->andReturn('application/pdf');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid file type');

        $this->service->upload($file);
    }

    public function test_upload_valid_image_stores_and_returns_result(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('test.jpg', 1280, 720)->size(512);

        $result = $this->service->upload($file);

        $this->assertArrayHasKey('path', $result);
        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('filename', $result);
        $this->assertArrayHasKey('width', $result);
        $this->assertArrayHasKey('height', $result);
        $this->assertArrayHasKey('thumbnail_url', $result);
        $this->assertArrayHasKey('variants', $result);
        $this->assertArrayHasKey('srcsets', $result);
        $this->assertSame(1280, $result['width']);
        $this->assertSame(720, $result['height']);
        $this->assertTrue(Storage::disk('public')->exists($result['path']));
    }

    // ─── delete ──────────────────────────────────────────────────

    public function test_delete_empty_path_returns_false(): void
    {
        $this->assertFalse($this->service->delete(''));
    }

    public function test_delete_nonexistent_file_returns_false(): void
    {
        Storage::shouldReceive('disk')->with('public')->andReturnSelf();
        Storage::shouldReceive('exists')->with('some/path.jpg')->andReturn(false);

        $this->assertFalse($this->service->delete('some/path.jpg'));
    }

    public function test_delete_existing_file_returns_true(): void
    {
        Storage::shouldReceive('disk')->with('public')->andReturnSelf();
        Storage::shouldReceive('exists')->with('some/path.jpg')->andReturn(true);
        Storage::shouldReceive('delete')->with('some/path.jpg')->andReturn(true);

        $this->assertTrue($this->service->delete('some/path.jpg'));
    }

    // ─── getUrl ──────────────────────────────────────────────────

    public function test_getUrl_empty_path_returns_null(): void
    {
        $this->assertNull($this->service->getUrl(''));
    }

    public function test_getUrl_null_path_returns_null(): void
    {
        $this->assertNull($this->service->getUrl(null));
    }

    public function test_getUrl_valid_path_returns_url(): void
    {
        Storage::shouldReceive('disk')->with('public')->andReturnSelf();
        Storage::shouldReceive('url')->with('tenant_2/uploads/img.jpg')->andReturn('/storage/tenant_2/uploads/img.jpg');

        $result = $this->service->getUrl('tenant_2/uploads/img.jpg');
        $this->assertSame('/storage/tenant_2/uploads/img.jpg', $result);
    }
}

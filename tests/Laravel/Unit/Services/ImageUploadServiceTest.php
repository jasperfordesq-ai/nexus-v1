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

    public function test_upload_exceeds_max_size_throws(): void
    {
        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('getSize')->andReturn(20 * 1024 * 1024); // 20MB

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File exceeds maximum size');

        $this->service->upload($file);
    }

    public function test_upload_invalid_mime_type_throws(): void
    {
        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('getSize')->andReturn(1024);
        $file->shouldReceive('getMimeType')->andReturn('application/pdf');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid file type');

        $this->service->upload($file);
    }

    public function test_upload_valid_image_stores_and_returns_result(): void
    {
        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('getSize')->andReturn(1024);
        $file->shouldReceive('getMimeType')->andReturn('image/jpeg');
        $file->shouldReceive('getClientOriginalExtension')->andReturn('jpg');
        $file->shouldReceive('storeAs')->once()->andReturn('tenant_2/uploads/test.jpg');

        Storage::shouldReceive('disk')->with('public')->andReturnSelf();
        Storage::shouldReceive('url')->with('tenant_2/uploads/test.jpg')->andReturn('/storage/tenant_2/uploads/test.jpg');

        $result = $this->service->upload($file);

        $this->assertArrayHasKey('path', $result);
        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('filename', $result);
        $this->assertSame('tenant_2/uploads/test.jpg', $result['path']);
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

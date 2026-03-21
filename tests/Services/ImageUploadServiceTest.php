<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\ImageUploadService;
use App\Core\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * ImageUploadService Tests
 */
class ImageUploadServiceTest extends TestCase
{
    private ImageUploadService $service;
    private static int $testTenantId = 2;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);
        Storage::fake('public');
        $this->service = new ImageUploadService();
    }

    public function test_service_can_be_instantiated(): void
    {
        $this->assertInstanceOf(ImageUploadService::class, $this->service);
    }

    public function test_upload_valid_jpeg(): void
    {
        $file = UploadedFile::fake()->image('test.jpg', 100, 100)->size(500);
        $result = $this->service->upload($file, 'avatars');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('path', $result);
        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('filename', $result);
        $this->assertNotEmpty($result['path']);
        $this->assertNotEmpty($result['filename']);
    }

    public function test_upload_valid_png(): void
    {
        $file = UploadedFile::fake()->image('test.png', 100, 100)->size(500);
        $result = $this->service->upload($file);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('path', $result);
    }

    public function test_upload_scopes_by_tenant(): void
    {
        $file = UploadedFile::fake()->image('test.jpg', 100, 100)->size(500);
        $result = $this->service->upload($file, 'uploads');

        $this->assertStringContainsString('tenant_' . self::$testTenantId, $result['path']);
    }

    public function test_upload_rejects_oversized_file(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File exceeds maximum size');

        // Create a file larger than 10 MB
        $file = UploadedFile::fake()->image('large.jpg', 100, 100)->size(11000);
        $this->service->upload($file);
    }

    public function test_upload_rejects_invalid_mime_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid file type');

        $file = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');
        $this->service->upload($file);
    }

    public function test_delete_nonexistent_path_returns_false(): void
    {
        $result = $this->service->delete('nonexistent/path/file.jpg');
        $this->assertFalse($result);
    }

    public function test_delete_empty_path_returns_false(): void
    {
        $result = $this->service->delete('');
        $this->assertFalse($result);
    }

    public function test_get_url_returns_null_for_empty_path(): void
    {
        $result = $this->service->getUrl('');
        $this->assertNull($result);

        $result = $this->service->getUrl(null);
        $this->assertNull($result);
    }

    public function test_get_url_returns_string_for_valid_path(): void
    {
        $result = $this->service->getUrl('some/path/image.jpg');
        $this->assertIsString($result);
    }

    public function test_upload_and_delete_roundtrip(): void
    {
        $file = UploadedFile::fake()->image('roundtrip.jpg', 100, 100)->size(100);
        $result = $this->service->upload($file);

        Storage::disk('public')->assertExists($result['path']);

        $deleted = $this->service->delete($result['path']);
        $this->assertTrue($deleted);

        Storage::disk('public')->assertMissing($result['path']);
    }
}

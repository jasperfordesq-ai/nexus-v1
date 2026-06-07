<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\ImageUploadService;
use Illuminate\Http\UploadedFile;

/**
 * The legacy App\Services\UploadService (a thin wrapper around the removed
 * \Nexus\Services\UploadService) was deleted during the Laravel migration
 * (commit aa27af479). Upload functionality now lives in ImageUploadService,
 * which exposes upload()/delete()/getUrl() instead of the old handleUpload().
 * These tests assert the current contract.
 */
class UploadServiceTest extends \Tests\Laravel\TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(ImageUploadService::class));
    }

    public function testPublicMethodsExist(): void
    {
        $this->assertTrue(method_exists(ImageUploadService::class, 'upload'));
        $this->assertTrue(method_exists(ImageUploadService::class, 'delete'));
        $this->assertTrue(method_exists(ImageUploadService::class, 'getUrl'));
    }

    public function testUploadMethodSignature(): void
    {
        $ref = new \ReflectionMethod(ImageUploadService::class, 'upload');
        $this->assertFalse($ref->isStatic());

        $params = $ref->getParameters();
        $this->assertEquals('file', $params[0]->getName());
        $this->assertSame(UploadedFile::class, $params[0]->getType()?->getName());

        $this->assertEquals('directory', $params[1]->getName());
        $this->assertTrue($params[1]->isOptional());
    }
}

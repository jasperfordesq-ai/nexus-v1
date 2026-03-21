<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Core;

use App\Core\ImageUploader;
use PHPUnit\Framework\TestCase;

class ImageUploaderTest extends TestCase
{
    // -------------------------------------------------------
    // upload() — validation
    // -------------------------------------------------------

    public function test_upload_returns_null_for_empty_file_name(): void
    {
        $result = ImageUploader::upload(['name' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_OK, 'size' => 0]);
        $this->assertNull($result);
    }

    public function test_upload_throws_on_upload_error(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Upload Error Code');
        ImageUploader::upload([
            'name' => 'test.jpg',
            'tmp_name' => '/tmp/test.jpg',
            'error' => UPLOAD_ERR_PARTIAL,
            'size' => 100,
        ]);
    }

    public function test_upload_throws_on_invalid_extension(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid file extension');
        ImageUploader::upload([
            'name' => 'test.exe',
            'tmp_name' => '/tmp/test.exe',
            'error' => UPLOAD_ERR_OK,
            'size' => 100,
        ]);
    }

    // -------------------------------------------------------
    // setAutoConvertWebP()
    // -------------------------------------------------------

    public function test_setAutoConvertWebP_does_not_throw(): void
    {
        ImageUploader::setAutoConvertWebP(false);
        ImageUploader::setAutoConvertWebP(true);
        $this->assertTrue(true);
    }

    // -------------------------------------------------------
    // setMaxDimension()
    // -------------------------------------------------------

    public function test_setMaxDimension_does_not_throw(): void
    {
        ImageUploader::setMaxDimension(1024);
        ImageUploader::setMaxDimension(1920);
        $this->assertTrue(true);
    }
}

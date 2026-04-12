<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Admin;

use App\Admin\WebPConverter;
use Tests\Laravel\TestCase;

class WebPConverterTest extends TestCase
{
    private WebPConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->converter = new WebPConverter();
    }

    public function test_getInstallInstructions_includes_platforms(): void
    {
        $instructions = $this->converter->getInstallInstructions();

        $this->assertStringContainsString('Ubuntu', $instructions);
        $this->assertStringContainsString('macOS', $instructions);
        $this->assertStringContainsString('Windows', $instructions);
        $this->assertStringContainsString('cwebp', $instructions);
    }

    public function test_convertImage_returns_failure_when_file_missing(): void
    {
        $result = $this->converter->convertImage('/tmp/nonexistent-' . uniqid() . '.jpg');

        $this->assertFalse($result['success']);
        $this->assertSame('File not found', $result['message']);
    }

    public function test_convertImage_rejects_invalid_extension(): void
    {
        // Create a temporary file with bad extension
        $tmpPath = tempnam(sys_get_temp_dir(), 'webp_test_') . '.txt';
        file_put_contents($tmpPath, 'not an image');

        try {
            $result = $this->converter->convertImage($tmpPath);

            $this->assertFalse($result['success']);
            $this->assertStringContainsString('Invalid file type', $result['message']);
        } finally {
            @unlink($tmpPath);
        }
    }

    public function test_convertOnUpload_rejects_non_image_mime(): void
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'webp_test_') . '.txt';
        file_put_contents($tmpPath, 'plain text content');

        try {
            $result = $this->converter->convertOnUpload($tmpPath);

            $this->assertFalse($result['success']);
            $this->assertSame('Not an image', $result['message']);
        } finally {
            @unlink($tmpPath);
        }
    }

    public function test_setQuality_clamps_to_valid_range(): void
    {
        $reflection = new \ReflectionObject($this->converter);
        $qualityProp = $reflection->getProperty('quality');
        $qualityProp->setAccessible(true);

        $this->converter->setQuality(-50);
        $this->assertSame(0, $qualityProp->getValue($this->converter));

        $this->converter->setQuality(500);
        $this->assertSame(100, $qualityProp->getValue($this->converter));

        $this->converter->setQuality(75);
        $this->assertSame(75, $qualityProp->getValue($this->converter));
    }

    public function test_resizeImage_returns_failure_when_file_missing(): void
    {
        $result = $this->converter->resizeImage('/tmp/nonexistent-' . uniqid() . '.jpg');

        $this->assertFalse($result['success']);
        $this->assertSame('File not found', $result['message']);
    }

    public function test_isCwebpAvailable_returns_bool(): void
    {
        // Result depends on environment — just ensure it returns a bool
        $result = $this->converter->isCwebpAvailable();
        $this->assertIsBool($result);
    }
}

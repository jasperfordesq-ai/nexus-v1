<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Core;

use App\Tests\TestCase;
use App\Core\AudioUploader;

/**
 * AudioUploader Tests
 * @covers \App\Core\AudioUploader
 */
class AudioUploaderTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(AudioUploader::class));
    }

    public function testPublicMethodsExist(): void
    {
        $methods = ['upload', 'validate'];
        foreach ($methods as $method) {
            $this->assertTrue(method_exists(AudioUploader::class, $method), "Method {$method} should exist");
        }
    }
}

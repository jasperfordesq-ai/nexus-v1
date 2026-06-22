<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Support;

use App\Support\LogoShape;
use Tests\Laravel\TestCase;

class LogoShapeTest extends TestCase
{
    public function testClassifyFallsBackToLandscapeForNullOrEmpty(): void
    {
        $this->assertSame('landscape', LogoShape::classify(null));
        $this->assertSame('landscape', LogoShape::classify(''));
    }

    public function testClassifyFallsBackForRemoteUrl(): void
    {
        // Only local /uploads/ paths are measurable; anything else is unmeasurable.
        $this->assertSame('landscape', LogoShape::classify('https://cdn.example.com/logo.png'));
    }

    public function testClassifyFallsBackForMissingLocalFile(): void
    {
        $this->assertSame('landscape', LogoShape::classify('/uploads/__does_not_exist_xyz__.png'));
    }

    public function testToneReturnsNullForNullOrEmpty(): void
    {
        $this->assertNull(LogoShape::tone(null));
        $this->assertNull(LogoShape::tone(''));
    }

    public function testToneReturnsNullForRemoteUrl(): void
    {
        $this->assertNull(LogoShape::tone('https://cdn.example.com/logo.png'));
    }

    public function testToneReturnsNullForMissingLocalFile(): void
    {
        $this->assertNull(LogoShape::tone('/uploads/__does_not_exist_xyz__.png'));
    }

    public function testClassifyAlwaysReturnsAKnownBucket(): void
    {
        foreach ([null, '', '/uploads/missing.png', 'https://x/y.svg'] as $input) {
            $this->assertContains(LogoShape::classify($input), ['wide', 'landscape', 'square']);
        }
    }
}

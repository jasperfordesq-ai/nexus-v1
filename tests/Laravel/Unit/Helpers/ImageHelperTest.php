<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Helpers;

use App\Helpers\ImageHelper;
use PHPUnit\Framework\TestCase;

class ImageHelperTest extends TestCase
{
    // -------------------------------------------------------
    // webp()
    // -------------------------------------------------------

    public function test_webp_returns_img_tag_for_non_webp_image(): void
    {
        $result = ImageHelper::webp('/assets/img/test.png', 'Test Image', 'my-class');
        // Since WebP probably doesn't exist in test environment, should return <img> tag
        $this->assertStringContainsString('<img', $result);
        $this->assertStringContainsString('test.png', $result);
        $this->assertStringContainsString('alt="Test Image"', $result);
    }

    public function test_webp_applies_css_class(): void
    {
        $result = ImageHelper::webp('/assets/img/test.png', 'Alt', 'hero-img');
        $this->assertStringContainsString('class="hero-img"', $result);
    }

    public function test_webp_adds_lazy_loading_by_default(): void
    {
        $result = ImageHelper::webp('/assets/img/test.png', 'Alt');
        $this->assertStringContainsString('loading="lazy"', $result);
    }

    public function test_webp_uses_default_avatar_for_empty_path(): void
    {
        $result = ImageHelper::webp('', 'No Image');
        $this->assertStringContainsString('default_avatar', $result);
    }

    public function test_webp_includes_custom_attributes(): void
    {
        $result = ImageHelper::webp('/test.png', 'Alt', '', ['data-id' => '42']);
        $this->assertStringContainsString('data-id="42"', $result);
    }

    // -------------------------------------------------------
    // responsive()
    // -------------------------------------------------------

    public function test_responsive_returns_picture_tag(): void
    {
        $result = ImageHelper::responsive('/assets/img/hero.jpg', 'Hero', [320, 640]);
        $this->assertStringContainsString('<picture>', $result);
        $this->assertStringContainsString('</picture>', $result);
        $this->assertStringContainsString('srcset=', $result);
    }

    public function test_responsive_includes_webp_source(): void
    {
        $result = ImageHelper::responsive('/assets/img/hero.jpg', 'Hero', [320]);
        $this->assertStringContainsString('type="image/webp"', $result);
        $this->assertStringContainsString('.webp', $result);
    }

    public function test_responsive_includes_size_descriptors(): void
    {
        $result = ImageHelper::responsive('/assets/img/hero.jpg', 'Hero', [320, 640]);
        $this->assertStringContainsString('320w', $result);
        $this->assertStringContainsString('640w', $result);
    }

    // -------------------------------------------------------
    // avatar()
    // -------------------------------------------------------

    public function test_avatar_returns_img_html(): void
    {
        $result = ImageHelper::avatar('/uploads/avatar.jpg', 'John Doe', 50);
        $this->assertStringContainsString('<img', $result);
        $this->assertStringContainsString('John Doe', $result);
    }

    public function test_avatar_uses_default_for_null(): void
    {
        $result = ImageHelper::avatar(null, 'User');
        $this->assertStringContainsString('default_avatar', $result);
    }

    public function test_avatar_uses_default_for_null_string(): void
    {
        $result = ImageHelper::avatar('null', 'User');
        $this->assertStringContainsString('default_avatar', $result);
    }

    public function test_avatar_uses_default_for_undefined_string(): void
    {
        $result = ImageHelper::avatar('undefined', 'User');
        $this->assertStringContainsString('default_avatar', $result);
    }

    public function test_avatar_includes_size_attributes(): void
    {
        $result = ImageHelper::avatar('/avatar.jpg', 'User', 60);
        $this->assertStringContainsString('width="60"', $result);
        $this->assertStringContainsString('height="60"', $result);
    }

    // -------------------------------------------------------
    // browserSupportsWebP()
    // -------------------------------------------------------

    public function test_browserSupportsWebP_returns_false_without_accept_header(): void
    {
        unset($_SERVER['HTTP_ACCEPT']);
        $this->assertFalse(ImageHelper::browserSupportsWebP());
    }

    public function test_browserSupportsWebP_returns_true_with_webp_accept(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'text/html,image/webp,image/apng,*/*;q=0.8';
        $this->assertTrue(ImageHelper::browserSupportsWebP());
    }

    public function test_browserSupportsWebP_returns_false_without_webp_accept(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'text/html,image/png,*/*;q=0.8';
        $this->assertFalse(ImageHelper::browserSupportsWebP());
    }
}

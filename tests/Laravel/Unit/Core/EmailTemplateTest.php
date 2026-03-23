<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Core;

use App\Core\EmailTemplate;
use Tests\Laravel\TestCase;

class EmailTemplateTest extends TestCase
{
    // -------------------------------------------------------
    // render()
    // -------------------------------------------------------

    public function test_render_returns_html_string(): void
    {
        $html = EmailTemplate::render('Test Title', 'Subtitle', '<p>Body</p>');
        $this->assertIsString($html);
        $this->assertStringContainsString('<!DOCTYPE html', $html);
    }

    public function test_render_includes_title(): void
    {
        $html = EmailTemplate::render('Welcome Email', 'Sub', 'Body text');
        $this->assertStringContainsString('Welcome Email', $html);
    }

    public function test_render_includes_subtitle(): void
    {
        $html = EmailTemplate::render('Title', 'Important Subtitle', 'Body');
        $this->assertStringContainsString('Important Subtitle', $html);
    }

    public function test_render_includes_body(): void
    {
        $html = EmailTemplate::render('Title', 'Sub', '<p>Hello World</p>');
        $this->assertStringContainsString('Hello World', $html);
    }

    public function test_render_includes_button_when_provided(): void
    {
        $html = EmailTemplate::render('Title', 'Sub', 'Body', 'Click Me', 'https://example.com');
        $this->assertStringContainsString('Click Me', $html);
        $this->assertStringContainsString('https://example.com', $html);
    }

    public function test_render_excludes_button_when_not_provided(): void
    {
        $html = EmailTemplate::render('Title', 'Sub', 'Body');
        // Should not contain the CTA button table structure (class is still in CSS)
        $this->assertStringNotContainsString('<!-- CTA Button -->', $html);
    }

    public function test_render_uses_custom_tenant_name(): void
    {
        $html = EmailTemplate::render('Title', 'Sub', 'Body', null, null, 'My Timebank');
        $this->assertStringContainsString('My Timebank', $html);
    }

    public function test_render_uses_default_tenant_name(): void
    {
        $html = EmailTemplate::render('Title', 'Sub', 'Body');
        $this->assertStringContainsString('Project NEXUS', $html);
    }

    public function test_render_includes_current_year(): void
    {
        $html = EmailTemplate::render('Title', 'Sub', 'Body');
        $this->assertStringContainsString(date('Y'), $html);
    }

    public function test_render_includes_manage_notification_link(): void
    {
        $html = EmailTemplate::render('Title', 'Sub', 'Body');
        $this->assertStringContainsString('Manage Notification Preferences', $html);
    }

    public function test_render_includes_responsive_styles(): void
    {
        $html = EmailTemplate::render('Title', 'Sub', 'Body');
        $this->assertStringContainsString('@media screen and (max-width: 600px)', $html);
    }

    public function test_render_includes_dark_mode_styles(): void
    {
        $html = EmailTemplate::render('Title', 'Sub', 'Body');
        $this->assertStringContainsString('prefers-color-scheme: dark', $html);
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\NewsletterTemplates;

class NewsletterTemplatesTest extends TestCase
{
    public function testGetTemplatesReturnsAllTemplates(): void
    {
        $templates = NewsletterTemplates::getTemplates();

        $this->assertIsArray($templates);
        $this->assertArrayHasKey('blank', $templates);
        $this->assertArrayHasKey('announcement', $templates);
        $this->assertArrayHasKey('weekly_update', $templates);
        $this->assertArrayHasKey('event_invite', $templates);
        $this->assertArrayHasKey('welcome', $templates);
        $this->assertArrayHasKey('feature_spotlight', $templates);
        $this->assertArrayHasKey('community_digest', $templates);
        $this->assertArrayHasKey('promotional', $templates);
        $this->assertArrayHasKey('thank_you', $templates);
        $this->assertArrayHasKey('survey_feedback', $templates);
    }

    public function testGetTemplatesHasRequiredMetadata(): void
    {
        $templates = NewsletterTemplates::getTemplates();

        foreach ($templates as $id => $meta) {
            $this->assertArrayHasKey('name', $meta, "Template '{$id}' missing 'name'");
            $this->assertArrayHasKey('description', $meta, "Template '{$id}' missing 'description'");
            $this->assertArrayHasKey('icon', $meta, "Template '{$id}' missing 'icon'");
            $this->assertArrayHasKey('category', $meta, "Template '{$id}' missing 'category'");
        }
    }

    public function testGetTemplateReturnsContentForValidId(): void
    {
        $template = NewsletterTemplates::getTemplate('announcement');

        $this->assertIsArray($template);
        $this->assertArrayHasKey('subject', $template);
        $this->assertArrayHasKey('preview_text', $template);
        $this->assertArrayHasKey('content', $template);
        $this->assertNotEmpty($template['subject']);
        $this->assertNotEmpty($template['content']);
    }

    public function testGetTemplateReturnsBlankForInvalidId(): void
    {
        $template = NewsletterTemplates::getTemplate('nonexistent_template');

        $this->assertIsArray($template);
        $this->assertEquals('', $template['subject']);
        $this->assertEquals('', $template['content']);
    }

    public function testGetTemplateBlankIsEmpty(): void
    {
        $template = NewsletterTemplates::getTemplate('blank');

        $this->assertEquals('', $template['subject']);
        $this->assertEquals('', $template['preview_text']);
        $this->assertEquals('', $template['content']);
    }

    public function testAllTemplatesContainHTMLContent(): void
    {
        $templateIds = array_keys(NewsletterTemplates::getTemplates());

        foreach ($templateIds as $id) {
            if ($id === 'blank') continue;

            $template = NewsletterTemplates::getTemplate($id);
            $this->assertStringContainsString('<table', $template['content'], "Template '{$id}' should contain HTML table elements");
        }
    }

    public function testProcessTemplateReplacesTenantName(): void
    {
        $template = NewsletterTemplates::getTemplate('announcement');
        $processed = NewsletterTemplates::processTemplate($template, 'Test Community');

        $this->assertStringContainsString('Test Community', $processed['subject']);
        $this->assertStringNotContainsString('{{tenant_name}}', $processed['subject']);
    }

    public function testProcessTemplateReplacesInAllFields(): void
    {
        $template = NewsletterTemplates::getTemplate('welcome');
        $processed = NewsletterTemplates::processTemplate($template, 'My Timebank');

        $this->assertStringNotContainsString('{{tenant_name}}', $processed['subject']);
        $this->assertStringNotContainsString('{{tenant_name}}', $processed['preview_text']);
        $this->assertStringNotContainsString('{{tenant_name}}', $processed['content']);
    }

    public function testTemplateCountMatchesMetadata(): void
    {
        $metadata = NewsletterTemplates::getTemplates();
        $count = count($metadata);

        $this->assertEquals(10, $count, 'Expected 10 newsletter templates');
    }

    public function testTemplatesContainPlaceholders(): void
    {
        $templateIds = ['announcement', 'weekly_update', 'welcome', 'thank_you', 'survey_feedback'];

        foreach ($templateIds as $id) {
            $template = NewsletterTemplates::getTemplate($id);
            $hasFirstName = strpos($template['content'], '{{first_name}}') !== false;
            $hasTenantName = strpos($template['content'], '{{tenant_name}}') !== false ||
                             strpos($template['subject'], '{{tenant_name}}') !== false;

            $this->assertTrue(
                $hasFirstName || $hasTenantName,
                "Template '{$id}' should contain personalization placeholders"
            );
        }
    }
}

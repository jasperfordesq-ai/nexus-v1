<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Models\NewsletterTemplate;
use Database\Seeders\DefaultNewsletterTemplatesSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Laravel\TestCase;

class NewsletterTemplatesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_factory_creates_template_with_schema_category(): void
    {
        $template = NewsletterTemplate::factory()
            ->forTenant($this->testTenantId)
            ->create(['created_by' => null]);

        $this->assertContains($template->category, ['starter', 'custom', 'saved']);
    }

    public function test_default_templates_seeder_uses_starter_category(): void
    {
        NewsletterTemplate::query()
            ->where('tenant_id', $this->testTenantId)
            ->delete();

        $this->seed(DefaultNewsletterTemplatesSeeder::class);

        $template = NewsletterTemplate::query()
            ->where('tenant_id', $this->testTenantId)
            ->first();

        $this->assertNotNull($template);
        $this->assertSame('starter', $template->category);
    }
}

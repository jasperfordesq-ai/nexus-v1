<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Controllers;

use PHPUnit\Framework\TestCase;

class FederationV2ControllerSourceTest extends TestCase
{
    public function test_external_federation_listing_images_are_resolved_against_partner_base_urls(): void
    {
        $source = file_get_contents(__DIR__ . '/../../../../app/Http/Controllers/Api/FederationV2Controller.php');

        $this->assertStringContainsString("'image_url' => self::resolveExternalUrl(\$l['image_url'] ?? null, \$partnerBaseUrl)", $source);
        $this->assertStringContainsString("'image_url' => self::resolveExternalUrl(\$l['image_url'] ?? null, \$baseUrl)", $source);
    }

    public function test_translation_context_query_is_partitioned_by_external_partner(): void
    {
        $source = file_get_contents(__DIR__ . '/../../../../app/Http/Controllers/Api/FederationV2Controller.php');

        $this->assertStringContainsString('external_partner_id, direction', $source);
        $this->assertStringContainsString('AND ((? IS NULL AND external_partner_id IS NULL) OR external_partner_id = ?)', $source);
        $this->assertStringContainsString('$externalPartnerId, $externalPartnerId', $source);
    }
}

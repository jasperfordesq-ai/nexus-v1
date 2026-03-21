<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Feature tests for OpenApiDocController — API documentation endpoints (public).
 */
class OpenApiDocControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ------------------------------------------------------------------
    //  GET /docs (PUBLIC)
    // ------------------------------------------------------------------

    public function test_docs_ui_is_public(): void
    {
        $response = $this->apiGet('/docs');

        $this->assertNotEquals(401, $response->getStatusCode());
    }

    // ------------------------------------------------------------------
    //  GET /docs/openapi.json (PUBLIC)
    // ------------------------------------------------------------------

    public function test_openapi_json_is_public(): void
    {
        $response = $this->apiGet('/docs/openapi.json');

        $this->assertNotEquals(401, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [200, 404]);
    }

    // ------------------------------------------------------------------
    //  GET /docs/openapi.yaml (PUBLIC)
    // ------------------------------------------------------------------

    public function test_openapi_yaml_is_public(): void
    {
        $response = $this->apiGet('/docs/openapi.yaml');

        $this->assertNotEquals(401, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [200, 404]);
    }
}

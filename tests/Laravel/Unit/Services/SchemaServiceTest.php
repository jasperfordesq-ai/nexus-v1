<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;

/**
 * App\Services\SchemaService was a dead legacy stub — every method logged
 * "Legacy delegation removed" and returned []. It was deliberately deleted in
 * commit aa27af479 ("convert all 93 stub services to native Laravel"), and there
 * is no PHP replacement: schema.org / JSON-LD generation now lives in the React
 * frontend (react-frontend/src/components/seo/SeoHead.tsx). SeoService only
 * handles metadata/redirect persistence (getMetadata/updateMetadata/getRedirects/
 * createRedirect) — it has no organization() schema builder to retarget this test
 * at.
 *
 * The original tests asserted the *structure* of that no-op stub. Resurrecting the
 * class would re-introduce dead code, so instead this test pins the intentional
 * removal: if a SchemaService ever reappears under App\Services, that's a
 * regression worth a deliberate re-review of these expectations.
 */
class SchemaServiceTest extends TestCase
{
    public function testLegacySchemaServiceStubRemainsRemoved(): void
    {
        $this->assertFalse(
            class_exists(\App\Services\SchemaService::class),
            'App\Services\SchemaService was a dead no-op stub deleted in the Laravel migration; '
            . 'schema.org JSON-LD generation now lives in the React frontend (SeoHead). '
            . 'Do not resurrect this class.'
        );
    }
}

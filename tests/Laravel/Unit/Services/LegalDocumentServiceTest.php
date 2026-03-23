<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\LegalDocumentService;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class LegalDocumentServiceTest extends TestCase
{
    public function test_type_constants(): void
    {
        $this->assertSame('terms', LegalDocumentService::TYPE_TERMS);
        $this->assertSame('privacy', LegalDocumentService::TYPE_PRIVACY);
        $this->assertSame('cookies', LegalDocumentService::TYPE_COOKIES);
    }

    public function test_getDocument_returns_null_when_not_found(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('leftJoin')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(null);

        $this->assertNull(LegalDocumentService::getDocument('terms'));
    }

    public function test_getDocument_returns_array_when_found(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('leftJoin')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) ['id' => 1, 'document_type' => 'terms', 'content' => 'Terms...']);

        $result = LegalDocumentService::getDocument('terms');
        $this->assertIsArray($result);
        $this->assertSame(1, $result['id']);
    }

    public function test_getByType_is_alias(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('leftJoin')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(null);

        $this->assertNull(LegalDocumentService::getByType('privacy'));
    }

    public function test_getAllForTenant_returns_array(): void
    {
        DB::shouldReceive('raw')->andReturnUsing(fn ($v) => new \Illuminate\Database\Query\Expression($v));
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('leftJoin')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('orderBy')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([
            (object) ['id' => 1, 'document_type' => 'terms'],
        ]));

        $result = LegalDocumentService::getAllForTenant(2);
        $this->assertCount(1, $result);
    }

    public function test_getVersions_returns_array(): void
    {
        DB::shouldReceive('table')->with('legal_document_versions')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('orderByDesc')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $this->assertSame([], LegalDocumentService::getVersions(1));
    }

    public function test_hasAccepted_true_when_no_active_doc(): void
    {
        DB::shouldReceive('table')->with('legal_documents')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(null);

        $this->assertTrue(LegalDocumentService::hasAccepted(1, 'terms'));
    }

    public function test_hasAccepted_false_when_not_accepted(): void
    {
        DB::shouldReceive('table')->with('legal_documents')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) ['id' => 1, 'current_version_id' => 5]);

        DB::shouldReceive('table')->with('user_legal_acceptances')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('exists')->andReturn(false);

        $this->assertFalse(LegalDocumentService::hasAccepted(1, 'terms'));
    }

    public function test_hasPendingAcceptances_returns_bool(): void
    {
        DB::shouldReceive('select')->andReturn([
            (object) ['acceptance_status' => 'not_accepted'],
        ]);

        $this->assertTrue(LegalDocumentService::hasPendingAcceptances(1));
    }

    public function test_hasPendingAcceptances_all_current(): void
    {
        DB::shouldReceive('select')->andReturn([
            (object) ['acceptance_status' => 'current'],
        ]);

        $this->assertFalse(LegalDocumentService::hasPendingAcceptances(1));
    }

    public function test_publishVersion_returns_false_for_missing_version(): void
    {
        // getVersion returns null
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(null);

        $this->assertFalse(LegalDocumentService::publishVersion(999));
    }

    public function test_deleteVersion_returns_false_for_non_draft(): void
    {
        // getVersion returns a published version
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) [
            'id' => 1, 'is_draft' => 0, 'document_id' => 1,
            'document_type' => 'terms', 'title' => 'ToS', 'tenant_id' => 2,
        ]);

        $this->assertFalse(LegalDocumentService::deleteVersion(1));
    }

    public function test_updateDocument_returns_null_for_empty_updates(): void
    {
        $this->assertNull(LegalDocumentService::updateDocument(1, ['nonexistent_field' => 'value']));
    }

    public function test_compareVersions_returns_null_if_version_missing(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(null);

        $this->assertNull(LegalDocumentService::compareVersions(1, 2));
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for legal document metadata CRUD on AdminEnterpriseController.
 *
 * Covers createLegalDoc / updateLegalDoc / legalDocs (list). These guard the
 * "saved but did not save" class of bug: the settings form must persist title
 * and acceptance flags, the document type is immutable, and duplicate types are
 * rejected cleanly rather than 500ing.
 */
class LegalDocumentCrudTest extends TestCase
{
    use DatabaseTransactions;

    private function clearType(int $tenantId, string $type): void
    {
        DB::table('legal_documents')
            ->where('tenant_id', $tenantId)
            ->where('document_type', $type)
            ->delete();
    }

    private function seedDocument(int $tenantId, int $createdBy, string $type = 'terms'): int
    {
        $this->clearType($tenantId, $type);

        return DB::table('legal_documents')->insertGetId([
            'tenant_id' => $tenantId,
            'document_type' => $type,
            'title' => ucfirst($type),
            'slug' => $type . '-' . uniqid(),
            'requires_acceptance' => 1,
            'acceptance_required_for' => 'registration',
            'notify_on_update' => 0,
            'is_active' => 1,
            'created_by' => $createdBy,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_update_legal_doc_persists_title_and_flags(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $id = $this->seedDocument($this->testTenantId, $admin->id);

        $response = $this->apiPut("/v2/admin/legal-documents/{$id}", [
            'title' => 'Updated Terms Title',
            'requires_acceptance' => false,
            'acceptance_required_for' => 'login',
            'notify_on_update' => true,
            'is_active' => false,
        ]);

        $response->assertStatus(200);

        $row = (array) DB::table('legal_documents')->where('id', $id)->first();
        $this->assertSame('Updated Terms Title', $row['title']);
        $this->assertSame(0, (int) $row['requires_acceptance']);
        $this->assertSame('login', $row['acceptance_required_for']);
        $this->assertSame(1, (int) $row['notify_on_update']);
        $this->assertSame(0, (int) $row['is_active']);
    }

    public function test_update_legal_doc_rejects_type_change_with_422(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $id = $this->seedDocument($this->testTenantId, $admin->id, 'terms');

        $response = $this->apiPut("/v2/admin/legal-documents/{$id}", [
            'title' => 'Still Terms',
            'type' => 'privacy',
        ]);

        $response->assertStatus(422);
        $this->assertSame('terms', DB::table('legal_documents')->where('id', $id)->value('document_type'));
    }

    public function test_update_legal_doc_cross_tenant_returns_404(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $otherTenantId = $this->testTenantId + 99;
        $id = $this->seedDocument($otherTenantId, $admin->id);

        $response = $this->apiPut("/v2/admin/legal-documents/{$id}", [
            'title' => 'Hijack attempt',
        ]);

        $response->assertStatus(404);
        $this->assertNotSame('Hijack attempt', DB::table('legal_documents')->where('id', $id)->value('title'));
    }

    public function test_create_legal_doc_duplicate_type_returns_422(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $this->seedDocument($this->testTenantId, $admin->id, 'cookies');

        $response = $this->apiPost('/v2/admin/legal-documents', [
            'title' => 'Another Cookie Policy',
            'type' => 'cookies',
        ]);

        $response->assertStatus(422);
    }

    public function test_create_legal_doc_rejects_invalid_type_with_422(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/legal-documents', [
            'title' => 'Weird Doc',
            'type' => 'not_a_real_type',
        ]);

        $response->assertStatus(422);
    }

    public function test_admin_list_includes_inactive_documents(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $id = $this->seedDocument($this->testTenantId, $admin->id, 'acceptable_use');
        DB::table('legal_documents')->where('id', $id)->update(['is_active' => 0]);

        $response = $this->apiGet('/v2/admin/legal-documents');
        $response->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($id, $ids, 'Inactive documents must still appear in the admin list.');
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Gdpr;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\Enterprise\GdprService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Laravel\TestCase;

/**
 * Regression coverage for the volunteering GDPR erasure gaps (module audit,
 * round 2): expense receipt files + path columns must be removed, the
 * custom-field-value delete must be entity_type-scoped (no collision damage),
 * and an organisation's public contact email that equals the erased user's
 * personal email must be scrubbed along with their org membership.
 */
class VolunteeringErasureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_account_erasure_cleans_volunteering_pii(): void
    {
        $tenantId = $this->testTenantId;
        TenantContext::setById($tenantId);

        $user = User::factory()->forTenant($tenantId)->create(['email' => 'erase-me-' . uniqid() . '@example.test']);
        $originalEmail = (string) $user->email;
        TenantContext::setById($tenantId);

        // Receipt file on the 'local' disk + expense row pointing at it.
        $receiptPath = "volunteer-expenses/{$tenantId}/receipt-" . uniqid() . '.pdf';
        Storage::disk('local')->put($receiptPath, '%PDF-1.4 fake receipt');
        $this->assertTrue(Storage::disk('local')->exists($receiptPath));

        $orgId = (int) DB::table('vol_organizations')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'name' => 'Erasure Org',
            'status' => 'active',
            'contact_email' => $originalEmail, // owner used their personal email
            'created_at' => now(),
        ]);
        DB::table('vol_expenses')->insert([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'organization_id' => $orgId,
            'expense_type' => 'travel',
            'amount' => 10.00,
            'currency' => 'EUR',
            'description' => 'Bus fare',
            'receipt_path' => $receiptPath,
            'receipt_filename' => 'receipt.pdf',
            'status' => 'pending',
            'submitted_at' => now(),
        ]);
        DB::table('org_members')->insert([
            'tenant_id' => $tenantId,
            'organization_id' => $orgId,
            'org_type' => 'volunteer',
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Custom-field values: one attached to the user's application (must be
        // deleted), and one on an OPPORTUNITY sharing the same entity_id (must
        // survive — the pre-fix delete matched on entity_id alone).
        $appId = (int) DB::table('vol_applications')->insertGetId([
            'tenant_id' => $tenantId,
            'opportunity_id' => 1,
            'user_id' => $user->id,
            'status' => 'pending',
            'created_at' => now(),
        ]);
        $fieldId = (int) DB::table('vol_custom_fields')->insertGetId([
            'tenant_id' => $tenantId,
            'field_key' => 'k',
            'field_label' => 'K',
            'field_type' => 'text',
            'applies_to' => 'application',
            'is_active' => 1,
            'created_at' => now(),
        ]);
        DB::table('vol_custom_field_values')->insert([
            'tenant_id' => $tenantId,
            'custom_field_id' => $fieldId,
            'entity_type' => 'application',
            'entity_id' => $appId,
            'field_value' => 'my answer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        // Collision: an opportunity custom-field value whose entity_id == $appId.
        DB::table('vol_custom_field_values')->insert([
            'tenant_id' => $tenantId,
            'custom_field_id' => $fieldId,
            'entity_type' => 'opportunity',
            'entity_id' => $appId,
            'field_value' => 'unrelated opportunity data',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new GdprService($tenantId))->executeAccountDeletion($user->id);

        // Receipt file removed and path columns cleared.
        $this->assertFalse(Storage::disk('local')->exists($receiptPath));
        $expense = DB::table('vol_expenses')->where('user_id', $user->id)->where('tenant_id', $tenantId)->first();
        $this->assertNotNull($expense);
        $this->assertNull($expense->receipt_path);
        $this->assertNull($expense->receipt_filename);

        // Org contact email scrubbed; org membership deleted.
        $this->assertNull(DB::table('vol_organizations')->where('id', $orgId)->value('contact_email'));
        $this->assertSame(0, DB::table('org_members')->where('user_id', $user->id)->where('tenant_id', $tenantId)->count());

        // Application custom-field value deleted; the colliding opportunity one survives.
        $this->assertSame(0, DB::table('vol_custom_field_values')->where('entity_type', 'application')->where('entity_id', $appId)->where('tenant_id', $tenantId)->count());
        $this->assertSame(1, DB::table('vol_custom_field_values')->where('entity_type', 'opportunity')->where('entity_id', $appId)->where('tenant_id', $tenantId)->count());
    }
}

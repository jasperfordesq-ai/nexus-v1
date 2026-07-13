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
use Tests\Laravel\TestCase;

class RefreshTokenSessionErasureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_erasure_deletes_only_the_users_tenant_bound_refresh_sessions(): void
    {
        $tenantId = $this->testTenantId;
        $otherTenantId = (int) DB::table('tenants')
            ->where('id', '!=', $tenantId)
            ->orderBy('id')
            ->value('id');
        if ($otherTenantId <= 0) {
            $otherTenantId = (int) DB::table('tenants')->insertGetId([
                'name' => 'Refresh erasure isolation tenant',
                'slug' => 'refresh-erasure-' . uniqid(),
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        TenantContext::setById($tenantId);
        $erasedUser = User::factory()->forTenant($tenantId)->create();
        $otherTenantUser = User::factory()->forTenant($otherTenantId)->create();
        TenantContext::setById($tenantId);

        $now = now();
        DB::table('refresh_token_sessions')->insert([
            [
                'tenant_id' => $tenantId,
                'user_id' => $erasedUser->id,
                'family_hash' => hash('sha256', 'erased-family-' . $erasedUser->id),
                'jti_hash' => hash('sha256', 'erased-jti-' . $erasedUser->id),
                'issued_at' => $now,
                'expires_at' => $now->copy()->addDay(),
                'family_expires_at' => $now->copy()->addDays(30),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $otherTenantId,
                'user_id' => $otherTenantUser->id,
                'family_hash' => hash('sha256', 'other-family-' . $otherTenantUser->id),
                'jti_hash' => hash('sha256', 'other-jti-' . $otherTenantUser->id),
                'issued_at' => $now,
                'expires_at' => $now->copy()->addDay(),
                'family_expires_at' => $now->copy()->addDays(30),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        try {
            $service = new class($tenantId) extends GdprService {
                public function generateDataExport(int $userId, int $requestId = null): string
                {
                    return '';
                }
            };
            $service->executeAccountDeletion($erasedUser->id);

            $this->assertDatabaseMissing('refresh_token_sessions', [
                'tenant_id' => $tenantId,
                'user_id' => $erasedUser->id,
            ]);
            $this->assertDatabaseHas('refresh_token_sessions', [
                'tenant_id' => $otherTenantId,
                'user_id' => $otherTenantUser->id,
            ]);
        } finally {
            foreach (glob(storage_path("exports/nexus_data_export_{$erasedUser->id}_*.zip")) ?: [] as $export) {
                @unlink($export);
            }
            TenantContext::reset();
        }
    }
}

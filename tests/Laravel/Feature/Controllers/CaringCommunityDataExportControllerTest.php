<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Controllers;

use App\Core\TenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

class CaringCommunityDataExportControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function setCaringCommunityFeature(bool $enabled): void
    {
        $tenant = DB::table('tenants')->where('id', $this->testTenantId)->first();
        $features = [];
        if ($tenant && ! empty($tenant->features)) {
            $decoded = is_string($tenant->features) ? json_decode($tenant->features, true) : $tenant->features;
            $features = is_array($decoded) ? $decoded : [];
        }

        $features['caring_community'] = $enabled;
        DB::table('tenants')
            ->where('id', $this->testTenantId)
            ->update(['features' => json_encode($features)]);
        TenantContext::setById($this->testTenantId);
    }

    public function test_member_data_export_profile_uses_allowlist_for_sensitive_user_columns(): void
    {
        $this->setCaringCommunityFeature(true);

        $user = User::factory()->forTenant($this->testTenantId)->create([
            'email' => 'care.export@example.test',
        ]);

        $sensitiveValues = [
            'verification_token' => 'verify-secret',
            'reset_token' => 'reset-secret',
            'reset_token_expiry' => '2026-05-01 10:00:00',
            'is_admin' => true,
            'is_super_admin' => true,
            'is_tenant_super_admin' => true,
            'safeguarding_status' => 'restricted',
            'safeguarding_notes' => 'internal-only note',
        ];

        $updates = [];
        foreach ($sensitiveValues as $column => $value) {
            if (Schema::hasColumn('users', $column)) {
                $updates[$column] = $value;
            }
        }
        if ($updates !== []) {
            DB::table('users')->where('id', $user->id)->update($updates);
        }

        Sanctum::actingAs($user);

        $response = $this->apiGet('/v2/caring-community/me/data-export');

        $response->assertStatus(200);
        $payload = json_decode($response->streamedContent(), true);

        $this->assertIsArray($payload);
        $profile = $payload['profile'] ?? $payload['data']['profile'] ?? null;
        $this->assertIsArray($profile);
        $this->assertSame($user->id, $profile['id'] ?? null);

        foreach (array_keys($sensitiveValues) as $column) {
            $this->assertArrayNotHasKey($column, $profile);
        }
        $this->assertArrayNotHasKey('password', $profile);
        $this->assertArrayNotHasKey('remember_token', $profile);
    }
}

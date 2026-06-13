<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for the tenant header-logo upload/reset endpoints
 * (AdminConfigController::uploadHeaderLogo[Dark] / removeHeaderLogo[Dark]).
 *
 * Covers auth gating, validation, SVG persistence into tenants.configuration,
 * and reset (unset) behaviour.
 */
class HeaderLogoUploadTest extends TestCase
{
    use DatabaseTransactions;

    private function tenantConfig(): array
    {
        $row = DB::selectOne('SELECT configuration FROM tenants WHERE id = ?', [$this->testTenantId]);
        return ($row && !empty($row->configuration)) ? (json_decode($row->configuration, true) ?: []) : [];
    }

    public function test_upload_requires_authentication(): void
    {
        $response = $this->apiPost('/v2/admin/settings/header-logo');
        $response->assertStatus(401);
    }

    public function test_upload_forbidden_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/settings/header-logo');
        $response->assertStatus(403);
    }

    public function test_upload_rejects_missing_file(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/settings/header-logo');
        $response->assertStatus(400);
    }

    public function test_upload_rejects_non_image(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $file = UploadedFile::fake()->createWithContent('notes.txt', 'just text');
        $response = $this->post('/api/v2/admin/settings/header-logo', ['logo' => $file], $this->withTenantHeader());

        $response->assertStatus(422);
    }

    public function test_upload_rejects_oversize_image(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        // 3 MB exceeds the 2 MB cap — rejected before any file is written.
        $file = UploadedFile::fake()->create('big.png', 3072, 'image/png');
        $response = $this->post('/api/v2/admin/settings/header-logo', ['logo' => $file], $this->withTenantHeader());

        $response->assertStatus(422);
    }

    public function test_svg_upload_persists_into_configuration(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>';
        $file = UploadedFile::fake()->createWithContent('logo.svg', $svg);

        $response = $this->post('/api/v2/admin/settings/header-logo', ['logo' => $file], $this->withTenantHeader());

        $response->assertStatus(200);
        $url = $response->json('data.url');
        $this->assertIsString($url);
        $this->assertStringEndsWith('.svg', $url);

        // The URL is now persisted in tenants.configuration under logo_url.
        $this->assertSame($url, $this->tenantConfig()['logo_url'] ?? null);

        // Best-effort cleanup of the file written to the uploads dir.
        $abs = base_path('httpdocs' . $url);
        if (is_file($abs)) {
            @unlink($abs);
        }
    }

    public function test_remove_unsets_configuration_key(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        // Seed an existing header logo plus an unrelated key we must preserve.
        $config = $this->tenantConfig();
        $config['logo_url'] = '/uploads/tenants/x/tenant-logos/seed.svg';
        $config['default_language'] = 'en';
        DB::update('UPDATE tenants SET configuration = ? WHERE id = ?', [json_encode($config), $this->testTenantId]);

        $response = $this->apiDelete('/v2/admin/settings/header-logo');
        $response->assertStatus(200);

        $after = $this->tenantConfig();
        $this->assertArrayNotHasKey('logo_url', $after);
        // Sibling keys are untouched.
        $this->assertSame('en', $after['default_language'] ?? null);
    }

    public function test_remove_dark_forbidden_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiDelete('/v2/admin/settings/header-logo-dark');
        $response->assertStatus(403);
    }
}

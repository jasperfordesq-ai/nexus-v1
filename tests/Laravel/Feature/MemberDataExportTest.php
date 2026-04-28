<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\MemberDataExportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;
use ZipArchive;

/**
 * MemberDataExportTest — covers the GDPR/FADP personal-data export flow.
 */
class MemberDataExportTest extends TestCase
{
    use DatabaseTransactions;

    private const PRIMARY_TENANT_ID = 2;
    private int $secondaryTenantId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->secondaryTenantId = (int) DB::table('tenants')->insertGetId([
            'name'       => 'Other Coop',
            'slug'       => 'other-coop-' . uniqid(),
            'is_active'  => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        TenantContext::setById(self::PRIMARY_TENANT_ID);
    }

    private function makeUser(int $tenantId, ?string $email = null, float $balance = 0): int
    {
        return (int) DB::table('users')->insertGetId([
            'tenant_id'  => $tenantId,
            'first_name' => 'Test',
            'last_name'  => 'Member',
            'name'       => 'Test Member',
            'email'      => $email ?? ('export.' . uniqid() . '@example.com'),
            'username'   => 'mde_' . substr(md5((string) microtime(true)), 0, 8),
            'password'   => password_hash('password', PASSWORD_BCRYPT),
            'balance'    => $balance,
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_export_returns_user_profile(): void
    {
        $userId = $this->makeUser(self::PRIMARY_TENANT_ID);
        $svc    = app(MemberDataExportService::class);

        $archive = $svc->buildArchive($userId);

        $this->assertSame('1.0', $archive['format_version']);
        $this->assertArrayHasKey('profile', $archive);
        $this->assertSame($userId, $archive['profile']['id']);
        $this->assertSame('Test Member', $archive['profile']['name']);
        $this->assertArrayHasKey('tenant', $archive);
        $this->assertNotEmpty($archive['tenant']['slug']);
    }

    public function test_export_includes_vol_logs_for_user(): void
    {
        $userId = $this->makeUser(self::PRIMARY_TENANT_ID);

        DB::table('vol_logs')->insert([
            'tenant_id'   => self::PRIMARY_TENANT_ID,
            'user_id'     => $userId,
            'date_logged' => now()->toDateString(),
            'hours'       => 2.5,
            'description' => 'Helped a neighbour',
            'status'      => 'approved',
            'created_at'  => now(),
        ]);

        $archive = app(MemberDataExportService::class)->buildArchive($userId);

        $this->assertNotEmpty($archive['vol_logs']['given']);
        $this->assertSame(2.5, $archive['vol_logs']['given'][0]['hours']);
    }

    public function test_export_excludes_other_users_data(): void
    {
        $me     = $this->makeUser(self::PRIMARY_TENANT_ID);
        $other  = $this->makeUser(self::PRIMARY_TENANT_ID);

        DB::table('vol_logs')->insert([
            'tenant_id'   => self::PRIMARY_TENANT_ID,
            'user_id'     => $other,
            'date_logged' => now()->toDateString(),
            'hours'       => 9.0,
            'status'      => 'approved',
            'created_at'  => now(),
        ]);

        $archive = app(MemberDataExportService::class)->buildArchive($me);

        $this->assertEmpty($archive['vol_logs']['given']);
    }

    public function test_export_is_tenant_scoped(): void
    {
        $userInOther = $this->makeUser($this->secondaryTenantId, 'cross.' . uniqid() . '@example.com');

        // Log activity in the SECONDARY tenant for that user
        DB::table('vol_logs')->insert([
            'tenant_id'   => $this->secondaryTenantId,
            'user_id'     => $userInOther,
            'date_logged' => now()->toDateString(),
            'hours'       => 7.0,
            'status'      => 'approved',
            'created_at'  => now(),
        ]);

        // Export from PRIMARY tenant — must NOT see the user or their logs
        TenantContext::setById(self::PRIMARY_TENANT_ID);
        $archive = app(MemberDataExportService::class)->buildArchive($userInOther);

        // Profile is empty (user belongs to other tenant)
        $this->assertSame([], $archive['profile']);
        // vol_logs given empty (we filter by primary tenant)
        $this->assertEmpty($archive['vol_logs']['given']);
    }

    public function test_export_request_is_logged(): void
    {
        $userId = $this->makeUser(self::PRIMARY_TENANT_ID);
        $svc    = app(MemberDataExportService::class);

        $before = DB::table('member_data_exports')->count();
        $id     = $svc->recordExportRequest($userId, 'json');
        $after  = DB::table('member_data_exports')->count();

        $this->assertGreaterThan(0, $id);
        $this->assertSame($before + 1, $after);

        $row = DB::table('member_data_exports')->where('id', $id)->first();
        $this->assertSame((int) self::PRIMARY_TENANT_ID, (int) $row->tenant_id);
        $this->assertSame($userId, (int) $row->user_id);
        $this->assertSame('json', $row->format);
    }

    public function test_zip_export_contains_json_and_readme(): void
    {
        $userId = $this->makeUser(self::PRIMARY_TENANT_ID);
        $svc    = app(MemberDataExportService::class);

        $built = $svc->buildZipArchive($userId);
        $this->assertStringEndsWith('.zip', $built['filename']);
        $this->assertNotEmpty($built['content']);

        $tmp = tempnam(sys_get_temp_dir(), 'mdetest_');
        file_put_contents($tmp, $built['content']);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($tmp) === true, 'ZIP failed to open');
        $this->assertNotFalse($zip->locateName('data.json'), 'Missing data.json inside ZIP');
        $this->assertNotFalse($zip->locateName('README.md'), 'Missing README.md inside ZIP');

        $jsonContents = $zip->getFromName('data.json');
        $this->assertIsString($jsonContents);
        $decoded = json_decode((string) $jsonContents, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('profile', $decoded);

        $zip->close();
        @unlink($tmp);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        // No Sanctum::actingAs — must be rejected
        $response = $this->apiPost('/v2/me/data-export', ['format' => 'json']);
        $response->assertStatus(401);
    }

    public function test_rate_limit_blocks_after_5_per_day(): void
    {
        $userId = $this->makeUser(self::PRIMARY_TENANT_ID);
        $svc    = app(MemberDataExportService::class);

        // Pre-seed 5 export rows in the last 24h
        for ($i = 0; $i < 5; $i++) {
            DB::table('member_data_exports')->insert([
                'tenant_id'    => self::PRIMARY_TENANT_ID,
                'user_id'      => $userId,
                'format'       => 'json',
                'requested_at' => now()->subHours($i),
                'completed_at' => now()->subHours($i),
                'created_at'   => now()->subHours($i),
                'updated_at'   => now()->subHours($i),
            ]);
        }

        $this->assertSame(5, $svc->countRecentRequests($userId));

        // Now hit the endpoint — must 429
        $userModel = User::find($userId);
        $this->assertNotNull($userModel);
        Sanctum::actingAs($userModel, ['*']);

        $response = $this->apiPost('/v2/me/data-export', ['format' => 'json']);
        $response->assertStatus(429);
    }
}

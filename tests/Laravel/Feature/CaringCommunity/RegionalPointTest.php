<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\CaringCommunity;

use App\Core\TenantContext;
use App\Services\CaringCommunity\CaringRegionalPointService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class RegionalPointTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setCaringCommunityFeature(true);
        TenantContext::setById(self::TENANT_ID);
    }

    private function setCaringCommunityFeature(bool $enabled): void
    {
        $tenant = DB::table('tenants')->where('id', self::TENANT_ID)->first();
        $features = [];
        if ($tenant && !empty($tenant->features)) {
            $decoded = is_string($tenant->features) ? json_decode($tenant->features, true) : $tenant->features;
            $features = is_array($decoded) ? $decoded : [];
        }
        $features['caring_community'] = $enabled;
        DB::table('tenants')
            ->where('id', self::TENANT_ID)
            ->update(['features' => json_encode($features)]);
    }

    private function makeUser(string $email, int $tenantId = self::TENANT_ID): int
    {
        return (int) DB::table('users')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => 'Regional Points User',
            'first_name' => 'Regional',
            'last_name' => 'User',
            'email' => $email,
            'username' => 'rp_' . substr(md5($email . microtime(true)), 0, 8),
            'password' => password_hash('password', PASSWORD_BCRYPT),
            'balance' => 0,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_regional_points_are_disabled_by_default(): void
    {
        $userId = $this->makeUser('rp-disabled-' . uniqid() . '@example.test');
        $service = app(CaringRegionalPointService::class);

        $this->assertFalse($service->isEnabled(self::TENANT_ID));

        $this->expectExceptionMessage('Regional points are not enabled for this community.');
        $service->memberSummary($userId);
    }

    public function test_admin_can_enable_without_changing_timebank_balance(): void
    {
        $userId = $this->makeUser('rp-enable-' . uniqid() . '@example.test');
        DB::table('users')->where('id', $userId)->update(['balance' => 12]);

        $service = app(CaringRegionalPointService::class);
        $config = $service->updateConfig(self::TENANT_ID, [
            'enabled' => true,
            'label' => 'Agoris Points',
            'symbol' => 'AGP',
            'auto_issue_enabled' => true,
            'points_per_approved_hour' => 5,
            'member_transfers_enabled' => true,
        ]);

        $this->assertTrue($config['enabled']);
        $this->assertSame('Agoris Points', $config['label']);
        $this->assertSame('AGP', $config['symbol']);
        $this->assertTrue($service->isEnabled(self::TENANT_ID));

        $summary = $service->memberSummary($userId);
        $this->assertEqualsWithDelta(0.0, $summary['account']['balance'], 0.001);

        $timebankBalance = (float) DB::table('users')->where('id', $userId)->value('balance');
        $this->assertEqualsWithDelta(12.0, $timebankBalance, 0.001);
    }

    public function test_issue_and_adjust_write_isolated_ledger(): void
    {
        $userId = $this->makeUser('rp-issue-' . uniqid() . '@example.test');
        $adminId = $this->makeUser('rp-admin-' . uniqid() . '@example.test');
        $service = app(CaringRegionalPointService::class);
        $service->updateConfig(self::TENANT_ID, ['enabled' => true]);

        $issued = $service->issue($userId, 25.5, 'Pilot-region launch bonus', $adminId);
        $this->assertEqualsWithDelta(25.5, $issued['balance'], 0.001);

        $adjusted = $service->adjust($userId, -5.25, 'Correction', $adminId);
        $this->assertEqualsWithDelta(20.25, $adjusted['balance'], 0.001);

        $history = $service->memberHistory($userId);
        $this->assertCount(2, $history);
        $this->assertSame('debit', $history[0]['direction']);
        $this->assertSame('credit', $history[1]['direction']);

        $this->assertDatabaseHas('caring_regional_point_accounts', [
            'tenant_id' => self::TENANT_ID,
            'user_id' => $userId,
        ]);
        $this->assertDatabaseHas('caring_regional_point_transactions', [
            'tenant_id' => self::TENANT_ID,
            'user_id' => $userId,
            'type' => 'admin_issue',
            'direction' => 'credit',
        ]);
    }

    public function test_issue_rejects_users_from_other_tenants(): void
    {
        $otherTenantUser = $this->makeUser('rp-other-' . uniqid() . '@example.test', 999);
        $adminId = $this->makeUser('rp-admin2-' . uniqid() . '@example.test');
        $service = app(CaringRegionalPointService::class);
        $service->updateConfig(self::TENANT_ID, ['enabled' => true]);

        $this->expectExceptionMessage('User not found');
        $service->issue($otherTenantUser, 10, 'Wrong tenant', $adminId);
    }
}

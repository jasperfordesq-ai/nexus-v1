<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Verein;

use App\Core\TenantContext;
use App\Services\Verein\VereinFederationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class FederationConsentTest extends TestCase
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
        DB::table('tenants')->where('id', self::TENANT_ID)->update(['features' => json_encode($features)]);
    }

    private function makeUser(string $email): int
    {
        return (int) DB::table('users')->insertGetId([
            'tenant_id' => self::TENANT_ID,
            'first_name' => 'T',
            'last_name' => 'U',
            'email' => $email,
            'username' => 'u_' . substr(md5($email . microtime(true)), 0, 8),
            'password' => password_hash('x', PASSWORD_BCRYPT),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeVerein(string $name): int
    {
        $owner = $this->makeUser('owner.' . uniqid() . '@example.com');
        return (int) DB::table('vol_organizations')->insertGetId([
            'tenant_id' => self::TENANT_ID,
            'user_id' => $owner,
            'name' => $name,
            'slug' => strtolower($name) . '-' . uniqid(),
            'org_type' => 'club',
            'status' => 'active',
            'created_at' => now(),
        ]);
    }

    public function test_opt_in_then_opt_out(): void
    {
        $svc = app(VereinFederationService::class);
        $orgId = $this->makeVerein('Verein A');

        $consent = $svc->setConsent($orgId, 'both', '8001');
        $this->assertTrue($consent['is_active']);
        $this->assertSame('both', $consent['sharing_scope']);
        $this->assertSame('8001', $consent['municipality_code']);

        $consent = $svc->setConsent($orgId, 'none', '8001');
        $this->assertFalse($consent['is_active']);
        $this->assertSame('none', $consent['sharing_scope']);
    }

    public function test_only_consenting_vereine_in_same_municipality_appear_in_network(): void
    {
        $svc = app(VereinFederationService::class);

        $a = $this->makeVerein('Verein A');
        $b = $this->makeVerein('Verein B');
        $c = $this->makeVerein('Verein C');
        $d = $this->makeVerein('Verein D'); // different muni

        $svc->setConsent($a, 'both', '8001');
        $svc->setConsent($b, 'events', '8001');
        $svc->setConsent($c, 'none', '8001'); // opted out
        $svc->setConsent($d, 'both', '8002');

        $network = $svc->getNetworkVereine($a);
        $ids = array_column($network, 'organization_id');

        $this->assertContains($b, $ids);
        $this->assertNotContains($c, $ids);
        $this->assertNotContains($d, $ids);
        $this->assertNotContains($a, $ids);
    }

    public function test_invalid_scope_throws(): void
    {
        $svc = app(VereinFederationService::class);
        $orgId = $this->makeVerein('Verein X');

        $this->expectException(\InvalidArgumentException::class);
        $svc->setConsent($orgId, 'bogus', '8001');
    }
}

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

class EventShareTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCaring();
        TenantContext::setById(self::TENANT_ID);
    }

    private function enableCaring(): void
    {
        $tenant = DB::table('tenants')->where('id', self::TENANT_ID)->first();
        $features = is_array(json_decode((string) ($tenant->features ?? '[]'), true)) ? json_decode((string) $tenant->features, true) : [];
        $features['caring_community'] = true;
        DB::table('tenants')->where('id', self::TENANT_ID)->update(['features' => json_encode($features)]);
    }

    private function makeUser(): int
    {
        return (int) DB::table('users')->insertGetId([
            'tenant_id' => self::TENANT_ID,
            'first_name' => 'X',
            'last_name' => 'Y',
            'email' => 'u' . uniqid() . '@x.test',
            'username' => 'u_' . substr(md5(uniqid()), 0, 8),
            'password' => password_hash('x', PASSWORD_BCRYPT),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeVerein(string $name): int
    {
        return (int) DB::table('vol_organizations')->insertGetId([
            'tenant_id' => self::TENANT_ID,
            'user_id' => $this->makeUser(),
            'name' => $name,
            'slug' => strtolower($name) . '-' . uniqid(),
            'org_type' => 'club',
            'status' => 'active',
            'created_at' => now(),
        ]);
    }

    private function makeEvent(int $userId): int
    {
        return (int) DB::table('events')->insertGetId([
            'tenant_id' => self::TENANT_ID,
            'user_id' => $userId,
            'title' => 'Test Event ' . uniqid(),
            'description' => 'desc',
            'start_time' => now()->addDays(7),
            'status' => 'active',
            'created_at' => now(),
        ]);
    }

    public function test_share_event_with_target_then_target_sees_it(): void
    {
        $svc = app(VereinFederationService::class);

        $sourceOwner = $this->makeUser();
        $source = (int) DB::table('vol_organizations')->insertGetId([
            'tenant_id' => self::TENANT_ID,
            'user_id' => $sourceOwner,
            'name' => 'Verein Source',
            'slug' => 'src-' . uniqid(),
            'org_type' => 'club',
            'status' => 'active',
            'created_at' => now(),
        ]);
        $target = $this->makeVerein('Verein Target');

        $svc->setConsent($source, 'both', '8001');
        $svc->setConsent($target, 'events', '8001');

        $eventId = $this->makeEvent($sourceOwner);

        $result = $svc->shareEvent($eventId, [$target], $source);
        $this->assertSame(1, $result['shared']);
        $this->assertSame(0, $result['skipped']);

        $incoming = $svc->getSharedEvents($target, 'incoming');
        $this->assertCount(1, $incoming);
        $this->assertSame($eventId, $incoming[0]['event_id']);
    }

    public function test_withdraw_share_removes_from_target_view(): void
    {
        $svc = app(VereinFederationService::class);

        $sourceOwner = $this->makeUser();
        $source = (int) DB::table('vol_organizations')->insertGetId([
            'tenant_id' => self::TENANT_ID,
            'user_id' => $sourceOwner,
            'name' => 'Verein S2',
            'slug' => 's2-' . uniqid(),
            'org_type' => 'club',
            'status' => 'active',
            'created_at' => now(),
        ]);
        $target = $this->makeVerein('Verein T2');

        $svc->setConsent($source, 'both', '8001');
        $svc->setConsent($target, 'both', '8001');

        $eventId = $this->makeEvent($sourceOwner);
        $svc->shareEvent($eventId, [$target], $source);

        $shares = $svc->getSharedEvents($source, 'outgoing');
        $this->assertCount(1, $shares);
        $shareId = $shares[0]['id'];

        $ok = $svc->withdrawEventShare($shareId, $source);
        $this->assertTrue($ok);

        $after = $svc->getSharedEvents($target, 'incoming');
        $this->assertCount(0, $after);
    }
}

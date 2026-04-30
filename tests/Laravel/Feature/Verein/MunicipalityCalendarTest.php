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

class MunicipalityCalendarTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant = DB::table('tenants')->where('id', self::TENANT_ID)->first();
        $features = is_array(json_decode((string) ($tenant->features ?? '[]'), true)) ? json_decode((string) $tenant->features, true) : [];
        $features['caring_community'] = true;
        DB::table('tenants')->where('id', self::TENANT_ID)->update(['features' => json_encode($features)]);
        TenantContext::setById(self::TENANT_ID);
    }

    private function makeUser(): int
    {
        return (int) DB::table('users')->insertGetId([
            'tenant_id' => self::TENANT_ID,
            'first_name' => 'C',
            'last_name' => 'U',
            'email' => 'mc' . uniqid() . '@x.test',
            'username' => 'mc_' . substr(md5(uniqid()), 0, 8),
            'password' => password_hash('x', PASSWORD_BCRYPT),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeVerein(int $ownerId, string $name): int
    {
        return (int) DB::table('vol_organizations')->insertGetId([
            'tenant_id' => self::TENANT_ID,
            'user_id' => $ownerId,
            'name' => $name,
            'slug' => strtolower($name) . '-' . uniqid(),
            'org_type' => 'club',
            'status' => 'active',
            'created_at' => now(),
        ]);
    }

    private function makeEvent(int $userId, string $title): int
    {
        return (int) DB::table('events')->insertGetId([
            'tenant_id' => self::TENANT_ID,
            'user_id' => $userId,
            'title' => $title,
            'description' => 'd',
            'start_time' => now()->addDays(3),
            'status' => 'active',
            'created_at' => now(),
        ]);
    }

    public function test_calendar_includes_consenting_vereine_excludes_non_consenting(): void
    {
        $svc = app(VereinFederationService::class);

        $ownerA = $this->makeUser();
        $ownerB = $this->makeUser();
        $ownerC = $this->makeUser();
        $ownerD = $this->makeUser();

        $a = $this->makeVerein($ownerA, 'Calendar A');
        $b = $this->makeVerein($ownerB, 'Calendar B');
        $c = $this->makeVerein($ownerC, 'Calendar C'); // wont opt in
        $d = $this->makeVerein($ownerD, 'Calendar D'); // diff muni

        $svc->setConsent($a, 'both', '8001');
        $svc->setConsent($b, 'events', '8001');
        $svc->setConsent($c, 'none', '8001');
        $svc->setConsent($d, 'both', '8002');

        $eventA = $this->makeEvent($ownerA, 'Event from A');
        $eventB = $this->makeEvent($ownerB, 'Event from B');
        $eventC = $this->makeEvent($ownerC, 'Event from C');
        $eventD = $this->makeEvent($ownerD, 'Event from D');

        $cal = $svc->getMunicipalityCalendar(self::TENANT_ID, '8001', 'month');
        $this->assertSame('8001', $cal['municipality_code']);

        $allTitles = [];
        foreach ($cal['buckets'] as $bucket) {
            foreach ($bucket as $ev) {
                $allTitles[] = $ev['title'];
            }
        }

        $this->assertContains('Event from A', $allTitles);
        $this->assertContains('Event from B', $allTitles);
        $this->assertNotContains('Event from C', $allTitles);
        $this->assertNotContains('Event from D', $allTitles);
    }
}

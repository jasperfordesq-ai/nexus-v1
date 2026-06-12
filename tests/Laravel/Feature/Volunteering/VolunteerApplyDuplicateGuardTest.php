<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Volunteering;

use App\Core\TenantContext;
use App\Services\VolunteerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * VolunteerService::apply() was an unguarded check-then-insert —
 * vol_applications has no unique key on (opportunity_id, user_id) because
 * declined rows may legitimately repeat, so two concurrent identical
 * submissions both passed the exists() check and double-inserted. The fix
 * serialises the window under the same Cache::lock pattern logHours uses.
 */
class VolunteerApplyDuplicateGuardTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
    }

    private function makeUser(): int
    {
        return (int) DB::table('users')->insertGetId([
            'tenant_id' => self::TENANT_ID,
            'first_name' => 'Apply',
            'last_name' => 'Tester',
            'email' => 'apl.' . uniqid('', true) . '@example.com',
            'username' => 'apl_' . substr(md5(uniqid('', true)), 0, 8),
            'password' => password_hash('password', PASSWORD_BCRYPT),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeOpportunity(int $creatorId): int
    {
        return (int) DB::table('vol_opportunities')->insertGetId([
            'tenant_id' => self::TENANT_ID,
            'title' => 'Apply race test opportunity',
            'description' => 'x',
            'is_active' => 1,
            'created_by' => $creatorId,
            'created_at' => now(),
        ]);
    }

    public function test_concurrent_identical_apply_is_rejected_while_lock_is_held(): void
    {
        $creator = $this->makeUser();
        $user = $this->makeUser();
        $oppId = $this->makeOpportunity($creator);

        // Simulate the concurrent first request still inside the window by
        // pre-acquiring the dedup lock the fix introduces.
        $lock = Cache::lock(sprintf('vol_apply_dedupe:%d:%d:%d', self::TENANT_ID, $oppId, $user), 10);
        $this->assertTrue($lock->get());

        try {
            VolunteerService::apply($oppId, $user);
            $this->fail('Expected the concurrent duplicate apply to be rejected (409).');
        } catch (\RuntimeException $e) {
            $this->assertSame(409, $e->getCode());
        } finally {
            $lock->release();
        }

        $this->assertSame(
            0,
            (int) DB::table('vol_applications')->where('opportunity_id', $oppId)->where('user_id', $user)->count(),
            'No application row may be created while a concurrent identical submission holds the lock.'
        );
    }

    public function test_apply_succeeds_and_releases_the_lock(): void
    {
        $creator = $this->makeUser();
        $user = $this->makeUser();
        $oppId = $this->makeOpportunity($creator);

        $application = VolunteerService::apply($oppId, $user);
        $this->assertSame('pending', $application->status);

        // The lock must be free again after a successful apply.
        $lock = Cache::lock(sprintf('vol_apply_dedupe:%d:%d:%d', self::TENANT_ID, $oppId, $user), 10);
        $this->assertTrue($lock->get(), 'The dedup lock must be released after apply() returns.');
        $lock->release();

        // And the sequential duplicate is still rejected by the exists() check.
        try {
            VolunteerService::apply($oppId, $user);
            $this->fail('Expected the sequential duplicate apply to be rejected (409).');
        } catch (\RuntimeException $e) {
            $this->assertSame(409, $e->getCode());
        }

        $this->assertSame(
            1,
            (int) DB::table('vol_applications')->where('opportunity_id', $oppId)->where('user_id', $user)->count()
        );
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\CaringCommunity;

use App\Core\TenantContext;
use App\Services\CaringCommunity\CivicDigestService;
use App\Services\EmailService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * AG90 — CivicDigestDispatch command tests.
 *
 * Verifies the dispatch command:
 *  - skips silently when there are zero items for a user
 *  - sends an email when a user is opted-in for the cadence and has items
 *  - records last_sent_at for idempotency (re-runs are no-ops)
 *  - skips users whose cadence does not match the requested cadence
 */
class CivicDigestDispatchTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2; // hour-timebank

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

    private function makeUser(string $cadence): int
    {
        $email = 'cd.' . uniqid('', true) . '@example.test';
        $userId = (int) DB::table('users')->insertGetId([
            'tenant_id' => self::TENANT_ID,
            'first_name' => 'Civic',
            'last_name' => 'Recipient',
            'email' => $email,
            'username' => 'cd_' . substr(md5($email . microtime(true)), 0, 8),
            'password' => password_hash('password', PASSWORD_BCRYPT),
            'status' => 'active',
            'email_verified_at' => now(),
            'preferred_language' => 'en',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Set explicit cadence pref
        app(CivicDigestService::class)->setUserPrefs(self::TENANT_ID, $userId, ['cadence' => $cadence]);

        return $userId;
    }

    public function test_skips_users_with_zero_items(): void
    {
        $userId = $this->makeUser('daily');

        // Stub digest service to return empty
        $stub = Mockery::mock(CivicDigestService::class . '[digestForMember]', [])->makePartial();
        $stub->shouldReceive('digestForMember')->andReturn([]);
        $this->app->instance(CivicDigestService::class, $stub);

        // EmailService MUST NOT be called for empty digests
        $emailMock = Mockery::mock(EmailService::class);
        $emailMock->shouldNotReceive('send');
        $this->app->instance(EmailService::class, $emailMock);

        $exitCode = $this->artisan('caring:civic-digest-dispatch', [
            '--cadence' => 'daily',
            '--tenant' => self::TENANT_ID,
        ])->run();

        $this->assertSame(0, $exitCode);

        // last_sent_at should NOT have been set
        $lastSent = app(CivicDigestService::class)->getLastSentAt(self::TENANT_ID, $userId);
        $this->assertNull($lastSent);
    }

    public function test_sends_email_and_marks_sent_when_items_exist(): void
    {
        $userId = $this->makeUser('daily');

        // Stub digest service to return one item
        $stub = Mockery::mock(CivicDigestService::class . '[digestForMember]', [])->makePartial();
        $stub->shouldReceive('digestForMember')->andReturn([
            [
                'id' => 'announcement:1',
                'source' => 'announcement',
                'title' => 'Test announcement',
                'summary' => 'Hello world',
                'occurred_at' => now()->toDateTimeString(),
                'sub_region_id' => null,
                'audience_match_score' => 7,
                'link_path' => '/caring-community/projects/1',
            ],
        ]);
        $this->app->instance(CivicDigestService::class, $stub);

        $emailMock = Mockery::mock(EmailService::class);
        $emailMock->shouldReceive('send')
            ->atLeast()->once()
            ->withArgs(function ($to, $subject, $html) {
                return is_string($to) && str_contains((string) $to, '@')
                    && is_string($subject) && $subject !== ''
                    && is_string($html) && $html !== '';
            })
            ->andReturn(true);
        $this->app->instance(EmailService::class, $emailMock);

        $exitCode = $this->artisan('caring:civic-digest-dispatch', [
            '--cadence' => 'daily',
            '--tenant' => self::TENANT_ID,
        ])->run();

        $this->assertSame(0, $exitCode);

        // last_sent_at must be set
        $lastSent = $stub->getLastSentAt(self::TENANT_ID, $userId);
        $this->assertNotNull($lastSent);
        $this->assertGreaterThanOrEqual(time() - 60, $lastSent);
    }

    public function test_skips_user_whose_cadence_does_not_match(): void
    {
        // Weekly user, daily run — should not be picked up
        $userId = $this->makeUser('weekly');

        $stub = Mockery::mock(CivicDigestService::class . '[digestForMember]', [])->makePartial();
        $stub->shouldReceive('digestForMember')->andReturnUsing(function () {
            $this->fail('digestForMember should not be called for cadence mismatch');
        });
        $this->app->instance(CivicDigestService::class, $stub);

        $emailMock = Mockery::mock(EmailService::class);
        $emailMock->shouldNotReceive('send');
        $this->app->instance(EmailService::class, $emailMock);

        // Force tenant default to 'off' so the weekly user isn't covered by tenant default
        $stub->setTenantCadence(self::TENANT_ID, 'off');

        $exitCode = $this->artisan('caring:civic-digest-dispatch', [
            '--cadence' => 'daily',
            '--tenant' => self::TENANT_ID,
        ])->run();

        $this->assertSame(0, $exitCode);

        $lastSent = $stub->getLastSentAt(self::TENANT_ID, $userId);
        $this->assertNull($lastSent);
    }
}

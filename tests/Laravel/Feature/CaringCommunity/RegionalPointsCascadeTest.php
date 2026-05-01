<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\CaringCommunity;

use App\Core\TenantContext;
use App\Events\VolLogStatusChanged;
use App\Services\CaringCommunity\CaringRegionalPointService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Laravel\TestCase;

/**
 * E1 — Verify that regional points auto-issued on a vol_log approval are
 * automatically reversed when the vol_log later transitions away from
 * `approved`. Without this listener members could "print" points by getting
 * hours approved and then having them reverted.
 */
class RegionalPointsCascadeTest extends TestCase
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

    private function makeUser(string $email): int
    {
        return (int) DB::table('users')->insertGetId([
            'tenant_id' => self::TENANT_ID,
            'name' => 'Cascade Test User',
            'first_name' => 'Cascade',
            'last_name' => 'User',
            'email' => $email,
            'username' => 'cascade_' . substr(md5($email . microtime(true)), 0, 8),
            'password' => password_hash('password', PASSWORD_BCRYPT),
            'balance' => 0,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeVolLog(int $userId, string $status, float $hours = 2.0): int
    {
        return (int) DB::table('vol_logs')->insertGetId([
            'tenant_id' => self::TENANT_ID,
            'user_id' => $userId,
            'organization_id' => null,
            'opportunity_id' => null,
            'date_logged' => now()->toDateString(),
            'hours' => $hours,
            'description' => 'Cascade test',
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function configureAutoIssue(int $points = 4): CaringRegionalPointService
    {
        $service = app(CaringRegionalPointService::class);
        $service->updateConfig(self::TENANT_ID, [
            'enabled' => true,
            'auto_issue_enabled' => true,
            'points_per_approved_hour' => $points,
        ]);

        return $service;
    }

    public function test_listener_reverses_points_when_vol_log_status_changes_from_approved(): void
    {
        $userId = $this->makeUser('cascade-revert-' . uniqid() . '@example.test');
        $adminId = $this->makeUser('cascade-admin-' . uniqid() . '@example.test');
        $logId = $this->makeVolLog($userId, 'approved', 2.5);
        $service = $this->configureAutoIssue(4);

        $award = $service->awardForApprovedHours(self::TENANT_ID, $userId, $logId, 2.5, $adminId);
        $this->assertNotNull($award);
        $this->assertEqualsWithDelta(10.0, $award['points'], 0.001);

        $summaryBefore = $service->memberSummary($userId);
        $this->assertEqualsWithDelta(10.0, $summaryBefore['account']['balance'], 0.001);

        // Simulate the status change: approved -> pending. Dispatching the
        // event triggers the listener which reverses the auto-issue.
        VolLogStatusChanged::dispatch(self::TENANT_ID, $logId, 'approved', 'pending');

        $summaryAfter = $service->memberSummary($userId);
        $this->assertEqualsWithDelta(0.0, $summaryAfter['account']['balance'], 0.001);

        $this->assertDatabaseHas('caring_regional_point_transactions', [
            'tenant_id' => self::TENANT_ID,
            'user_id' => $userId,
            'reference_type' => 'vol_log_reversal',
            'reference_id' => $logId,
            'direction' => 'debit',
            'type' => 'reversal',
        ]);
    }

    public function test_listener_handles_approved_to_declined_transition(): void
    {
        $userId = $this->makeUser('cascade-decl-' . uniqid() . '@example.test');
        $adminId = $this->makeUser('cascade-decl-admin-' . uniqid() . '@example.test');
        $logId = $this->makeVolLog($userId, 'approved', 1.0);
        $service = $this->configureAutoIssue(4);

        $service->awardForApprovedHours(self::TENANT_ID, $userId, $logId, 1.0, $adminId);
        $this->assertEqualsWithDelta(4.0, $service->memberSummary($userId)['account']['balance'], 0.001);

        VolLogStatusChanged::dispatch(self::TENANT_ID, $logId, 'approved', 'declined');

        $this->assertEqualsWithDelta(0.0, $service->memberSummary($userId)['account']['balance'], 0.001);

        // The original credit row is still present; only a new debit is added.
        $this->assertSame(
            1,
            DB::table('caring_regional_point_transactions')
                ->where('tenant_id', self::TENANT_ID)
                ->where('reference_type', 'vol_log')
                ->where('reference_id', $logId)
                ->where('type', 'earned_for_hours')
                ->count(),
            'Original earned_for_hours credit must still exist'
        );

        $this->assertSame(
            1,
            DB::table('caring_regional_point_transactions')
                ->where('tenant_id', self::TENANT_ID)
                ->where('reference_type', 'vol_log_reversal')
                ->where('reference_id', $logId)
                ->where('type', 'reversal')
                ->count(),
            'Exactly one reversal transaction must be created'
        );
    }

    public function test_re_approval_after_revert_does_not_double_credit(): void
    {
        $userId = $this->makeUser('cascade-reapprove-' . uniqid() . '@example.test');
        $adminId = $this->makeUser('cascade-reapprove-admin-' . uniqid() . '@example.test');
        $logId = $this->makeVolLog($userId, 'approved', 2.0);
        $service = $this->configureAutoIssue(5);

        // Initial approval: credit 10
        $service->awardForApprovedHours(self::TENANT_ID, $userId, $logId, 2.0, $adminId);
        $this->assertEqualsWithDelta(10.0, $service->memberSummary($userId)['account']['balance'], 0.001);

        // Revert to declined: debit 10
        VolLogStatusChanged::dispatch(self::TENANT_ID, $logId, 'approved', 'declined');
        $this->assertEqualsWithDelta(0.0, $service->memberSummary($userId)['account']['balance'], 0.001);

        // Re-approve: awardForApprovedHours is idempotent on (vol_log_id) so
        // the existing earned_for_hours row blocks a duplicate credit. The
        // member balance must remain at 0 because the original credit was
        // already reversed and we don't re-issue.
        $second = $service->awardForApprovedHours(self::TENANT_ID, $userId, $logId, 2.0, $adminId);
        $this->assertNotNull($second);
        $this->assertTrue($second['already_awarded'] ?? false, 'Existing award must be returned, no duplicate credit');

        $this->assertSame(
            1,
            DB::table('caring_regional_point_transactions')
                ->where('tenant_id', self::TENANT_ID)
                ->where('reference_type', 'vol_log')
                ->where('reference_id', $logId)
                ->where('type', 'earned_for_hours')
                ->count(),
            'No duplicate earned_for_hours row must be created on re-approval'
        );
    }

    public function test_revert_on_log_with_no_prior_award_is_noop(): void
    {
        $userId = $this->makeUser('cascade-noop-' . uniqid() . '@example.test');
        $logId = $this->makeVolLog($userId, 'approved', 1.0);
        $service = $this->configureAutoIssue(4);

        // No award call — log was approved but auto-issue was not configured
        // at the time. The cascade revert listener should be a graceful no-op.
        $this->assertEqualsWithDelta(0.0, $service->memberSummary($userId)['account']['balance'], 0.001);

        VolLogStatusChanged::dispatch(self::TENANT_ID, $logId, 'approved', 'pending');

        $this->assertEqualsWithDelta(0.0, $service->memberSummary($userId)['account']['balance'], 0.001);
        $this->assertSame(
            0,
            DB::table('caring_regional_point_transactions')
                ->where('tenant_id', self::TENANT_ID)
                ->where('reference_id', $logId)
                ->count(),
            'No transactions of any kind should exist for this vol_log'
        );
    }

    public function test_event_with_non_approved_previous_status_does_nothing(): void
    {
        $userId = $this->makeUser('cascade-nonprev-' . uniqid() . '@example.test');
        $adminId = $this->makeUser('cascade-nonprev-admin-' . uniqid() . '@example.test');
        $logId = $this->makeVolLog($userId, 'approved', 2.0);
        $service = $this->configureAutoIssue(4);

        $service->awardForApprovedHours(self::TENANT_ID, $userId, $logId, 2.0, $adminId);
        $this->assertEqualsWithDelta(8.0, $service->memberSummary($userId)['account']['balance'], 0.001);

        // pending -> approved is the credit path; the listener must NOT touch
        // points in this direction (the credit is handled by the caller).
        VolLogStatusChanged::dispatch(self::TENANT_ID, $logId, 'pending', 'approved');

        $this->assertEqualsWithDelta(8.0, $service->memberSummary($userId)['account']['balance'], 0.001);
        $this->assertSame(
            0,
            DB::table('caring_regional_point_transactions')
                ->where('tenant_id', self::TENANT_ID)
                ->where('reference_type', 'vol_log_reversal')
                ->count(),
            'No reversal must be created for non-approved -> X transitions'
        );
    }

    public function test_event_listener_is_registered(): void
    {
        // Sanity-check the listener wiring so the cascade test failures are
        // easier to diagnose — if this fails the others will fail too but the
        // root cause is missing event registration.
        $hasListener = collect(Event::getListeners(VolLogStatusChanged::class))->isNotEmpty();
        $this->assertTrue($hasListener, 'VolLogStatusChanged must have at least one listener registered');
    }
}

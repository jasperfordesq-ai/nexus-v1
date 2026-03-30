<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Integration;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\EmailService;
use App\Services\GamificationEmailService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Integration test: verify that sendWeeklyDigests() does not send duplicate
 * emails when multiple tenants have users with XP activity.
 */
class EmailDeliveryIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    private GamificationEmailService $gamificationEmailService;

    /** @var list<array{to: string, subject: string}> */
    private array $sentLog = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->gamificationEmailService = new GamificationEmailService();
        $this->sentLog = [];
    }

    // =========================================================================
    // sendWeeklyDigests() — no duplicate emails
    // =========================================================================

    public function test_sendWeeklyDigests_does_not_send_duplicate_emails(): void
    {
        // --- Arrange: 2 tenants, 3 users each, each user with XP activity ---

        $tenantA = $this->testTenantId; // 2 (hour-timebank), already seeded by TestCase
        $tenantB = 999;                 // seeded by TestCase as "Other Test Tenant"

        $usersA = $this->seedUsersWithXp($tenantA, 3);
        $usersB = $this->seedUsersWithXp($tenantB, 3);

        // Stub EmailService so we capture every send() call without touching real mail
        $fakeEmailService = $this->createMock(EmailService::class);
        $fakeEmailService
            ->method('send')
            ->willReturnCallback(function (string $to, string $subject) {
                $this->sentLog[] = ['to' => $to, 'subject' => $subject];
                return true;
            });

        $this->app->instance(EmailService::class, $fakeEmailService);

        // --- Act ---
        $result = $this->gamificationEmailService->sendWeeklyDigests();

        // --- Assert: total sent equals number of unique users, not multiplied by tenant count ---
        $allEmails = array_merge(
            array_column($usersA, 'email'),
            array_column($usersB, 'email'),
        );
        $uniqueUserCount = count($allEmails);

        $this->assertLessThanOrEqual($uniqueUserCount, $result['sent'],
            'Sent count should not exceed the number of unique users with XP activity');

        // Assert no email address appears more than once in the sent log
        $sentAddresses = array_column($this->sentLog, 'to');
        $duplicates = array_diff_assoc($sentAddresses, array_unique($sentAddresses));
        $this->assertEmpty($duplicates,
            'No user email should appear more than once in the sent log. Duplicates: '
            . implode(', ', $duplicates));
    }

    public function test_sendWeeklyDigests_returns_zero_sent_when_no_xp_activity(): void
    {
        // Ensure there is no XP activity for any user in the test tenants
        DB::table('user_xp_log')
            ->whereIn('tenant_id', [$this->testTenantId, 999])
            ->where('created_at', '>=', now()->subWeek())
            ->delete();

        $result = $this->gamificationEmailService->sendWeeklyDigests();

        $this->assertEquals(0, $result['sent'],
            'No emails should be sent when there is no XP activity');
    }

    public function test_sendWeeklyDigests_skips_users_with_opt_out_preference(): void
    {
        $tenantId = $this->testTenantId;
        $users = $this->seedUsersWithXp($tenantId, 2);

        // Opt out the first user by setting their notification preference
        DB::table('users')
            ->where('id', $users[0]['id'])
            ->update(['notification_preferences' => json_encode([
                'email_gamification_digest' => false,
            ])]);

        $fakeEmailService = $this->createMock(EmailService::class);
        $fakeEmailService
            ->method('send')
            ->willReturnCallback(function (string $to, string $subject) {
                $this->sentLog[] = ['to' => $to, 'subject' => $subject];
                return true;
            });

        $this->app->instance(EmailService::class, $fakeEmailService);

        $result = $this->gamificationEmailService->sendWeeklyDigests();

        // The opted-out user should be in the skipped count, not sent
        $sentAddresses = array_column($this->sentLog, 'to');
        $this->assertNotContains($users[0]['email'], $sentAddresses,
            'Opted-out user should not receive a digest email');
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Create users for a tenant and seed XP activity in the past 7 days.
     *
     * @return list<array{id: int, email: string}>
     */
    private function seedUsersWithXp(int $tenantId, int $count): array
    {
        $created = [];

        for ($i = 0; $i < $count; $i++) {
            $user = User::factory()->forTenant($tenantId)->create([
                'status' => 'active',
                'xp'     => 100 + ($i * 50),
                'level'  => 2,
            ]);

            // Seed XP log entry within the past 7 days
            DB::table('user_xp_log')->insert([
                'user_id'    => $user->id,
                'tenant_id'  => $tenantId,
                'xp_amount'  => 25 + ($i * 10),
                'reason'     => 'test_activity',
                'created_at' => now()->subDays(rand(1, 6)),
            ]);

            $created[] = ['id' => $user->id, 'email' => $user->email];
        }

        return $created;
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Console;

use App\Services\EmailDispatchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * Tests for SafeguardingReviewFlagsCommand (artisan safeguarding:review-flags).
 *
 * Tenant ID 99701 is exclusively reserved for this test class.
 * Each test seeds only what it needs and relies on DatabaseTransactions
 * to roll back after each test.
 */
class SafeguardingReviewFlagsCommandTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99701;

    // -----------------------------------------------------------------------
    // Shared fixture IDs — seeded in setUp, reused across tests
    // -----------------------------------------------------------------------
    private int $memberId;
    private int $optionId;
    private int $adminId;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Mail::fake();

        // Stub EmailDispatchService so the real custom Mailer (SMTP/SendGrid)
        // is never called in tests. The command only stamps timestamps when
        // sendWithOptions() returns true.
        $this->instance(EmailDispatchService::class, new class extends EmailDispatchService {
            public function send(string $to, string $subject, string $body, array $options = []): bool
            {
                return true;
            }
        });

        // Insert the test tenant
        DB::table('tenants')->insertOrIgnore([
            'id'        => self::TENANT_ID,
            'name'      => 'Safeguarding Review Test Tenant',
            'slug'      => 'sg-review-test-99701',
            'is_active' => 1,
            'features'  => json_encode(['caring_community' => true]),
            'created_at' => now(),
        ]);

        // Insert a member user for this tenant
        $this->memberId = DB::table('users')->insertGetId([
            'tenant_id'          => self::TENANT_ID,
            'name'               => 'Test Member',
            'first_name'         => 'Test',
            'last_name'          => 'Member',
            'email'              => 'member-sg-99701@test.local',
            'password'           => bcrypt('secret'),
            'role'               => 'member',
            'status'             => 'active',
            'preferred_language' => 'en',
            'created_at'         => now(),
        ]);

        // Insert an admin user for this tenant (used in escalation tests)
        $this->adminId = DB::table('users')->insertGetId([
            'tenant_id'          => self::TENANT_ID,
            'name'               => 'Test Admin',
            'first_name'         => 'Test',
            'last_name'          => 'Admin',
            'email'              => 'admin-sg-99701@test.local',
            'password'           => bcrypt('secret'),
            'role'               => 'admin',
            'status'             => 'active',
            'preferred_language' => 'en',
            'created_at'         => now(),
        ]);

        // Insert a safeguarding option for this tenant (not 'none_apply')
        $this->optionId = DB::table('tenant_safeguarding_options')->insertGetId([
            'tenant_id'   => self::TENANT_ID,
            'option_key'  => 'works_with_children',
            'option_type' => 'checkbox',
            'label'       => 'Works with children',
            'sort_order'  => 1,
            'is_active'   => 1,
            'is_required' => 0,
            'created_at'  => now(),
        ]);

        \App\Core\TenantContext::setById(self::TENANT_ID);
    }

    // -----------------------------------------------------------------------
    // Helper: insert a user_safeguarding_preferences row
    // -----------------------------------------------------------------------
    private function insertPreference(array $overrides = []): int
    {
        $defaults = [
            'tenant_id'              => self::TENANT_ID,
            'user_id'                => $this->memberId,
            'option_id'              => $this->optionId,
            'selected_value'         => '1',
            'consent_given_at'       => now()->subDays(400),
            'review_reminder_sent_at' => null,
            'review_confirmed_at'    => null,
            'review_escalated_at'    => null,
            'revoked_at'             => null,
            'created_at'             => now()->subDays(400),
        ];

        return DB::table('user_safeguarding_preferences')->insertGetId(
            array_merge($defaults, $overrides)
        );
    }

    // -----------------------------------------------------------------------
    // 1. Command exits successfully with no work to do
    // -----------------------------------------------------------------------
    public function test_command_exits_zero_with_no_due_preferences(): void
    {
        $this->artisan('safeguarding:review-flags')
            ->assertExitCode(0);
    }

    // -----------------------------------------------------------------------
    // 2. Dry-run: reports count but writes nothing
    // -----------------------------------------------------------------------
    public function test_dry_run_reports_count_without_updating_db(): void
    {
        $prefId = $this->insertPreference([
            'consent_given_at' => now()->subDays(400),
        ]);

        $this->artisan('safeguarding:review-flags', ['--dry-run' => true])
            ->expectsOutputToContain('DRY RUN')
            ->assertExitCode(0);

        // Timestamp must NOT have been written
        $pref = DB::table('user_safeguarding_preferences')->find($prefId);
        $this->assertNull($pref->review_reminder_sent_at);
    }

    // -----------------------------------------------------------------------
    // 3. Reminder path: stamps review_reminder_sent_at on due preference
    // -----------------------------------------------------------------------
    public function test_reminder_stamps_review_reminder_sent_at(): void
    {
        $prefId = $this->insertPreference([
            'consent_given_at' => now()->subDays(400),
        ]);

        $this->artisan('safeguarding:review-flags')
            ->assertExitCode(0);

        $pref = DB::table('user_safeguarding_preferences')->find($prefId);
        $this->assertNotNull(
            $pref->review_reminder_sent_at,
            'review_reminder_sent_at should be set after command runs'
        );
    }

    // -----------------------------------------------------------------------
    // 4. Idempotency: running twice does not re-send reminder
    // -----------------------------------------------------------------------
    public function test_reminder_is_not_resent_if_already_stamped(): void
    {
        $sentAt = now()->subDays(1);
        $prefId = $this->insertPreference([
            'consent_given_at'        => now()->subDays(400),
            'review_reminder_sent_at' => $sentAt,
        ]);

        $this->artisan('safeguarding:review-flags')
            ->assertExitCode(0);

        $pref = DB::table('user_safeguarding_preferences')->find($prefId);
        $this->assertEquals(
            $sentAt->toDateTimeString(),
            (new \DateTime($pref->review_reminder_sent_at))->format('Y-m-d H:i:s'),
            'Timestamp should not be overwritten on a second run'
        );
    }

    // -----------------------------------------------------------------------
    // 5. Revoked preferences are not picked up for reminders
    // -----------------------------------------------------------------------
    public function test_revoked_preference_is_skipped_for_reminder(): void
    {
        $prefId = $this->insertPreference([
            'consent_given_at' => now()->subDays(400),
            'revoked_at'       => now()->subDays(10),
        ]);

        $this->artisan('safeguarding:review-flags')
            ->assertExitCode(0);

        $pref = DB::table('user_safeguarding_preferences')->find($prefId);
        $this->assertNull($pref->review_reminder_sent_at);
    }

    // -----------------------------------------------------------------------
    // 6. 'none_apply' option key is excluded from reminders
    // -----------------------------------------------------------------------
    public function test_none_apply_option_is_excluded_from_reminders(): void
    {
        $noneOptionId = DB::table('tenant_safeguarding_options')->insertGetId([
            'tenant_id'   => self::TENANT_ID,
            'option_key'  => 'none_apply',
            'option_type' => 'checkbox',
            'label'       => 'None apply',
            'sort_order'  => 99,
            'is_active'   => 1,
            'is_required' => 0,
            'created_at'  => now(),
        ]);

        $prefId = $this->insertPreference([
            'option_id'        => $noneOptionId,
            'consent_given_at' => now()->subDays(400),
        ]);

        $this->artisan('safeguarding:review-flags')
            ->assertExitCode(0);

        $pref = DB::table('user_safeguarding_preferences')->find($prefId);
        $this->assertNull($pref->review_reminder_sent_at);
    }

    // -----------------------------------------------------------------------
    // 7. Preference newer than 365 days is not due yet
    // -----------------------------------------------------------------------
    public function test_preference_under_365_days_old_is_not_reminded(): void
    {
        $prefId = $this->insertPreference([
            'consent_given_at' => now()->subDays(300), // not yet 365 days
        ]);

        $this->artisan('safeguarding:review-flags')
            ->assertExitCode(0);

        $pref = DB::table('user_safeguarding_preferences')->find($prefId);
        $this->assertNull($pref->review_reminder_sent_at);
    }

    // -----------------------------------------------------------------------
    // 8. Inactive users are not reminded
    // -----------------------------------------------------------------------
    public function test_inactive_user_preference_is_skipped_for_reminder(): void
    {
        // Make the member inactive temporarily (we'll use a separate user)
        $inactiveUserId = DB::table('users')->insertGetId([
            'tenant_id'          => self::TENANT_ID,
            'name'               => 'Inactive Member',
            'first_name'         => 'Inactive',
            'last_name'          => 'Member',
            'email'              => 'inactive-sg-99701@test.local',
            'password'           => bcrypt('secret'),
            'role'               => 'member',
            'status'             => 'inactive',
            'preferred_language' => 'en',
            'created_at'         => now(),
        ]);

        $optionId2 = DB::table('tenant_safeguarding_options')->insertGetId([
            'tenant_id'   => self::TENANT_ID,
            'option_key'  => 'works_with_children_inactive_test',
            'option_type' => 'checkbox',
            'label'       => 'Works with children (inactive test)',
            'sort_order'  => 2,
            'is_active'   => 1,
            'is_required' => 0,
            'created_at'  => now(),
        ]);

        $prefId = DB::table('user_safeguarding_preferences')->insertGetId([
            'tenant_id'              => self::TENANT_ID,
            'user_id'                => $inactiveUserId,
            'option_id'              => $optionId2,
            'selected_value'         => '1',
            'consent_given_at'       => now()->subDays(400),
            'review_reminder_sent_at' => null,
            'revoked_at'             => null,
            'created_at'             => now()->subDays(400),
        ]);

        $this->artisan('safeguarding:review-flags')
            ->assertExitCode(0);

        $pref = DB::table('user_safeguarding_preferences')->find($prefId);
        $this->assertNull($pref->review_reminder_sent_at);
    }

    // -----------------------------------------------------------------------
    // 9. Escalation path: stamps review_escalated_at after 30-day no-response
    // -----------------------------------------------------------------------
    public function test_escalation_stamps_review_escalated_at(): void
    {
        $prefId = $this->insertPreference([
            'consent_given_at'        => now()->subDays(400),
            'review_reminder_sent_at' => now()->subDays(35),  // >30 days ago
            'review_confirmed_at'     => null,
            'review_escalated_at'     => null,
        ]);

        $this->artisan('safeguarding:review-flags')
            ->assertExitCode(0);

        $pref = DB::table('user_safeguarding_preferences')->find($prefId);
        $this->assertNotNull(
            $pref->review_escalated_at,
            'review_escalated_at should be set after 30-day no-response'
        );
    }

    // -----------------------------------------------------------------------
    // 10. Confirmed preference is not escalated
    // -----------------------------------------------------------------------
    public function test_confirmed_preference_is_not_escalated(): void
    {
        $prefId = $this->insertPreference([
            'consent_given_at'        => now()->subDays(400),
            'review_reminder_sent_at' => now()->subDays(35),
            'review_confirmed_at'     => now()->subDays(5), // member confirmed
            'review_escalated_at'     => null,
        ]);

        $this->artisan('safeguarding:review-flags')
            ->assertExitCode(0);

        $pref = DB::table('user_safeguarding_preferences')->find($prefId);
        $this->assertNull($pref->review_escalated_at);
    }

    // -----------------------------------------------------------------------
    // 11. Escalation idempotency: already-escalated row not re-escalated
    // -----------------------------------------------------------------------
    public function test_already_escalated_preference_is_not_re_escalated(): void
    {
        $escalatedAt = now()->subDays(2);
        $prefId = $this->insertPreference([
            'consent_given_at'        => now()->subDays(400),
            'review_reminder_sent_at' => now()->subDays(35),
            'review_confirmed_at'     => null,
            'review_escalated_at'     => $escalatedAt,
        ]);

        $this->artisan('safeguarding:review-flags')
            ->assertExitCode(0);

        $pref = DB::table('user_safeguarding_preferences')->find($prefId);
        $this->assertEquals(
            $escalatedAt->toDateTimeString(),
            (new \DateTime($pref->review_escalated_at))->format('Y-m-d H:i:s'),
            'review_escalated_at should not be overwritten on a second run'
        );
    }

    // -----------------------------------------------------------------------
    // 12. Escalation dry-run: no DB write
    // -----------------------------------------------------------------------
    public function test_escalation_dry_run_writes_nothing(): void
    {
        $prefId = $this->insertPreference([
            'consent_given_at'        => now()->subDays(400),
            'review_reminder_sent_at' => now()->subDays(35),
            'review_confirmed_at'     => null,
            'review_escalated_at'     => null,
        ]);

        $this->artisan('safeguarding:review-flags', ['--dry-run' => true])
            ->expectsOutputToContain('DRY RUN')
            ->assertExitCode(0);

        $pref = DB::table('user_safeguarding_preferences')->find($prefId);
        $this->assertNull($pref->review_escalated_at);
    }

    // -----------------------------------------------------------------------
    // 13. Reminder within 30-day window is not yet escalated
    // -----------------------------------------------------------------------
    public function test_reminder_sent_recently_is_not_yet_escalated(): void
    {
        $prefId = $this->insertPreference([
            'consent_given_at'        => now()->subDays(400),
            'review_reminder_sent_at' => now()->subDays(10), // only 10 days ago
            'review_confirmed_at'     => null,
            'review_escalated_at'     => null,
        ]);

        $this->artisan('safeguarding:review-flags')
            ->assertExitCode(0);

        $pref = DB::table('user_safeguarding_preferences')->find($prefId);
        $this->assertNull($pref->review_escalated_at);
    }
}

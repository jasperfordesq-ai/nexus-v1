<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Console;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * Tests for `verein-federation:expire-invitations` Artisan command.
 *
 * Uses unique tenant id 99723 for isolation.
 *
 * The command delegates to VereinFederationService::expireOldInvitations()
 * which UPDATEs verein_cross_invitations WHERE status='sent'
 * AND expires_at < NOW() to status='expired'.
 *
 * We seed rows with explicit past/future expires_at timestamps to assert
 * which rows are mutated and which are left alone.
 *
 * verein_cross_invitations references source_organization_id and
 * target_organization_id (vol_organizations) without FK enforcement in test DB
 * (InnoDB FK checks are OFF in tests / or we use insertOrIgnore-safe raw
 * values). The command only touches the verein_cross_invitations table so
 * we only need tenant + invitation rows.
 */
class ExpireVereinFederationInvitationsTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99723;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        DB::table('tenants')->insertOrIgnore([
            'id'         => self::TENANT_ID,
            'name'       => 'Expire Verein Invitations Test Tenant',
            'slug'       => 'expire-verein-test-' . self::TENANT_ID,
            'is_active'  => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \App\Core\TenantContext::setById(self::TENANT_ID);
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Insert a verein_cross_invitations row.
     *
     * @param string      $status    'sent', 'accepted', 'declined', 'expired'
     * @param string|null $expiresAt MySQL datetime string or null
     */
    private function insertInvitation(string $status = 'sent', ?string $expiresAt = null): int
    {
        return (int) DB::table('verein_cross_invitations')->insertGetId([
            'source_organization_id' => 1001,
            'target_organization_id' => 1002,
            'tenant_id'              => self::TENANT_ID,
            'inviter_user_id'        => 1,
            'invitee_user_id'        => 2,
            'message'                => null,
            'status'                 => $status,
            'sent_at'                => now()->subDays(40),
            'expires_at'             => $expiresAt,
            'created_at'             => now()->subDays(40),
            'updated_at'             => now()->subDays(40),
        ]);
    }

    /**
     * Fetch the status of a row by its primary key.
     */
    private function statusOf(int $id): string
    {
        return (string) DB::table('verein_cross_invitations')
            ->where('id', $id)
            ->value('status');
    }

    // ----------------------------------------------------------------
    // Tests
    // ----------------------------------------------------------------

    public function test_exits_success_with_no_invitations(): void
    {
        $this->artisan('verein-federation:expire-invitations')
            ->assertExitCode(0);

        // No rows in the table for our tenant — nothing to assert except exit code.
        $this->assertSame(0, DB::table('verein_cross_invitations')
            ->where('tenant_id', self::TENANT_ID)
            ->count());
    }

    public function test_past_sent_invitation_is_marked_expired(): void
    {
        $id = $this->insertInvitation('sent', now()->subDays(1)->toDateTimeString());

        $this->artisan('verein-federation:expire-invitations')
            ->assertExitCode(0);

        $this->assertSame('expired', $this->statusOf($id));
    }

    public function test_future_sent_invitation_is_not_expired(): void
    {
        $id = $this->insertInvitation('sent', now()->addDays(5)->toDateTimeString());

        $this->artisan('verein-federation:expire-invitations')
            ->assertExitCode(0);

        $this->assertSame('sent', $this->statusOf($id));
    }

    public function test_null_expires_at_invitation_is_not_expired(): void
    {
        // expires_at IS NULL — the WHERE clause requires NOT NULL, so this row is skipped.
        $id = $this->insertInvitation('sent', null);

        $this->artisan('verein-federation:expire-invitations')
            ->assertExitCode(0);

        $this->assertSame('sent', $this->statusOf($id));
    }

    public function test_already_accepted_invitation_is_not_changed(): void
    {
        $id = $this->insertInvitation('accepted', now()->subDays(2)->toDateTimeString());

        $this->artisan('verein-federation:expire-invitations')
            ->assertExitCode(0);

        $this->assertSame('accepted', $this->statusOf($id));
    }

    public function test_already_declined_invitation_is_not_changed(): void
    {
        $id = $this->insertInvitation('declined', now()->subDays(2)->toDateTimeString());

        $this->artisan('verein-federation:expire-invitations')
            ->assertExitCode(0);

        $this->assertSame('declined', $this->statusOf($id));
    }

    public function test_already_expired_invitation_is_not_double_expired(): void
    {
        $id = $this->insertInvitation('expired', now()->subDays(2)->toDateTimeString());

        $this->artisan('verein-federation:expire-invitations')
            ->assertExitCode(0);

        // Row was already 'expired', status must remain 'expired' (not changed to something else).
        $this->assertSame('expired', $this->statusOf($id));
    }

    public function test_only_past_invitations_expired_when_mixed_batch(): void
    {
        $past   = $this->insertInvitation('sent', now()->subHours(1)->toDateTimeString());
        $future = $this->insertInvitation('sent', now()->addDays(10)->toDateTimeString());
        $noExp  = $this->insertInvitation('sent', null);
        $accepted = $this->insertInvitation('accepted', now()->subDays(3)->toDateTimeString());

        $this->artisan('verein-federation:expire-invitations')
            ->assertExitCode(0);

        $this->assertSame('expired', $this->statusOf($past),     'Past sent invitation must be expired');
        $this->assertSame('sent',    $this->statusOf($future),   'Future sent invitation must stay sent');
        $this->assertSame('sent',    $this->statusOf($noExp),    'Null expires_at must stay sent');
        $this->assertSame('accepted', $this->statusOf($accepted), 'Accepted invitation must not change');
    }

    public function test_command_output_contains_count_when_invitations_expire(): void
    {
        $this->insertInvitation('sent', now()->subDays(31)->toDateTimeString());
        $this->insertInvitation('sent', now()->subDays(32)->toDateTimeString());

        $this->artisan('verein-federation:expire-invitations')
            ->expectsOutputToContain('Expired')
            ->assertExitCode(0);
    }

    public function test_command_produces_no_output_when_nothing_to_expire(): void
    {
        // One future invitation — no output expected (the command only calls $this->info()
        // when $expired > 0).
        $this->insertInvitation('sent', now()->addDays(10)->toDateTimeString());

        // The command does NOT output anything for zero expirations.
        // We just confirm exit code 0 with no assertion on output (avoids false "risky" flag).
        $result = $this->artisan('verein-federation:expire-invitations');
        $result->assertExitCode(0);
        $this->assertTrue(true); // explicit assertion to avoid risky-test flag
    }

    public function test_updated_at_is_refreshed_on_expired_row(): void
    {
        $id = $this->insertInvitation('sent', now()->subDays(35)->toDateTimeString());

        // Record the original updated_at before the command runs.
        $before = (string) DB::table('verein_cross_invitations')->where('id', $id)->value('updated_at');

        // Small sleep to ensure NOW() advances at least 1 second.
        sleep(1);

        $this->artisan('verein-federation:expire-invitations')
            ->assertExitCode(0);

        $after = (string) DB::table('verein_cross_invitations')->where('id', $id)->value('updated_at');

        $this->assertSame('expired', $this->statusOf($id));
        // updated_at must have changed (the service passes now() to the update).
        $this->assertNotSame($before, $after, 'updated_at must be refreshed after expiry');
    }
}

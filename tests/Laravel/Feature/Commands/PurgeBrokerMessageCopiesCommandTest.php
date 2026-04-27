<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Commands;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Feature tests for PurgeBrokerMessageCopiesCommand.
 *
 * Command signature: safeguarding:purge-message-copies
 *   --days=90           (default retention for tenants with no broker_config)
 *   --flagged-days=365  (retention for flagged copies, uniform across tenants)
 *
 * Tests verify: reviewed rows deleted after retention, safety floor of 7 days,
 * flagged-copy separate retention, fallback for tenants without config, and
 * that unreviewed copies are never deleted.
 */
class PurgeBrokerMessageCopiesCommandTest extends TestCase
{
    use DatabaseTransactions;

    /** Command signature as defined in the Command class. */
    private const COMMAND = 'safeguarding:purge-message-copies';

    // ----------------------------------------------------------------
    // HELPERS
    // ----------------------------------------------------------------

    /**
     * Insert a real messages row (required by broker_message_copies FK) and a
     * broker_message_copies row.  Returns the broker_message_copies.id.
     *
     * @param array<string, mixed> $overrides  Extra columns for broker_message_copies
     */
    private function insertCopy(array $overrides = []): int
    {
        $tenantId = $overrides['tenant_id'] ?? $this->testTenantId;
        $senderId = $overrides['sender_id'] ?? 1;
        $receiverId = $overrides['receiver_id'] ?? 2;

        $msgId = DB::table('messages')->insertGetId([
            'tenant_id'   => $tenantId,
            'sender_id'   => $senderId,
            'receiver_id' => $receiverId,
            'body'        => 'Test message for broker review',
            'is_read'     => false,
            'created_at'  => $overrides['sent_at'] ?? now()->subDays(100),
        ]);

        return DB::table('broker_message_copies')->insertGetId(array_merge([
            'tenant_id'           => $tenantId,
            'original_message_id' => $msgId,
            'sender_id'           => $senderId,
            'receiver_id'         => $receiverId,
            'message_body'        => 'Test message',
            'sent_at'             => now()->subDays(100),  // old by default
            'copy_reason'         => 'first_contact',
            'flagged'             => false,
            'reviewed_by'         => 1,
            'reviewed_at'         => now()->subDays(100),
            'conversation_key'    => 'key-' . uniqid(),
            'created_at'          => now()->subDays(100),
        ], $overrides));
    }

    /**
     * Set broker_config.retention_days for the given tenant in tenant_settings.
     */
    private function setTenantRetentionDays(int $tenantId, int $days): void
    {
        $existing = DB::table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->where('setting_key', 'broker_config')
            ->first();

        if ($existing) {
            $config = json_decode($existing->setting_value ?? '{}', true) ?? [];
            $config['retention_days'] = $days;
            DB::table('tenant_settings')
                ->where('tenant_id', $tenantId)
                ->where('setting_key', 'broker_config')
                ->update(['setting_value' => json_encode($config), 'updated_at' => now()]);
        } else {
            DB::table('tenant_settings')->insert([
                'tenant_id'     => $tenantId,
                'setting_key'   => 'broker_config',
                'setting_value' => json_encode(['retention_days' => $days]),
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }
    }

    // ----------------------------------------------------------------
    // HAPPY PATH — reviewed rows older than retention are deleted
    // ----------------------------------------------------------------

    public function test_reviewed_copies_older_than_retention_are_deleted(): void
    {
        $this->setTenantRetentionDays($this->testTenantId, 30);

        // Row sent 60 days ago — beyond 30-day retention
        $oldId = $this->insertCopy(['sent_at' => now()->subDays(60), 'reviewed_at' => now()->subDays(60)]);

        // Row sent 10 days ago — within retention
        $recentId = $this->insertCopy(['sent_at' => now()->subDays(10), 'reviewed_at' => now()->subDays(10)]);

        $exitCode = Artisan::call(self::COMMAND, ['--days' => 30]);

        $this->assertEquals(0, $exitCode);

        $this->assertDatabaseMissing('broker_message_copies', ['id' => $oldId]);
        $this->assertDatabaseHas('broker_message_copies', ['id' => $recentId]);
    }

    // ----------------------------------------------------------------
    // UNREVIEWED rows must NEVER be deleted
    // ----------------------------------------------------------------

    public function test_unreviewed_copies_are_never_deleted(): void
    {
        $this->setTenantRetentionDays($this->testTenantId, 30);

        // Very old but unreviewed — must survive
        $unreviewedId = $this->insertCopy([
            'sent_at'     => now()->subDays(200),
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        Artisan::call(self::COMMAND, ['--days' => 30]);

        $this->assertDatabaseHas('broker_message_copies', ['id' => $unreviewedId],
            'Unreviewed broker copies must never be purged regardless of age'
        );
    }

    // ----------------------------------------------------------------
    // SAFETY FLOOR — 7 days minimum regardless of config
    // ----------------------------------------------------------------

    public function test_safety_floor_prevents_deletion_within_7_days(): void
    {
        // Tenant sets retention_days to 0 (intentionally or accidentally)
        $this->setTenantRetentionDays($this->testTenantId, 0);

        // Row sent 3 days ago — within the 7-day safety floor
        $recentId = $this->insertCopy([
            'sent_at'     => now()->subDays(3),
            'reviewed_at' => now()->subDays(3),
        ]);

        // Row sent 10 days ago — past the safety floor, should be deleted
        $oldId = $this->insertCopy([
            'sent_at'     => now()->subDays(10),
            'reviewed_at' => now()->subDays(10),
        ]);

        Artisan::call(self::COMMAND, ['--days' => 0]);

        // 3-day row is protected by 7-day floor
        $this->assertDatabaseHas('broker_message_copies', ['id' => $recentId],
            'Safety floor: rows newer than 7 days must never be deleted even if config says 0'
        );

        // 10-day row exceeds the 7-day floor and should be gone
        $this->assertDatabaseMissing('broker_message_copies', ['id' => $oldId]);
    }

    // ----------------------------------------------------------------
    // FLAGGED COPIES — use --flagged-days retention, survive standard purge
    // ----------------------------------------------------------------

    public function test_flagged_copies_survive_standard_retention(): void
    {
        // Standard retention is 30 days; flagged retention is 365
        $this->setTenantRetentionDays($this->testTenantId, 30);

        // Flagged row older than 30 days but newer than 365 — must survive
        $flaggedId = $this->insertCopy([
            'sent_at'      => now()->subDays(60),
            'reviewed_at'  => now()->subDays(60),
            'flagged'      => true,
            'flag_reason'  => 'Harassment',
            'flag_severity' => 'warning',
        ]);

        Artisan::call(self::COMMAND, ['--days' => 30, '--flagged-days' => 365]);

        $this->assertDatabaseHas('broker_message_copies', ['id' => $flaggedId],
            'Flagged copies must survive the standard retention window'
        );
    }

    public function test_flagged_copies_are_deleted_after_flagged_retention(): void
    {
        // Flagged retention of 30 days — very old flagged row should be deleted
        $flaggedId = $this->insertCopy([
            'sent_at'      => now()->subDays(400),
            'reviewed_at'  => now()->subDays(400),
            'flagged'      => true,
            'flag_reason'  => 'Old flag',
            'flag_severity' => 'info',
        ]);

        Artisan::call(self::COMMAND, ['--days' => 90, '--flagged-days' => 365]);

        $this->assertDatabaseMissing('broker_message_copies', ['id' => $flaggedId],
            'Flagged copies older than flagged-days retention should be deleted'
        );
    }

    // ----------------------------------------------------------------
    // NO-CONFIG TENANTS — fall back to CLI --days default
    // ----------------------------------------------------------------

    public function test_tenants_without_config_use_default_retention(): void
    {
        // Make sure there's no broker_config for tenant 999
        DB::table('tenant_settings')
            ->where('tenant_id', 999)
            ->where('setting_key', 'broker_config')
            ->delete();

        // Create two users on tenant 999 so the FK on sender_id/receiver_id is satisfied
        $sender999 = \App\Models\User::factory()->forTenant(999)->create();
        $receiver999 = \App\Models\User::factory()->forTenant(999)->create();

        // Old row: 100 days — beyond 90-day default
        $oldId = $this->insertCopy([
            'tenant_id'   => 999,
            'sender_id'   => $sender999->id,
            'receiver_id' => $receiver999->id,
            'sent_at'     => now()->subDays(100),
            'reviewed_at' => now()->subDays(100),
            'created_at'  => now()->subDays(100),
        ]);

        // Recent row: 10 days — within 90-day default
        $recentId = $this->insertCopy([
            'tenant_id'   => 999,
            'sender_id'   => $sender999->id,
            'receiver_id' => $receiver999->id,
            'sent_at'     => now()->subDays(10),
            'reviewed_at' => now()->subDays(10),
            'created_at'  => now()->subDays(10),
        ]);

        // Default 90 days — old row (100 days) deleted, recent (10 days) survives
        Artisan::call(self::COMMAND, ['--days' => 90]);

        $this->assertDatabaseMissing('broker_message_copies', ['id' => $oldId]);
        $this->assertDatabaseHas('broker_message_copies', ['id' => $recentId]);
    }

    // ----------------------------------------------------------------
    // COMMAND OUTPUT / EXIT CODE
    // ----------------------------------------------------------------

    public function test_command_exits_with_success_and_outputs_summary(): void
    {
        $exitCode = Artisan::call(self::COMMAND);

        $output = Artisan::output();

        $this->assertEquals(0, $exitCode, 'Command should return SUCCESS exit code');
        $this->assertStringContainsString('Purged', $output,
            'Command output should include a purge summary'
        );
    }

    public function test_command_succeeds_with_nothing_to_delete(): void
    {
        // No old rows — command should complete cleanly
        $exitCode = Artisan::call(self::COMMAND, ['--days' => 1, '--flagged-days' => 1]);

        $this->assertEquals(0, $exitCode);
    }
}

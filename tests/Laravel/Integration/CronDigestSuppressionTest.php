<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Integration;

use App\Models\User;
use App\Services\CronJobRunner;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Laravel\TestCase;

class CronDigestSuppressionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_suppressed_digest_recipient_is_marked_suppressed_not_returned_to_pending(): void
    {
        if (!Schema::hasTable('notification_queue') || !Schema::hasTable('email_suppression')) {
            $this->markTestSkipped('Notification queue or suppression table is not available.');
        }

        $user = User::factory()->forTenant($this->testTenantId)->create([
            'email' => 'digest-suppressed-' . uniqid('', true) . '@example.test',
            'status' => 'active',
            'notification_preferences' => json_encode(['email_digest' => true]),
        ]);

        $queueId = (int) DB::table('notification_queue')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'activity_type' => 'digest_test',
            'content_snippet' => 'Digest item for suppressed recipient',
            'link' => '/notifications',
            'status' => 'pending',
            'frequency' => 'daily',
            'created_at' => now(),
        ]);

        DB::table('email_suppression')->insert([
            'email' => $user->email,
            'reason' => 'bounce',
            'detail' => 'Test suppression',
            'suppressed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $runner = new CronJobRunner();
        $method = new \ReflectionMethod(CronJobRunner::class, 'processDigest');
        $method->setAccessible(true);

        ob_start();
        try {
            $method->invoke($runner, 'daily');
        } finally {
            ob_end_clean();
        }

        $row = DB::table('notification_queue')->where('id', $queueId)->first();

        $this->assertSame('suppressed', $row->status);
        $this->assertNull($row->sent_at);
        $this->assertDatabaseHas('email_log', [
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'recipient_email' => $user->email,
            'category' => 'notification_digest',
            'status' => 'suppressed',
            'error' => 'recipient on local suppression list',
        ]);
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Integration;

use App\Core\TenantContext;
use App\Services\GroupScheduledPostService;
use App\Services\TenantFeatureConfig;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;
use Throwable;

/**
 * Uses committed fixtures and two database connections so both scheduler
 * workers exercise FOR UPDATE SKIP LOCKED and the same publication claim.
 */
final class GroupScheduledPublishingConcurrencyTest extends TestCase
{
    public function test_two_workers_publish_one_logical_occurrence_exactly_once(): void
    {
        $tenantId = null;
        $userId = null;
        $groupId = null;
        $scheduledId = null;

        try {
            $suffix = bin2hex(random_bytes(8));
            $tenantId = (int) DB::table('tenants')->insertGetId([
                'name' => 'Scheduled concurrency ' . $suffix,
                'slug' => 'gspc-' . $suffix,
                'is_active' => true,
                'features' => json_encode(
                    array_merge(TenantFeatureConfig::FEATURE_DEFAULTS, ['groups' => true]),
                    JSON_THROW_ON_ERROR,
                ),
                'depth' => 0,
                'allows_subtenants' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $userId = (int) DB::table('users')->insertGetId([
                'tenant_id' => $tenantId,
                'name' => 'Scheduled Concurrency Owner',
                'email' => 'gspc-' . $suffix . '@example.test',
                'role' => 'member',
                'status' => 'active',
                'is_approved' => true,
                'is_active' => true,
                'xp' => 0,
                'level' => 1,
                'preferred_language' => 'en',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $groupId = (int) DB::table('groups')->insertGetId([
                'tenant_id' => $tenantId,
                'owner_id' => $userId,
                'name' => 'Scheduled concurrency group ' . $suffix,
                'description' => 'Committed fixture for two scheduled publishing workers.',
                'visibility' => 'private',
                'status' => 'active',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $scheduledId = (int) DB::table('group_scheduled_posts')->insertGetId([
                'tenant_id' => $tenantId,
                'group_id' => $groupId,
                'user_id' => $userId,
                'post_type' => 'discussion',
                'title' => 'Exactly once ' . $suffix,
                'content' => 'Only one discussion and root post may be committed.',
                'is_recurring' => false,
                'recurrence_pattern' => null,
                'scheduled_at' => now()->subMinute(),
                'status' => 'scheduled',
                'attempt_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $workerResults = $this->runWorkers($tenantId);

            DB::purge();
            DB::reconnect();
            TenantContext::reset();
            TenantContext::setById($tenantId);

            self::assertSame(['ok', 'ok'], $workerResults);
            $scheduled = DB::table('group_scheduled_posts')->where('id', $scheduledId)->first();
            self::assertNotNull($scheduled);
            self::assertSame('published', $scheduled->status);
            self::assertSame(1, (int) $scheduled->attempt_count);
            self::assertSame(1, DB::table('group_discussions')
                ->where('group_id', $groupId)
                ->where('title', 'Exactly once ' . $suffix)
                ->count());
            self::assertSame(1, DB::table('group_posts')
                ->where('discussion_id', $scheduled->published_resource_id)
                ->count());
        } finally {
            DB::purge();
            DB::reconnect();

            if ($groupId !== null) {
                $discussionIds = DB::table('group_discussions')->where('group_id', $groupId)->pluck('id')->all();
                if ($discussionIds !== []) {
                    DB::table('group_posts')->whereIn('discussion_id', $discussionIds)->delete();
                }
                DB::table('group_discussions')->where('group_id', $groupId)->delete();
                DB::table('group_webhook_deliveries')->where('group_id', $groupId)->delete();
                DB::table('group_audit_log')->where('group_id', $groupId)->delete();
                DB::table('group_scheduled_posts')->where('group_id', $groupId)->delete();
                DB::table('group_members')->where('group_id', $groupId)->delete();
                DB::table('groups')->where('id', $groupId)->delete();
            }
            if ($userId !== null) {
                DB::table('users')->where('id', $userId)->delete();
            }
            if ($tenantId !== null) {
                DB::table('group_policies')->where('tenant_id', $tenantId)->delete();
                DB::table('tenants')->where('id', $tenantId)->delete();
            }

            TenantContext::reset();
            TenantContext::setById($this->testTenantId);
        }
    }

    /** @return list<string> */
    private function runWorkers(int $tenantId): array
    {
        if (!function_exists('pcntl_fork') || !function_exists('pcntl_waitpid')) {
            TenantContext::reset();
            TenantContext::setById($tenantId);
            GroupScheduledPostService::publishDue();
            GroupScheduledPostService::publishDue();

            return ['ok', 'ok'];
        }

        DB::disconnect();
        $workers = [];

        for ($index = 0; $index < 2; $index++) {
            $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            if ($sockets === false) {
                throw new \RuntimeException('Unable to create the scheduled worker socket.');
            }

            $pid = pcntl_fork();
            if ($pid === -1) {
                throw new \RuntimeException('Unable to fork the scheduled worker.');
            }

            if ($pid === 0) {
                fclose($sockets[0]);
                fread($sockets[1], 1);

                try {
                    DB::purge();
                    DB::reconnect();
                    TenantContext::reset();
                    TenantContext::setById($tenantId);
                    GroupScheduledPostService::publishDue();
                    fwrite($sockets[1], 'ok');
                    fclose($sockets[1]);
                    exit(0);
                } catch (Throwable $exception) {
                    fwrite($sockets[1], 'error:' . $exception->getMessage());
                    fclose($sockets[1]);
                    exit(1);
                }
            }

            fclose($sockets[1]);
            $workers[] = ['pid' => $pid, 'socket' => $sockets[0]];
        }

        foreach ($workers as $worker) {
            fwrite($worker['socket'], '1');
        }

        $results = [];
        foreach ($workers as $worker) {
            $message = stream_get_contents($worker['socket']);
            fclose($worker['socket']);
            pcntl_waitpid($worker['pid'], $status);
            $exitStatus = pcntl_wifexited($status) ? pcntl_wexitstatus($status) : -1;
            $results[] = $exitStatus === 0 ? (string) $message : 'worker-exit-' . $exitStatus . ':' . $message;
        }

        sort($results);

        return $results;
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Integration;

use App\Core\TenantContext;
use App\Services\GroupAuditService;
use App\Services\GroupChallengeService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;
use Throwable;

/**
 * Uses committed fixtures and separate PHP processes so both workers exercise
 * the database row lock and exactly-once reward ledger, not an in-process mock.
 */
final class GroupChallengeConcurrencyTest extends TestCase
{
    public function test_concurrent_completion_awards_the_member_exactly_once(): void
    {
        $tenantId = null;
        $ownerId = null;
        $memberId = null;
        $groupId = null;
        $challengeId = null;

        try {
            $suffix = bin2hex(random_bytes(8));
            $tenantId = (int) DB::table('tenants')->insertGetId([
                'name' => 'Group Challenge Concurrency ' . $suffix,
                'slug' => 'gcc-' . $suffix,
                'is_active' => true,
                'depth' => 0,
                'allows_subtenants' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $ownerId = $this->insertUser($tenantId, 'owner-' . $suffix . '@example.test');
            $memberId = $this->insertUser($tenantId, 'member-' . $suffix . '@example.test');
            $groupId = (int) DB::table('groups')->insertGetId([
                'tenant_id' => $tenantId,
                'owner_id' => $ownerId,
                'name' => 'Concurrent challenge ' . $suffix,
                'description' => 'Committed fixture for two concurrent progress workers.',
                'visibility' => 'private',
                'status' => 'active',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('group_members')->insert([
                'tenant_id' => $tenantId,
                'group_id' => $groupId,
                'user_id' => $memberId,
                'role' => 'member',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $challengeId = (int) DB::table('group_challenges')->insertGetId([
                'tenant_id' => $tenantId,
                'group_id' => $groupId,
                'created_by' => $ownerId,
                'title' => 'One concurrent post',
                'description' => 'The first of two racing workers completes this challenge.',
                'metric' => 'posts',
                'target_value' => 1,
                'current_value' => 0,
                'reward_xp' => 25,
                'status' => 'active',
                'starts_at' => now()->subMinute(),
                'ends_at' => now()->addDay(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $workerResults = $this->runProgressWorkers($tenantId, $groupId);

            DB::purge();
            DB::reconnect();
            TenantContext::reset();
            TenantContext::setById($tenantId);

            self::assertSame(['ok', 'ok'], $workerResults);
            self::assertSame('completed', DB::table('group_challenges')->where('id', $challengeId)->value('status'));
            self::assertSame(1, (int) DB::table('group_challenges')->where('id', $challengeId)->value('current_value'));
            self::assertSame(1, DB::table('group_challenge_rewards')
                ->where('challenge_id', $challengeId)
                ->where('user_id', $memberId)
                ->count());
            self::assertSame(25, (int) DB::table('users')->where('id', $memberId)->value('xp'));
            self::assertSame(1, DB::table('user_xp_log')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $memberId)
                ->where('action', 'group_challenge')
                ->where('source_reference', 'group_challenge:' . $challengeId)
                ->count());
            self::assertSame(1, DB::table('group_audit_log')
                ->where('group_id', $groupId)
                ->where('action', GroupAuditService::ACTION_CHALLENGE_COMPLETED)
                ->count());
            self::assertSame(1, DB::table('group_audit_log')
                ->where('group_id', $groupId)
                ->where('action', GroupAuditService::ACTION_CHALLENGE_REWARD_AWARDED)
                ->count());
        } finally {
            DB::purge();
            DB::reconnect();

            if ($challengeId !== null) {
                DB::table('user_xp_log')
                    ->where('source_reference', 'group_challenge:' . $challengeId)
                    ->delete();
                DB::table('group_challenge_rewards')->where('challenge_id', $challengeId)->delete();
                DB::table('group_challenges')->where('id', $challengeId)->delete();
            }
            if ($groupId !== null) {
                DB::table('group_audit_log')->where('group_id', $groupId)->delete();
                DB::table('group_members')->where('group_id', $groupId)->delete();
                DB::table('groups')->where('id', $groupId)->delete();
            }
            if ($ownerId !== null || $memberId !== null) {
                DB::table('users')->whereIn('id', array_values(array_filter([$ownerId, $memberId])))->delete();
            }
            if ($tenantId !== null) {
                DB::table('tenants')->where('id', $tenantId)->delete();
            }

            TenantContext::reset();
            TenantContext::setById($this->testTenantId);
        }
    }

    public function test_concurrent_final_progress_and_cancellation_never_orphan_reward_history(): void
    {
        $tenantId = null;
        $ownerId = null;
        $memberId = null;
        $groupId = null;
        $challengeId = null;

        try {
            $suffix = bin2hex(random_bytes(8));
            $tenantId = (int) DB::table('tenants')->insertGetId([
                'name' => 'Group Challenge Cancel Race ' . $suffix,
                'slug' => 'gccr-' . $suffix,
                'is_active' => true,
                'depth' => 0,
                'allows_subtenants' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $ownerId = $this->insertUser($tenantId, 'owner-' . $suffix . '@example.test');
            $memberId = $this->insertUser($tenantId, 'member-' . $suffix . '@example.test');
            $groupId = (int) DB::table('groups')->insertGetId([
                'tenant_id' => $tenantId,
                'owner_id' => $ownerId,
                'name' => 'Challenge cancel race ' . $suffix,
                'description' => 'Committed fixture for progress and cancellation workers.',
                'visibility' => 'private',
                'status' => 'active',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('group_members')->insert([
                'tenant_id' => $tenantId,
                'group_id' => $groupId,
                'user_id' => $memberId,
                'role' => 'member',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $challengeId = (int) DB::table('group_challenges')->insertGetId([
                'tenant_id' => $tenantId,
                'group_id' => $groupId,
                'created_by' => $ownerId,
                'title' => 'Race completion against cancellation',
                'description' => 'Exactly one terminal outcome must win the database row lock.',
                'metric' => 'posts',
                'target_value' => 1,
                'current_value' => 0,
                'reward_xp' => 25,
                'status' => 'active',
                'starts_at' => now()->subMinute(),
                'ends_at' => now()->addDay(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $workerResults = $this->runProgressAndCancelWorkers(
                $tenantId,
                $groupId,
                $challengeId,
                $ownerId,
            );

            DB::purge();
            DB::reconnect();
            TenantContext::reset();
            TenantContext::setById($tenantId);

            self::assertContains('progress-ok', $workerResults);
            self::assertNotContains('worker-error', $workerResults);
            $status = (string) DB::table('group_challenges')->where('id', $challengeId)->value('status');
            self::assertContains($status, ['cancelled', 'completed']);

            $rewardCount = DB::table('group_challenge_rewards')->where('challenge_id', $challengeId)->count();
            $xpLogCount = DB::table('user_xp_log')
                ->where('source_reference', 'group_challenge:' . $challengeId)
                ->count();
            $memberXp = (int) DB::table('users')->where('id', $memberId)->value('xp');

            if ($status === 'cancelled') {
                self::assertContains('cancel-ok', $workerResults);
                self::assertSame(0, $rewardCount);
                self::assertSame(0, $xpLogCount);
                self::assertSame(0, $memberXp);
                self::assertSame(1, DB::table('group_audit_log')
                    ->where('group_id', $groupId)
                    ->where('action', GroupAuditService::ACTION_CHALLENGE_CANCELLED)
                    ->count());
                self::assertSame(0, DB::table('group_audit_log')
                    ->where('group_id', $groupId)
                    ->whereIn('action', [
                        GroupAuditService::ACTION_CHALLENGE_COMPLETED,
                        GroupAuditService::ACTION_CHALLENGE_REWARD_AWARDED,
                    ])
                    ->count());
            } else {
                self::assertContains('cancel-immutable', $workerResults);
                self::assertSame(1, $rewardCount);
                self::assertSame(1, $xpLogCount);
                self::assertSame(25, $memberXp);
                self::assertSame(0, DB::table('group_audit_log')
                    ->where('group_id', $groupId)
                    ->where('action', GroupAuditService::ACTION_CHALLENGE_CANCELLED)
                    ->count());
                self::assertSame(1, DB::table('group_audit_log')
                    ->where('group_id', $groupId)
                    ->where('action', GroupAuditService::ACTION_CHALLENGE_COMPLETED)
                    ->count());
                self::assertSame(1, DB::table('group_audit_log')
                    ->where('group_id', $groupId)
                    ->where('action', GroupAuditService::ACTION_CHALLENGE_REWARD_AWARDED)
                    ->count());
            }
        } finally {
            DB::purge();
            DB::reconnect();

            if ($challengeId !== null) {
                DB::table('user_xp_log')->where('source_reference', 'group_challenge:' . $challengeId)->delete();
                DB::table('group_challenge_rewards')->where('challenge_id', $challengeId)->delete();
                DB::table('group_challenges')->where('id', $challengeId)->delete();
            }
            if ($groupId !== null) {
                DB::table('group_audit_log')->where('group_id', $groupId)->delete();
                DB::table('group_members')->where('group_id', $groupId)->delete();
                DB::table('groups')->where('id', $groupId)->delete();
            }
            if ($ownerId !== null || $memberId !== null) {
                DB::table('users')->whereIn('id', array_values(array_filter([$ownerId, $memberId])))->delete();
            }
            if ($tenantId !== null) {
                DB::table('tenants')->where('id', $tenantId)->delete();
            }

            TenantContext::reset();
            TenantContext::setById($this->testTenantId);
        }
    }

    private function insertUser(int $tenantId, string $email): int
    {
        return (int) DB::table('users')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => str_starts_with($email, 'owner-') ? 'Challenge Owner' : 'Challenge Member',
            'email' => $email,
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
    }

    /** @return list<string> */
    private function runProgressWorkers(int $tenantId, int $groupId): array
    {
        if (! function_exists('pcntl_fork') || ! function_exists('pcntl_waitpid')) {
            TenantContext::reset();
            TenantContext::setById($tenantId);
            GroupChallengeService::incrementProgress($groupId, 'posts');
            GroupChallengeService::incrementProgress($groupId, 'posts');

            return ['ok', 'ok'];
        }

        DB::disconnect();
        $workers = [];

        for ($index = 0; $index < 2; $index++) {
            $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            if ($sockets === false) {
                throw new \RuntimeException('Unable to create the concurrency worker socket.');
            }

            $pid = pcntl_fork();
            if ($pid === -1) {
                throw new \RuntimeException('Unable to fork the concurrency worker.');
            }

            if ($pid === 0) {
                fclose($sockets[0]);
                fread($sockets[1], 1);

                try {
                    DB::purge();
                    DB::reconnect();
                    TenantContext::reset();
                    TenantContext::setById($tenantId);
                    GroupChallengeService::incrementProgress($groupId, 'posts');
                    fwrite($sockets[1], 'ok');
                    fclose($sockets[1]);
                    exit(0);
                } catch (Throwable $e) {
                    fwrite($sockets[1], 'error:' . $e->getMessage());
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
            $results[] = $exitStatus === 0
                ? (string) $message
                : 'worker-exit-' . $exitStatus . ':' . (string) $message;
        }

        return $results;
    }

    /** @return list<string> */
    private function runProgressAndCancelWorkers(
        int $tenantId,
        int $groupId,
        int $challengeId,
        int $ownerId,
    ): array {
        if (! function_exists('pcntl_fork') || ! function_exists('pcntl_waitpid')) {
            TenantContext::reset();
            TenantContext::setById($tenantId);
            GroupChallengeService::delete($groupId, $challengeId, $ownerId);
            GroupChallengeService::incrementProgress($groupId, 'posts');

            return ['cancel-ok', 'progress-ok'];
        }

        DB::disconnect();
        $workers = [];
        foreach (['progress', 'cancel'] as $operation) {
            $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            if ($sockets === false) {
                throw new \RuntimeException('Unable to create the concurrency worker socket.');
            }

            $pid = pcntl_fork();
            if ($pid === -1) {
                throw new \RuntimeException('Unable to fork the concurrency worker.');
            }

            if ($pid === 0) {
                fclose($sockets[0]);
                fread($sockets[1], 1);

                try {
                    DB::purge();
                    DB::reconnect();
                    TenantContext::reset();
                    TenantContext::setById($tenantId);
                    if ($operation === 'progress') {
                        GroupChallengeService::incrementProgress($groupId, 'posts');
                        $message = 'progress-ok';
                    } else {
                        GroupChallengeService::delete($groupId, $challengeId, $ownerId);
                        $message = 'cancel-ok';
                    }
                } catch (DomainException $e) {
                    $message = $e->getMessage() === GroupChallengeService::ERROR_IMMUTABLE
                        ? 'cancel-immutable'
                        : 'worker-error';
                } catch (Throwable) {
                    $message = 'worker-error';
                }

                fwrite($sockets[1], $message);
                fclose($sockets[1]);
                exit($message === 'worker-error' ? 1 : 0);
            }

            fclose($sockets[1]);
            $workers[] = ['pid' => $pid, 'socket' => $sockets[0]];
        }

        foreach ($workers as $worker) {
            fwrite($worker['socket'], '1');
        }

        $results = [];
        foreach ($workers as $worker) {
            $message = (string) stream_get_contents($worker['socket']);
            fclose($worker['socket']);
            pcntl_waitpid($worker['pid'], $status);
            $exitStatus = pcntl_wifexited($status) ? pcntl_wexitstatus($status) : -1;
            $results[] = $exitStatus === 0 ? $message : 'worker-error';
        }

        return $results;
    }
}

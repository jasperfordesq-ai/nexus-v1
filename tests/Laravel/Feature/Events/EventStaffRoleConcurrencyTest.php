<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Core\TenantContext;
use App\Enums\EventStaffRole;
use App\Models\User;
use App\Services\EventRoleService;
use App\Services\TenantFeatureConfig;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;
use Throwable;

/** Exercises the event row lock with two committed, independent workers. */
final class EventStaffRoleConcurrencyTest extends TestCase
{
    public function test_concurrent_identical_grants_create_one_version_history_and_outbox_event(): void
    {
        $suffix = bin2hex(random_bytes(8));
        $tenantId = (int) DB::table('tenants')->insertGetId([
            'name' => 'Event role concurrency ' . $suffix,
            'slug' => 'erc-' . $suffix,
            'features' => json_encode(
                array_merge(TenantFeatureConfig::FEATURE_DEFAULTS, ['events' => true]),
                JSON_THROW_ON_ERROR,
            ),
            'is_active' => true,
            'depth' => 0,
            'allows_subtenants' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $ownerId = $this->insertUser($tenantId, 'owner-' . $suffix . '@example.test');
        $staffId = $this->insertUser($tenantId, 'staff-' . $suffix . '@example.test');
        $start = now()->addWeek();
        $eventId = (int) DB::table('events')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id' => $ownerId,
            'title' => 'Concurrent role ' . $suffix,
            'description' => 'Committed concurrency fixture.',
            'start_time' => $start,
            'end_time' => $start->copy()->addHour(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $results = $this->runWorkers($tenantId, $eventId, $ownerId, $staffId);

        DB::purge();
        DB::reconnect();
        TenantContext::reset();
        TenantContext::setById($tenantId);

        sort($results);
        self::assertSame(['changed', 'unchanged'], $results);
        $assignment = DB::table('event_staff_assignments')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('user_id', $staffId)
            ->where('role', EventStaffRole::CheckInStaff->value)
            ->first();
        self::assertNotNull($assignment);
        self::assertSame(1, (int) $assignment->assignment_version);
        self::assertSame(1, DB::table('event_staff_assignment_history')
            ->where('assignment_id', $assignment->id)
            ->count());
        self::assertSame(1, DB::table('event_domain_outbox')
            ->where('event_id', $eventId)
            ->where('action', 'event.staff_role.granted')
            ->count());

        // Immutable evidence intentionally remains until the isolated Events
        // test database is dropped; deleting it would invalidate this contract.
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    private function insertUser(int $tenantId, string $email): int
    {
        return (int) DB::table('users')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => str_starts_with($email, 'owner-') ? 'Role Owner' : 'Role Staff',
            'email' => $email,
            'role' => 'member',
            'status' => 'active',
            'is_approved' => true,
            'is_active' => true,
            'preferred_language' => 'en',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return list<string> */
    private function runWorkers(int $tenantId, int $eventId, int $ownerId, int $staffId): array
    {
        if (!function_exists('pcntl_fork') || !function_exists('pcntl_waitpid')) {
            TenantContext::reset();
            TenantContext::setById($tenantId);
            /** @var User $owner */
            $owner = User::withoutGlobalScopes()->findOrFail($ownerId);
            $service = new EventRoleService();
            $first = $service->grant($eventId, $staffId, EventStaffRole::CheckInStaff, $owner);
            $second = $service->grant($eventId, $staffId, EventStaffRole::CheckInStaff, $owner);

            return [$first['changed'] ? 'changed' : 'unchanged', $second['changed'] ? 'changed' : 'unchanged'];
        }

        DB::disconnect();
        $workers = [];
        for ($index = 0; $index < 2; $index++) {
            $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            if ($sockets === false) {
                throw new \RuntimeException('event_staff_role_concurrency_socket_failed');
            }
            $pid = pcntl_fork();
            if ($pid === -1) {
                throw new \RuntimeException('event_staff_role_concurrency_fork_failed');
            }
            if ($pid === 0) {
                fclose($sockets[0]);
                fread($sockets[1], 1);

                try {
                    DB::purge();
                    DB::reconnect();
                    TenantContext::reset();
                    TenantContext::setById($tenantId);
                    /** @var User $owner */
                    $owner = User::withoutGlobalScopes()->findOrFail($ownerId);
                    $result = (new EventRoleService())->grant(
                        $eventId,
                        $staffId,
                        EventStaffRole::CheckInStaff,
                        $owner,
                    );
                    fwrite($sockets[1], $result['changed'] ? 'changed' : 'unchanged');
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
            $results[] = $exitStatus === 0
                ? (string) $message
                : 'worker-exit-' . $exitStatus . ':' . (string) $message;
        }

        return $results;
    }
}

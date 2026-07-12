<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Core\TenantContext;
use App\Exceptions\EventSessionException;
use App\Models\User;
use App\Services\EventSessionService;
use App\Services\TenantFeatureConfig;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;
use Throwable;

/** Proves the event aggregate lock serializes overlapping agenda writes. */
final class EventSessionConcurrencyTest extends TestCase
{
    public function test_concurrent_room_conflicts_commit_one_session_and_one_agenda_version(): void
    {
        $suffix = bin2hex(random_bytes(8));
        $tenantId = (int) DB::table('tenants')->insertGetId([
            'name' => 'Agenda concurrency ' . $suffix,
            'slug' => 'agenda-' . $suffix,
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
        $ownerId = (int) DB::table('users')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => 'Agenda Owner',
            'email' => 'agenda-owner-' . $suffix . '@example.test',
            'role' => 'member',
            'status' => 'active',
            'is_approved' => true,
            'is_active' => true,
            'preferred_language' => 'en',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $start = CarbonImmutable::now('UTC')->addMonth()->startOfHour();
        $eventId = (int) DB::table('events')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id' => $ownerId,
            'title' => 'Concurrent agenda event',
            'description' => 'Committed concurrency fixture.',
            'start_time' => $start,
            'end_time' => $start->addHours(3),
            'timezone' => 'UTC',
            'timezone_source' => 'test',
            'all_day' => false,
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'is_recurring_template' => false,
            'agenda_version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $results = $this->runWorkers($tenantId, $eventId, $ownerId, $start);

        DB::purge();
        DB::reconnect();
        TenantContext::reset();
        TenantContext::setById($tenantId);
        sort($results);
        self::assertSame(['created', 'event_agenda_room_conflict'], $results);
        self::assertSame(1, DB::table('event_sessions')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->count());
        self::assertSame(1, DB::table('event_session_history')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->count());
        self::assertSame(1, (int) DB::table('events')->where('id', $eventId)->value('agenda_version'));

        // Durable history intentionally remains until the isolated test DB is dropped.
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    /** @return list<string> */
    private function runWorkers(
        int $tenantId,
        int $eventId,
        int $ownerId,
        CarbonImmutable $start,
    ): array {
        if (! function_exists('pcntl_fork') || ! function_exists('pcntl_waitpid')) {
            TenantContext::reset();
            TenantContext::setById($tenantId);
            /** @var User $owner */
            $owner = User::withoutGlobalScopes()->findOrFail($ownerId);
            $service = new EventSessionService();
            $service->create($eventId, $owner, $this->payload($start, 'First'), 'agenda-worker-1');
            try {
                $service->create($eventId, $owner, $this->payload($start, 'Second'), 'agenda-worker-2');

                return ['created', 'unexpected-created'];
            } catch (EventSessionException $exception) {
                return ['created', $exception->reasonCode];
            }
        }

        DB::disconnect();
        $workers = [];
        for ($index = 0; $index < 2; $index++) {
            $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            if ($sockets === false) {
                throw new \RuntimeException('event_agenda_concurrency_socket_failed');
            }
            $pid = pcntl_fork();
            if ($pid === -1) {
                throw new \RuntimeException('event_agenda_concurrency_fork_failed');
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
                    (new EventSessionService())->create(
                        $eventId,
                        $owner,
                        $this->payload($start, 'Worker ' . $index),
                        'agenda-worker-' . $index,
                    );
                    fwrite($sockets[1], 'created');
                    fclose($sockets[1]);
                    exit(0);
                } catch (EventSessionException $exception) {
                    fwrite($sockets[1], $exception->reasonCode);
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

    /** @return array<string,mixed> */
    private function payload(CarbonImmutable $start, string $title): array
    {
        return [
            'title' => $title,
            'start_at' => $start->toIso8601String(),
            'end_at' => $start->addHour()->toIso8601String(),
            'timezone' => 'UTC',
            'room_name' => 'Main Hall',
            'speakers' => [],
        ];
    }
}

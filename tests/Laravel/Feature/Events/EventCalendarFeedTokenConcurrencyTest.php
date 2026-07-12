<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\EventCalendarService;
use App\Services\TenantFeatureConfig;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Laravel\TestCase;
use Throwable;

/** Independent connections prove the locked member row enforces the active-token cap. */
final class EventCalendarFeedTokenConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!function_exists('pcntl_fork') || !function_exists('pcntl_waitpid')) {
            self::markTestSkipped('pcntl is required for calendar-token concurrency tests.');
        }
    }

    public function test_simultaneous_creates_cannot_exceed_one_active_token(): void
    {
        [$tenantId, $userId] = $this->fixture();
        DB::disconnect();
        $workers = [];
        for ($index = 0; $index < 2; $index++) {
            $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            if ($sockets === false) {
                throw new RuntimeException('event_calendar_concurrency_socket_failed');
            }
            $pid = pcntl_fork();
            if ($pid === -1) {
                throw new RuntimeException('event_calendar_concurrency_fork_failed');
            }
            if ($pid === 0) {
                fclose($sockets[0]);
                fread($sockets[1], 1);
                try {
                    DB::purge();
                    DB::reconnect();
                    TenantContext::reset();
                    TenantContext::setById($tenantId);
                    Config::set('events.calendar.max_active_feed_tokens', 1);
                    /** @var User $user */
                    $user = User::withoutGlobalScopes()->findOrFail($userId);
                    app(EventCalendarService::class)->createFeedToken($user, 'Concurrent');
                    fwrite($sockets[1], 'created');
                    fclose($sockets[1]);
                    exit(0);
                } catch (\DomainException $exception) {
                    fwrite($sockets[1], $exception->getMessage());
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
            $exit = pcntl_wifexited($status) ? pcntl_wexitstatus($status) : -1;
            $results[] = $exit === 0 ? (string) $message : "worker-exit-{$exit}:{$message}";
        }

        DB::purge();
        DB::reconnect();
        sort($results);
        self::assertSame(['created', 'event_calendar_token_limit'], $results);
        self::assertSame(1, DB::table('event_calendar_feed_tokens')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->count());

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    /** @return array{int,int} */
    private function fixture(): array
    {
        $suffix = bin2hex(random_bytes(8));
        $tenantId = (int) DB::table('tenants')->insertGetId([
            'name' => 'Calendar token concurrency ' . $suffix,
            'slug' => 'ect-' . $suffix,
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
        $userId = (int) DB::table('users')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => 'Calendar concurrency member',
            'email' => "calendar-{$suffix}@example.test",
            'role' => 'member',
            'status' => 'active',
            'is_approved' => true,
            'is_active' => true,
            'preferred_language' => 'en',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$tenantId, $userId];
    }
}

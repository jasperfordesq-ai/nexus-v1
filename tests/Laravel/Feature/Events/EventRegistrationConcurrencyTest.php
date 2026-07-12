<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Core\TenantContext;
use App\Exceptions\EventRegistrationException;
use App\Exceptions\EventWaitlistException;
use App\Models\User;
use App\Services\EventRegistrationService;
use App\Services\EventWaitlistService;
use App\Services\TenantFeatureConfig;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Laravel\TestCase;
use Throwable;

/** Real independent connections prove the event lock prevents capacity oversell. */
final class EventRegistrationConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('app.key', 'base64:HfQEDtbtr90JIXhsaAhSFWnzIo1f31VZ2e5qLqKKnls=');
        Config::set('events.notification_delivery.mode', 'outbox_authoritative');
        Config::set('events.notification_delivery.consumer_enabled', true);
        Config::set('events.registration.default_capacity_pool_key', 'event');
        Config::set('events.registration.legacy_dual_read', true);
        Config::set('events.registration.legacy_dual_write', true);
        Config::set('events.registration.timed_waitlist_offers_enabled', true);
        Config::set('events.registration.offer_ttl_minutes', 15);
        Config::set('event_waitlist.envelope.active_key_version', 'concurrency-test-v1');
        Config::set('event_waitlist.envelope.active_key', null);
        Config::set('event_waitlist.envelope.fallback_to_app_key', true);
        if (! function_exists('pcntl_fork') || ! function_exists('pcntl_waitpid')) {
            self::markTestSkipped('pcntl is required for real Events registration concurrency tests.');
        }
    }

    public function test_two_simultaneous_confirmations_cannot_overbook_one_place(): void
    {
        [$tenantId, $eventId, , $firstId, $secondId] = $this->fixture();

        $results = $this->runWorkers([
            ['confirm', $tenantId, $eventId, $firstId, null],
            ['confirm', $tenantId, $eventId, $secondId, null],
        ]);
        $this->reconnectFor($tenantId);

        sort($results);
        self::assertSame(['confirmed', 'event_registration_capacity_full'], $results);
        self::assertSame(1, DB::table('event_registrations')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('registration_state', 'confirmed')
            ->count());
        self::assertSame(1, DB::table('event_rsvps')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->whereIn('status', ['going', 'attended'])
            ->count());

        $this->restoreTenant();
    }

    public function test_concurrent_idempotent_retries_create_one_fact_history_and_outbox(): void
    {
        [$tenantId, $eventId, , $memberId] = $this->fixture();

        $results = $this->runWorkers([
            ['confirm', $tenantId, $eventId, $memberId, null],
            ['confirm', $tenantId, $eventId, $memberId, null],
        ]);
        $this->reconnectFor($tenantId);

        sort($results);
        self::assertSame(['confirmed', 'confirmed'], $results);
        self::assertSame(1, DB::table('event_registrations')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('user_id', $memberId)
            ->count());
        self::assertSame(1, DB::table('event_registration_history')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('user_id', $memberId)
            ->count());
        self::assertSame(1, DB::table('event_domain_outbox')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('action', 'event.registration.confirmed')
            ->count());

        $this->restoreTenant();
    }

    public function test_offer_acceptance_racing_direct_registration_cannot_overbook(): void
    {
        [$tenantId, $eventId, , $holderId, $waiterId, $competitorId] = $this->fixture(true);
        TenantContext::reset();
        TenantContext::setById($tenantId);
        /** @var User $holder */
        $holder = User::withoutGlobalScopes()->findOrFail($holderId);
        /** @var User $waiter */
        $waiter = User::withoutGlobalScopes()->findOrFail($waiterId);
        $registrations = new EventRegistrationService();
        $waitlist = new EventWaitlistService($registrations);
        $registrations->confirm($eventId, $holderId, $holder, 'seed-holder');
        $waitlist->join($eventId, $waiterId, $waiter, 'seed-waiter');
        $released = $registrations->withdraw($eventId, $holderId, $holder, 'release-holder');
        self::assertNotNull($released->offerToken);

        $results = $this->runWorkers([
            ['accept', $tenantId, $eventId, $waiterId, $released->offerToken],
            ['confirm', $tenantId, $eventId, $competitorId, null],
        ]);
        $this->reconnectFor($tenantId);

        sort($results);
        self::assertSame(['accepted', 'event_registration_capacity_full'], $results);
        $confirmed = DB::table('event_registrations')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('registration_state', 'confirmed')
            ->pluck('user_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
        self::assertSame([$waiterId], $confirmed);
        self::assertSame('accepted', DB::table('event_waitlist_entries')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('user_id', $waiterId)
            ->value('queue_state'));

        $this->restoreTenant();
    }

    /**
     * @param list<array{string,int,int,int,?string}> $operations
     * @return list<string>
     */
    private function runWorkers(array $operations): array
    {
        DB::disconnect();
        $workers = [];
        foreach ($operations as $operation) {
            $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            if ($sockets === false) {
                throw new RuntimeException('event_registration_concurrency_socket_failed');
            }
            $pid = pcntl_fork();
            if ($pid === -1) {
                throw new RuntimeException('event_registration_concurrency_fork_failed');
            }
            if ($pid === 0) {
                fclose($sockets[0]);
                fread($sockets[1], 1);
                [$type, $tenantId, $eventId, $userId, $token] = $operation;
                try {
                    DB::purge();
                    DB::reconnect();
                    TenantContext::reset();
                    TenantContext::setById($tenantId);
                    Config::set('events.notification_delivery.mode', 'outbox_authoritative');
                    Config::set('events.notification_delivery.consumer_enabled', true);
                    Config::set('events.registration.legacy_dual_read', true);
                    Config::set('events.registration.legacy_dual_write', true);
                    Config::set('events.registration.timed_waitlist_offers_enabled', true);
                    /** @var User $actor */
                    $actor = User::withoutGlobalScopes()->findOrFail($userId);
                    if ($type === 'accept') {
                        (new EventWaitlistService())->acceptOffer(
                            $eventId,
                            $userId,
                            (string) $token,
                            $actor,
                            "concurrent-accept-{$userId}",
                        );
                        $message = 'accepted';
                    } else {
                        (new EventRegistrationService())->confirm(
                            $eventId,
                            $userId,
                            $actor,
                            "concurrent-confirm-{$userId}",
                        );
                        $message = 'confirmed';
                    }
                    fwrite($sockets[1], $message);
                    fclose($sockets[1]);
                    exit(0);
                } catch (EventRegistrationException|EventWaitlistException $exception) {
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
            $exit = pcntl_wifexited($status) ? pcntl_wexitstatus($status) : -1;
            $results[] = $exit === 0
                ? (string) $message
                : "worker-exit-{$exit}:{$message}";
        }

        return $results;
    }

    /** @return array{int,int,int,int,int,int} */
    private function fixture(bool $extraMember = false): array
    {
        $suffix = bin2hex(random_bytes(8));
        $tenantId = (int) DB::table('tenants')->insertGetId([
            'name' => 'Event registration concurrency ' . $suffix,
            'slug' => 'evr-' . $suffix,
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
        $ownerId = $this->insertUser($tenantId, "owner-{$suffix}@example.test");
        $firstId = $this->insertUser($tenantId, "first-{$suffix}@example.test");
        $secondId = $this->insertUser($tenantId, "second-{$suffix}@example.test");
        $thirdId = $extraMember
            ? $this->insertUser($tenantId, "third-{$suffix}@example.test")
            : $secondId;
        $start = now()->addWeek();
        $eventId = (int) DB::table('events')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id' => $ownerId,
            'title' => 'Concurrent registration ' . $suffix,
            'description' => 'Committed capacity fixture.',
            'start_time' => $start,
            'end_time' => $start->copy()->addHour(),
            'timezone' => 'UTC',
            'timezone_source' => 'explicit',
            'all_day' => 0,
            'occurrence_key' => 'concurrency:' . $suffix,
            'is_recurring_template' => 0,
            'max_attendees' => 1,
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'lifecycle_version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$tenantId, $eventId, $ownerId, $firstId, $secondId, $thirdId];
    }

    private function insertUser(int $tenantId, string $email): int
    {
        return (int) DB::table('users')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => 'Registration member',
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

    private function reconnectFor(int $tenantId): void
    {
        DB::purge();
        DB::reconnect();
        TenantContext::reset();
        TenantContext::setById($tenantId);
    }

    private function restoreTenant(): void
    {
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }
}

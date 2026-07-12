<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Core\TenantContext;
use App\Exceptions\EventTicketingException;
use App\Models\User;
use App\Services\EventTicketEntitlementService;
use App\Services\EventTicketTypeService;
use App\Services\TenantFeatureConfig;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Laravel\Feature\Events\Concerns\BuildsEventTicketingFixtures;
use Tests\Laravel\TestCase;
use Throwable;

/** Independent connections prove the ticket-type lock serializes free inventory. */
final class EventTicketEntitlementConcurrencyTest extends TestCase
{
    use BuildsEventTicketingFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        if (! function_exists('pcntl_fork') || ! function_exists('pcntl_waitpid')) {
            self::markTestSkipped('pcntl is required for real Events ticket concurrency tests.');
        }
    }

    public function test_two_members_racing_for_one_unit_cannot_oversell_inventory(): void
    {
        [$tenantId, $eventId, $ticketTypeId, $firstRegistration, $firstMember, $secondRegistration, $secondMember]
            = $this->fixture();

        $results = $this->runWorkers([
            [$tenantId, $eventId, $ticketTypeId, $firstRegistration, $firstMember, 'ticket-race-first'],
            [$tenantId, $eventId, $ticketTypeId, $secondRegistration, $secondMember, 'ticket-race-second'],
        ]);
        $this->reconnectFor($tenantId);

        sort($results);
        self::assertSame(['allocated', 'event_ticket_allocation_exhausted'], $results);
        self::assertSame(1, DB::table('event_ticket_entitlements')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('ticket_type_id', $ticketTypeId)
            ->where('status', 'confirmed')
            ->count());
        self::assertSame(1, (int) DB::table('event_ticket_inventory_history')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('ticket_type_id', $ticketTypeId)
            ->sum('quantity_delta'));

        $this->restoreTenant();
    }

    public function test_concurrent_same_idempotency_key_materializes_one_entitlement_and_one_history_pair(): void
    {
        [$tenantId, $eventId, $ticketTypeId, $registrationId, $memberId] = $this->fixture();
        $operation = [
            $tenantId,
            $eventId,
            $ticketTypeId,
            $registrationId,
            $memberId,
            'ticket-race-idempotent',
        ];

        $results = $this->runWorkers([$operation, $operation]);
        $this->reconnectFor($tenantId);

        sort($results);
        self::assertSame(['allocated', 'replayed'], $results);
        self::assertSame(1, DB::table('event_ticket_entitlements')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('ticket_type_id', $ticketTypeId)
            ->count());
        self::assertSame(1, DB::table('event_ticket_entitlement_history')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('ticket_type_id', $ticketTypeId)
            ->count());
        self::assertSame(1, DB::table('event_ticket_inventory_history')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('ticket_type_id', $ticketTypeId)
            ->count());

        $this->restoreTenant();
    }

    /**
     * @param list<array{int,int,int,int,int,string}> $operations
     * @return list<string>
     */
    private function runWorkers(array $operations): array
    {
        DB::disconnect();
        $workers = [];
        foreach ($operations as $operation) {
            $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            if ($sockets === false) {
                throw new RuntimeException('event_ticket_concurrency_socket_failed');
            }
            $pid = pcntl_fork();
            if ($pid === -1) {
                throw new RuntimeException('event_ticket_concurrency_fork_failed');
            }
            if ($pid === 0) {
                fclose($sockets[0]);
                fread($sockets[1], 1);
                [$tenantId, $eventId, $ticketTypeId, $registrationId, $memberId, $key] = $operation;
                try {
                    DB::purge();
                    DB::reconnect();
                    TenantContext::reset();
                    TenantContext::setById($tenantId);
                    /** @var User $member */
                    $member = User::withoutGlobalScopes()->findOrFail($memberId);
                    $result = (new EventTicketEntitlementService())->allocateSelf(
                        $eventId,
                        $ticketTypeId,
                        $registrationId,
                        $member,
                        1,
                        $key,
                    );
                    fwrite($sockets[1], $result['changed'] ? 'allocated' : 'replayed');
                    fclose($sockets[1]);
                    exit(0);
                } catch (EventTicketingException $exception) {
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
            $results[] = $exit === 0
                ? (string) $message
                : "worker-exit-{$exit}:{$message}";
        }

        return $results;
    }

    /** @return array{int,int,int,int,int,int,int} */
    private function fixture(): array
    {
        $suffix = bin2hex(random_bytes(8));
        $tenantId = (int) DB::table('tenants')->insertGetId([
            'name' => 'Event ticket concurrency ' . $suffix,
            'slug' => 'evt-' . $suffix,
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
        $firstMemberId = $this->insertUser($tenantId, "first-{$suffix}@example.test");
        $secondMemberId = $this->insertUser($tenantId, "second-{$suffix}@example.test");
        TenantContext::reset();
        TenantContext::setById($tenantId);
        $start = CarbonImmutable::now('UTC')->addWeek()->startOfHour();
        [$eventId] = $this->ticketEvent(
            $ownerId,
            $start,
            $start->addHours(2),
            'UTC',
            $tenantId,
        );
        $firstRegistration = $this->ticketRegistration($eventId, $firstMemberId, 'confirmed', $tenantId);
        $secondRegistration = $this->ticketRegistration($eventId, $secondMemberId, 'confirmed', $tenantId);
        /** @var User $owner */
        $owner = User::withoutGlobalScopes()->findOrFail($ownerId);
        $types = new EventTicketTypeService();
        $created = $types->create(
            $eventId,
            $owner,
            $this->ticketTypePayload($start, [
                'name' => 'Concurrent one-place ticket',
                'allocation_limit' => 1,
                'per_member_limit' => 1,
            ]),
            'concurrency-ticket-create-' . $suffix,
        );
        $active = $types->activate(
            $eventId,
            (int) $created['ticket_type']->id,
            $owner,
            1,
            'concurrency-ticket-activate-' . $suffix,
        );

        return [
            $tenantId,
            $eventId,
            (int) $active['ticket_type']->id,
            $firstRegistration,
            $firstMemberId,
            $secondRegistration,
            $secondMemberId,
        ];
    }

    private function insertUser(int $tenantId, string $email): int
    {
        return (int) DB::table('users')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => 'Ticket member',
            'email' => $email,
            'role' => 'member',
            'status' => 'active',
            'is_approved' => true,
            'is_active' => true,
            'preferred_language' => 'en',
            'created_at' => now()->subYear(),
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

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events\Concerns;

use App\Core\TenantContext;
use App\Models\EventTicketType;
use App\Models\User;
use App\Services\EventTicketTypeService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

trait BuildsEventTicketingFixtures
{
    protected function ticketUser(array $overrides = [], int $tenantId = 2): User
    {
        $user = User::factory()->forTenant($tenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
            'created_at' => CarbonImmutable::now('UTC')->subYear(),
        ], $overrides));
        TenantContext::setById($this->testTenantId);

        return $user;
    }

    /** @return array{0:int,1:CarbonImmutable,2:CarbonImmutable} */
    protected function ticketEvent(
        int $ownerId,
        ?CarbonImmutable $start = null,
        ?CarbonImmutable $end = null,
        string $timezone = 'UTC',
        int $tenantId = 2,
        bool $template = false,
    ): array {
        $start ??= CarbonImmutable::now($timezone)->addMonth()->startOfHour();
        $end ??= $start->addHours(3);
        $eventId = (int) DB::table('events')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id' => $ownerId,
            'title' => 'Ticketing foundation fixture',
            'description' => 'Ticketing foundation fixture.',
            'start_time' => $start->utc(),
            'end_time' => $end->utc(),
            'timezone' => $timezone,
            'timezone_source' => 'test',
            'all_day' => false,
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'is_recurring_template' => $template,
            'occurrence_key' => $template
                ? null
                : 'ticket-test:' . bin2hex(random_bytes(12)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$eventId, $start, $end];
    }

    protected function ticketRegistration(
        int $eventId,
        int $userId,
        string $state = 'confirmed',
        int $tenantId = 2,
    ): int {
        $now = CarbonImmutable::now('UTC');

        return (int) DB::table('event_registrations')->insertGetId([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'user_id' => $userId,
            'capacity_pool_key' => 'event',
            'allocation_key' => null,
            'registration_state' => $state,
            'registration_version' => 1,
            'state_changed_at' => $now,
            'state_changed_by' => $userId,
            'invited_at' => $state === 'invited' ? $now : null,
            'pending_at' => $state === 'pending' ? $now : null,
            'confirmed_at' => $state === 'confirmed' ? $now : null,
            'declined_at' => $state === 'declined' ? $now : null,
            'cancelled_at' => $state === 'cancelled' ? $now : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @return array<string,mixed> */
    protected function ticketTypePayload(
        CarbonImmutable $start,
        array $overrides = [],
    ): array {
        return array_merge([
            'name' => 'Community ticket',
            'description' => 'Organizer supplied ticket description.',
            'kind' => 'free',
            'unit_price_credits' => '0.00',
            'allocation_limit' => 10,
            'sales_opens_at' => CarbonImmutable::now($start->getTimezone())
                ->subDay()->format('Y-m-d\TH:i:sP'),
            'sales_closes_at' => $start->subDay()->format('Y-m-d\TH:i:sP'),
            'per_member_limit' => 2,
            'eligibility_policy' => [
                'approved_member_required' => true,
                'minimum_account_age_days' => 30,
                'required_group_ids' => [],
            ],
            'refund_cutoff_at' => $start->subDays(2)->format('Y-m-d\TH:i:sP'),
            'organizer_cancel_refundable' => true,
        ], $overrides);
    }

    protected function activeTicketType(
        int $eventId,
        User $owner,
        CarbonImmutable $start,
        array $overrides = [],
    ): EventTicketType {
        $service = new EventTicketTypeService();
        $created = $service->create(
            $eventId,
            $owner,
            $this->ticketTypePayload($start, $overrides),
            'ticket-type-create-' . bin2hex(random_bytes(8)),
        );
        $active = $service->activate(
            $eventId,
            (int) $created['ticket_type']->id,
            $owner,
            1,
            'ticket-type-activate-' . bin2hex(random_bytes(8)),
        );

        return $active['ticket_type'];
    }
}

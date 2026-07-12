<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Http\Resources\EventTicketResource;
use App\Services\EventTicketEntitlementService;
use App\Services\EventTicketQueryService;
use App\Services\EventTicketTypeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Laravel\Feature\Events\Concerns\BuildsEventTicketingFixtures;
use Tests\Laravel\TestCase;

final class EventTicketQueryServiceTest extends TestCase
{
    use DatabaseTransactions;
    use BuildsEventTicketingFixtures;

    public function test_catalogue_is_policy_filtered_and_time_credit_materialisation_is_explicitly_disabled(): void
    {
        $owner = $this->ticketUser();
        $member = $this->ticketUser();
        $outsider = $this->ticketUser();
        [$eventId, $start] = $this->ticketEvent((int) $owner->id);
        $registrationId = $this->ticketRegistration($eventId, (int) $member->id);
        $free = $this->activeTicketType($eventId, $owner, $start);
        $types = new EventTicketTypeService();
        $credit = $types->create(
            $eventId,
            $owner,
            $this->ticketTypePayload($start, [
                'name' => 'Time-credit ticket',
                'kind' => 'time_credit',
                'unit_price_credits' => '2.00',
            ]),
            'ticket-query-credit-draft',
        );
        $queries = app(EventTicketQueryService::class);

        $manager = EventTicketResource::catalogue($queries->read($eventId, $owner));
        self::assertTrue($manager['permissions']['manage']);
        self::assertTrue($manager['permissions']['reconcile']);
        self::assertCount(2, $manager['ticket_types']);
        $creditProjection = collect($manager['ticket_types'])
            ->firstWhere('id', (int) $credit['ticket_type']->id);
        self::assertNotNull($creditProjection);
        self::assertFalse($creditProjection['availability']['materialization_supported']);
        self::assertSame('unavailable', $creditProjection['availability']['gateway_status']);
        self::assertFalse($manager['payment_gateway']['time_credit_supported']);
        self::assertFalse($manager['payment_gateway']['money_supported']);

        $memberCatalogue = EventTicketResource::catalogue($queries->read($eventId, $member));
        self::assertFalse($memberCatalogue['permissions']['manage']);
        self::assertTrue($memberCatalogue['permissions']['allocate_self']);
        self::assertCount(1, $memberCatalogue['ticket_types']);
        self::assertSame((int) $free->id, $memberCatalogue['ticket_types'][0]['id']);
        self::assertNull($memberCatalogue['ticket_types'][0]['eligibility_policy']);

        $outsiderCatalogue = EventTicketResource::catalogue($queries->read($eventId, $outsider));
        self::assertFalse($outsiderCatalogue['permissions']['allocate_self']);
        self::assertCount(1, $outsiderCatalogue['ticket_types']);

        $allocation = (new EventTicketEntitlementService())->allocateSelf(
            $eventId,
            (int) $free->id,
            $registrationId,
            $member,
            1,
            'ticket-query-self-allocation',
        );
        self::assertTrue($allocation['changed']);
        $after = EventTicketResource::catalogue($queries->read($eventId, $member));
        self::assertCount(1, $after['own_entitlements']);
        self::assertSame('confirmed', $after['own_entitlements'][0]['status']);
        self::assertArrayNotHasKey('registration_id', $after['own_entitlements'][0]);
        self::assertArrayNotHasKey('allocation_idempotency_hash', $after['own_entitlements'][0]);
    }
}

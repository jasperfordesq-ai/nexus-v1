<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Core\TenantContext;
use App\Services\EventTicketTypeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\Feature\Events\Concerns\BuildsEventTicketingFixtures;
use Tests\Laravel\TestCase;

final class EventTicketControllerTest extends TestCase
{
    use DatabaseTransactions;
    use BuildsEventTicketingFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'features' => json_encode(['events' => true], JSON_THROW_ON_ERROR),
        ]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    public function test_member_catalogue_allocation_replay_and_cancellation_use_private_explicit_contracts(): void
    {
        $owner = $this->ticketUser();
        $member = $this->ticketUser();
        [$eventId, $start] = $this->ticketEvent((int) $owner->id);
        $this->ticketRegistration($eventId, (int) $member->id);
        $ticket = $this->activeTicketType($eventId, $owner, $start);
        Sanctum::actingAs($member, ['*']);

        $catalogue = $this->apiGet("/v2/events/{$eventId}/tickets");
        $catalogue->assertOk()
            ->assertJsonPath('data.contract_version', 1)
            ->assertJsonPath('data.permissions.manage', false)
            ->assertJsonPath('data.permissions.allocate_self', true)
            ->assertJsonPath('data.payment_gateway.free_supported', true)
            ->assertJsonPath('data.payment_gateway.time_credit_supported', false)
            ->assertJsonPath('data.ticket_types.0.id', (int) $ticket->id)
            ->assertJsonPath('data.ticket_types.0.availability.eligibility.eligible', true)
            ->assertJsonPath('data.ticket_types.0.eligibility_policy', null)
            ->assertJsonMissingPath('data.ticket_types.0.tenant_id')
            ->assertJsonMissingPath('data.ticket_types.0.create_idempotency_hash');
        self::assertStringContainsString('no-store', (string) $catalogue->headers->get('Cache-Control'));

        $allocated = $this->apiPost(
            "/v2/events/{$eventId}/tickets/{$ticket->id}/allocate",
            ['units' => 1],
            ['Idempotency-Key' => 'ticket-api-member-allocation'],
        );
        $allocated->assertCreated()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.idempotent_replay', false)
            ->assertJsonPath('data.entitlement.ticket_type_id', (int) $ticket->id)
            ->assertJsonPath('data.entitlement.kind', 'free')
            ->assertJsonPath('data.entitlement.status', 'confirmed')
            ->assertJsonMissingPath('data.entitlement.registration_id')
            ->assertJsonMissingPath('data.entitlement.allocation_idempotency_hash');
        $entitlementId = (int) $allocated->json('data.entitlement.id');

        $this->apiPost(
            "/v2/events/{$eventId}/tickets/{$ticket->id}/allocate",
            ['units' => 1],
            ['Idempotency-Key' => 'ticket-api-member-allocation'],
        )->assertOk()
            ->assertJsonPath('data.changed', false)
            ->assertJsonPath('data.idempotent_replay', true)
            ->assertJsonPath('data.entitlement.id', $entitlementId);

        $this->apiPost(
            "/v2/events/{$eventId}/ticket-entitlements/{$entitlementId}/cancel",
            ['expected_version' => 1, 'reason' => 'Plans changed.'],
            ['Idempotency-Key' => 'ticket-api-member-cancel'],
        )->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.entitlement.status', 'cancelled')
            ->assertJsonPath('data.entitlement.version', 2);

        self::assertSame(1, DB::table('event_ticket_entitlements')->where('event_id', $eventId)->count());
        self::assertSame(2, DB::table('event_ticket_entitlement_history')->where('event_id', $eventId)->count());
        self::assertSame(2, DB::table('event_ticket_inventory_history')->where('event_id', $eventId)->count());
    }

    public function test_manager_type_lifecycle_quote_reconciliation_and_validation_fail_closed(): void
    {
        $owner = $this->ticketUser();
        $member = $this->ticketUser();
        [$eventId, $start] = $this->ticketEvent((int) $owner->id);
        $this->ticketRegistration($eventId, (int) $member->id);
        Sanctum::actingAs($owner, ['*']);

        $this->apiPost("/v2/events/{$eventId}/ticket-types", $this->ticketTypePayload($start))
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.field', 'idempotency_key');
        $this->apiPost(
            "/v2/events/{$eventId}/ticket-types",
            array_merge($this->ticketTypePayload($start), ['idempotency_key' => 'body-key']),
            ['Idempotency-Key' => 'different-header-key'],
        )->assertUnprocessable()
            ->assertJsonPath('errors.0.field', 'idempotency_key');

        $created = $this->apiPost(
            "/v2/events/{$eventId}/ticket-types",
            $this->ticketTypePayload($start),
            ['Idempotency-Key' => 'ticket-api-create-type'],
        );
        $created->assertCreated()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.ticket_type.version', 1)
            ->assertJsonPath('data.ticket_type.status', 'draft')
            ->assertJsonMissingPath('data.ticket_type.tenant_id');
        $ticketTypeId = (int) $created->json('data.ticket_type.id');

        $this->apiPut(
            "/v2/events/{$eventId}/ticket-types/{$ticketTypeId}",
            ['expected_version' => 1, 'name' => 'Revised community ticket'],
            ['Idempotency-Key' => 'ticket-api-update-type'],
        )->assertOk()
            ->assertJsonPath('data.ticket_type.name', 'Revised community ticket')
            ->assertJsonPath('data.ticket_type.version', 2);

        $this->apiPost(
            "/v2/events/{$eventId}/ticket-types/{$ticketTypeId}/activate",
            ['expected_version' => 2],
            ['Idempotency-Key' => 'ticket-api-activate-type'],
        )->assertOk()
            ->assertJsonPath('data.ticket_type.status', 'active')
            ->assertJsonPath('data.ticket_type.version', 3);

        Sanctum::actingAs($member, ['*']);
        $this->apiPost(
            "/v2/events/{$eventId}/tickets/{$ticketTypeId}/quote",
            ['units' => 1],
        )->assertOk()
            ->assertJsonPath('data.ticket_type_id', $ticketTypeId)
            ->assertJsonPath('data.eligibility.eligible', true)
            ->assertJsonPath('data.materialization_supported', true)
            ->assertJsonPath('data.attendance_reward_included', false);
        $this->apiPost(
            "/v2/events/{$eventId}/ticket-types",
            $this->ticketTypePayload($start),
            ['Idempotency-Key' => 'ticket-api-member-create-denied'],
        )->assertForbidden()
            ->assertJsonPath('errors.0.code', 'EVENT_TICKET_FORBIDDEN');

        Sanctum::actingAs($owner, ['*']);
        $this->apiGet("/v2/events/{$eventId}/tickets/reconciliation")
            ->assertOk()
            ->assertJsonPath('data.read_only', true)
            ->assertJsonPath('data.ticket_types.0.ticket_type_id', $ticketTypeId)
            ->assertJsonPath('data.ticket_types.0.inventory_mismatch', false);

        $credit = (new EventTicketTypeService())->create(
            $eventId,
            $owner,
            $this->ticketTypePayload($start, [
                'name' => 'Time-credit draft',
                'kind' => 'time_credit',
                'unit_price_credits' => '2.00',
            ]),
            'ticket-api-credit-draft',
        );
        $this->apiPost(
            "/v2/events/{$eventId}/ticket-types/{$credit['ticket_type']->id}/activate",
            ['expected_version' => 1],
            ['Idempotency-Key' => 'ticket-api-credit-activation'],
        )->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'EVENT_TICKET_VALIDATION_FAILED');
        self::assertSame(
            'draft',
            DB::table('event_ticket_types')->where('id', $credit['ticket_type']->id)->value('status'),
        );
    }
}

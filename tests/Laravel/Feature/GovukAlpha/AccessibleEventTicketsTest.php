<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Services\EventTicketTypeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\Feature\Events\Concerns\BuildsEventTicketingFixtures;
use Tests\Laravel\TestCase;

final class AccessibleEventTicketsTest extends TestCase
{
    use BuildsEventTicketingFixtures;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'features' => json_encode(['events' => true], JSON_THROW_ON_ERROR),
        ]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    public function test_html_first_catalogue_allocates_and_cancels_only_free_tickets_without_wallet_effects(): void
    {
        $member = $this->ticketUser();
        [$eventId, $start] = $this->ticketEvent((int) $member->id);
        $this->ticketRegistration($eventId, (int) $member->id);
        $free = $this->activeTicketType($eventId, $member, $start);
        $credit = (new EventTicketTypeService())->create(
            $eventId,
            $member,
            $this->ticketTypePayload($start, [
                'name' => 'Time-credit draft',
                'kind' => 'time_credit',
                'unit_price_credits' => '2.00',
            ]),
            'accessible-ticket-credit-draft',
        );
        $balanceBefore = DB::table('users')->where('id', $member->id)->value('balance');
        Sanctum::actingAs($member, ['*']);
        $base = "/{$this->testTenantSlug}/accessible/events/{$eventId}/tickets";

        $catalogue = $this->get($base);
        $catalogue->assertOk()
            ->assertSeeText('Community ticket')
            ->assertSeeText('Time-credit draft')
            ->assertSeeText(__('event_tickets.gateway_disabled'))
            ->assertSeeText(__('event_tickets.time_credit_disabled', ['credits' => '2.00']));
        self::assertStringContainsString('no-store', (string) $catalogue->headers->get('Cache-Control'));

        $this->accessiblePost("{$base}/" . (int) $free->id . '/allocate', [
            'units' => '2',
            'idempotency_key' => 'accessible-free-ticket-allocate',
        ])->assertRedirect("{$base}?status=allocated");
        $entitlement = DB::table('event_ticket_entitlements')
            ->where('event_id', $eventId)
            ->where('ticket_type_id', (int) $free->id)
            ->first();
        self::assertNotNull($entitlement);
        self::assertSame('free', (string) $entitlement->ticket_kind_snapshot);
        self::assertSame('0.00', (string) $entitlement->total_price_credits_snapshot);
        self::assertSame(
            (string) $balanceBefore,
            (string) DB::table('users')->where('id', $member->id)->value('balance'),
        );

        $cancelPath = "{$base}/entitlements/{$entitlement->id}/cancel";
        $this->get($cancelPath)
            ->assertOk()
            ->assertSeeText(__('event_tickets.cancel_title'))
            ->assertSeeText(__('event_tickets.cancel_free_only'));
        $this->accessiblePost($cancelPath, [
            'expected_version' => '1',
            'reason' => 'Plans changed.',
            'idempotency_key' => 'accessible-free-ticket-cancel',
        ])->assertRedirect("{$base}?status=cancelled");
        self::assertSame('cancelled', DB::table('event_ticket_entitlements')
            ->where('id', $entitlement->id)
            ->value('status'));
        self::assertSame(2, (int) DB::table('event_ticket_entitlements')
            ->where('id', $entitlement->id)
            ->value('entitlement_version'));

        $this->accessiblePost(
            "{$base}/" . (int) $credit['ticket_type']->id . '/allocate',
            ['units' => '1', 'idempotency_key' => 'accessible-credit-ticket-denied'],
        )->assertRedirect($base);
        self::assertSame(0, DB::table('event_ticket_entitlements')
            ->where('event_id', $eventId)
            ->where('ticket_type_id', (int) $credit['ticket_type']->id)
            ->count());
        self::assertSame(
            (string) $balanceBefore,
            (string) DB::table('users')->where('id', $member->id)->value('balance'),
        );
    }

    public function test_unregistered_member_sees_catalogue_but_cannot_self_allocate(): void
    {
        $owner = $this->ticketUser();
        $member = $this->ticketUser();
        [$eventId, $start] = $this->ticketEvent((int) $owner->id);
        $free = $this->activeTicketType($eventId, $owner, $start);
        Sanctum::actingAs($member, ['*']);
        $base = "/{$this->testTenantSlug}/accessible/events/{$eventId}/tickets";

        $this->get($base)
            ->assertOk()
            ->assertSeeText(__('event_tickets.registration_required'));
        $this->accessiblePost("{$base}/" . (int) $free->id . '/allocate', [
            'units' => '1',
            'idempotency_key' => 'accessible-unregistered-ticket-denied',
        ])->assertRedirect($base);
        self::assertSame(0, DB::table('event_ticket_entitlements')
            ->where('event_id', $eventId)
            ->count());
    }

    public function test_eligibility_denial_returns_to_the_catalogue_as_validation_not_forbidden(): void
    {
        $owner = $this->ticketUser();
        $member = $this->ticketUser(['created_at' => now()]);
        [$eventId, $start] = $this->ticketEvent((int) $owner->id);
        $this->ticketRegistration($eventId, (int) $member->id);
        $free = $this->activeTicketType($eventId, $owner, $start);
        Sanctum::actingAs($member, ['*']);
        $base = "/{$this->testTenantSlug}/accessible/events/{$eventId}/tickets";

        $response = $this->accessiblePost("{$base}/" . (int) $free->id . '/allocate', [
            'units' => '1',
            'idempotency_key' => 'accessible-eligibility-denied',
        ]);

        $response->assertRedirect($base)
            ->assertSessionHasErrors('ticket');
        self::assertSame(0, DB::table('event_ticket_entitlements')
            ->where('event_id', $eventId)
            ->where('user_id', (int) $member->id)
            ->count());
    }

    /** @param array<string,mixed> $data */
    private function accessiblePost(string $uri, array $data): TestResponse
    {
        $token = 'accessible-event-ticket-token';
        $this->withSession(['_token' => $token]);

        return $this->post($uri, ['_token' => $token, ...$data]);
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Events;

use App\Models\User;
use App\Services\GamificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

class EventGamificationIdempotencyTest extends TestCase
{
    use DatabaseTransactions;

    private function activeUser(): User
    {
        return User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
            'xp' => 0,
        ]);
    }

    private function eventOwnedBy(int $organizerId): int
    {
        return (int) DB::table('events')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $organizerId,
            'title' => 'RSVP XP claim event',
            'description' => 'A future event for reward idempotency coverage.',
            'start_time' => now()->addDays(7),
            'end_time' => now()->addDays(7)->addHours(2),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assertSingleRsvpMilestone(User $member, int $eventId): void
    {
        $reference = 'event:' . $eventId;
        $this->assertSame(1, DB::table('user_xp_log')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $member->id)
            ->where('action', 'attend_event')
            ->where('source_reference', $reference)
            ->count());
        $this->assertSame(
            GamificationService::XP_VALUES['attend_event'],
            (int) DB::table('users')->where('id', $member->id)->value('xp')
        );
    }

    public function test_duplicate_going_rsvp_awards_one_database_claim(): void
    {
        $organizer = $this->activeUser();
        $member = $this->activeUser();
        $eventId = $this->eventOwnedBy((int) $organizer->id);
        Sanctum::actingAs($member, ['*']);

        $this->apiPost("/v2/events/{$eventId}/rsvp", ['status' => 'going'])->assertOk();
        $this->apiPost("/v2/events/{$eventId}/rsvp", ['status' => 'going'])->assertOk();

        $this->assertSingleRsvpMilestone($member, $eventId);
    }

    public function test_status_cycle_and_rsvp_recreation_cannot_reaward_milestone(): void
    {
        $organizer = $this->activeUser();
        $member = $this->activeUser();
        $eventId = $this->eventOwnedBy((int) $organizer->id);
        Sanctum::actingAs($member, ['*']);

        $this->apiPost("/v2/events/{$eventId}/rsvp", ['status' => 'going'])->assertOk();
        $this->apiPost("/v2/events/{$eventId}/rsvp", ['status' => 'interested'])->assertOk();
        $this->apiPost("/v2/events/{$eventId}/rsvp", ['status' => 'going'])->assertOk();
        $this->apiDelete("/v2/events/{$eventId}/rsvp")->assertNoContent();
        $this->apiPost("/v2/events/{$eventId}/rsvp", ['status' => 'going'])->assertOk();

        $this->assertSingleRsvpMilestone($member, $eventId);
    }

    public function test_draft_event_creation_does_not_award_publication_xp(): void
    {
        $organizer = $this->activeUser();
        Sanctum::actingAs($organizer, ['*']);

        $response = $this->apiPost('/v2/events', [
            'title' => 'Creation XP reference event',
            'description' => 'Creation reward must be tied to the concrete event.',
            'location' => 'Community Hall',
            'start_time' => now()->addDays(14)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDays(14)->addHours(2)->format('Y-m-d H:i:s'),
        ]);
        $this->assertContains($response->getStatusCode(), [200, 201]);

        $eventId = (int) DB::table('events')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $organizer->id)
            ->where('title', 'Creation XP reference event')
            ->value('id');

        $this->assertGreaterThan(0, $eventId);
        $this->assertSame(0, DB::table('user_xp_log')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $organizer->id)
            ->where('action', 'create_event')
            ->where('source_reference', 'event:' . $eventId)
            ->count());
    }
}

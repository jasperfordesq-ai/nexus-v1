<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

final class AccessibleEventVenueAccessibilityTest extends TestCase
{
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

    public function test_accessible_create_edit_and_detail_preserve_tri_state_public_venue_facts(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        Sanctum::actingAs($owner, ['*']);
        $base = "/{$this->testTenantSlug}/accessible";

        $this->get("{$base}/events/new")
            ->assertOk()
            ->assertSeeText(__('event_accessibility.form.title'))
            ->assertSeeText(__('event_accessibility.features.step_free_access'))
            ->assertSee('name="accessibility_step_free"', false)
            ->assertSeeText(__('event_accessibility.form.privacy_note'));

        $start = CarbonImmutable::now('UTC')->addMonths(3)->startOfHour();
        $this->accessiblePost("{$base}/events/new", [
            'title' => 'Accessible venue profile fixture',
            'description' => 'A complete HTML-first venue accessibility fixture.',
            'start_time' => $start->format('Y-m-d\TH:i'),
            'end_time' => $start->addHours(2)->format('Y-m-d\TH:i'),
            'location' => 'Accessible Community Hall',
            'accessibility_step_free' => 'yes',
            'accessibility_toilet' => 'no',
            'accessibility_hearing_loop' => 'unknown',
            'accessibility_quiet_space' => 'yes',
            'accessibility_seating' => 'yes',
            'accessibility_parking' => 'unknown',
            'accessibility_parking_details' => 'Two marked bays beside the east entrance.',
            'accessibility_transit_details' => 'Level route from the bus stop.',
            'accessibility_assistance_contact' => 'Message the event team.',
            'accessibility_notes' => 'Use the east entrance.',
        ])->assertRedirect();

        $eventId = (int) DB::table('events')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', (int) $owner->id)
            ->where('title', 'Accessible venue profile fixture')
            ->value('id');
        self::assertGreaterThan(0, $eventId);
        $row = DB::table('events')->where('id', $eventId)->firstOrFail();
        self::assertSame(1, (int) $row->accessibility_step_free);
        self::assertSame(0, (int) $row->accessibility_toilet);
        self::assertNull($row->accessibility_hearing_loop);
        self::assertSame('Use the east entrance.', $row->accessibility_notes);

        $this->get("{$base}/events/{$eventId}")
            ->assertOk()
            ->assertSeeText(__('event_accessibility.detail.title'))
            ->assertSeeText(__('event_accessibility.status.yes'))
            ->assertSeeText(__('event_accessibility.status.no'))
            ->assertSeeText(__('event_accessibility.status.unknown'))
            ->assertSeeText('Two marked bays beside the east entrance.')
            ->assertDontSeeText('private accommodation');

        $edit = $this->get("{$base}/events/{$eventId}/edit");
        $edit->assertOk()
            ->assertSee('name="accessibility_step_free"', false)
            ->assertSee('value="yes" selected', false)
            ->assertSee('Use the east entrance.');

        $this->accessiblePost("{$base}/events/{$eventId}/edit", [
            'title' => 'Accessible venue profile fixture',
            'description' => 'A complete HTML-first venue accessibility fixture.',
            'start_time' => $start->format('Y-m-d\TH:i'),
            'end_time' => $start->addHours(2)->format('Y-m-d\TH:i'),
            'location' => 'Accessible Community Hall',
            'accessibility_step_free' => 'no',
            'accessibility_toilet' => 'yes',
            'accessibility_hearing_loop' => 'unknown',
            'accessibility_quiet_space' => 'unknown',
            'accessibility_seating' => 'yes',
            'accessibility_parking' => 'no',
            'accessibility_notes' => 'Portable ramp required.',
        ])->assertRedirect("{$base}/events/{$eventId}?status=event-updated");

        $updated = DB::table('events')->where('id', $eventId)->firstOrFail();
        self::assertSame(0, (int) $updated->accessibility_step_free);
        self::assertSame(1, (int) $updated->accessibility_toilet);
        self::assertNull($updated->accessibility_quiet_space);
        self::assertNull($updated->accessibility_parking_details);
        self::assertSame('Portable ramp required.', $updated->accessibility_notes);
    }

    /** @param array<string,mixed> $data */
    private function accessiblePost(string $uri, array $data): TestResponse
    {
        $token = 'accessible-event-venue-accessibility-token';

        return $this->withSession(['_token' => $token])->post($uri, [
            ...$data,
            '_token' => $token,
        ]);
    }
}

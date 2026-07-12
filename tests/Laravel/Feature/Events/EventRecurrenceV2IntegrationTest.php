<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Models\User;
use App\Services\EventRecurrenceService;
use App\Services\EventService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

final class EventRecurrenceV2IntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('events.recurrence.engine_v2_enabled', true);
        config()->set('events.recurrence.max_occurrences', 366);
        config()->set('events.recurrence.max_horizon_years', 20);
    }

    public function test_flagged_create_is_atomic_and_materializes_count_including_dtstart(): void
    {
        $organizer = $this->activeUser();
        Sanctum::actingAs($organizer, ['*']);

        $response = $this->apiPost('/v2/events/recurring', $this->payload([
            'title' => 'V2 count-ten series',
            'recurrence_frequency' => 'daily',
            'recurrence_ends_type' => 'after_count',
            'recurrence_ends_after_count' => 10,
        ]))->assertCreated();

        $templateId = (int) $response->json('data.template.id');
        $this->assertSame(10, (int) $response->json('data.occurrences_created'));
        $template = DB::table('events')->where('id', $templateId)->first();
        $rule = DB::table('event_recurrence_rules')->where('event_id', $templateId)->first();
        $occurrences = DB::table('events')
            ->where('tenant_id', $this->testTenantId)
            ->where('parent_event_id', $templateId)
            ->orderBy('start_time')
            ->get();

        $this->assertNotNull($template);
        $this->assertNotNull($rule);
        $this->assertNull($template->occurrence_key);
        $this->assertSame(EventRecurrenceService::ENGINE, $template->recurrence_engine);
        $this->assertSame(EventRecurrenceService::ENGINE_VERSION, $template->recurrence_engine_version);
        $this->assertSame('FREQ=DAILY;INTERVAL=1;COUNT=10', $rule->rrule);
        $this->assertSame(EventRecurrenceService::ENGINE, $rule->recurrence_engine);
        $this->assertSame(EventRecurrenceService::ENGINE_VERSION, $rule->recurrence_engine_version);
        $this->assertSame(64, strlen((string) $rule->rule_hash));
        $this->assertCount(10, $occurrences);
        $this->assertSame($template->start_time, $occurrences->first()->start_time);
        $this->assertSame('2027-06-15', $occurrences->first()->occurrence_date);
        $this->assertSame('2027-06-24', $occurrences->last()->occurrence_date);

        foreach ($occurrences as $occurrence) {
            $this->assertSame('draft', $occurrence->status);
            $this->assertSame('draft', $occurrence->publication_status);
            $this->assertSame(EventRecurrenceService::ENGINE, $occurrence->recurrence_engine);
            $this->assertSame(EventRecurrenceService::ENGINE_VERSION, $occurrence->recurrence_engine_version);
            $this->assertStringStartsWith(
                "recurrence:{$this->testTenantId}:{$templateId}:",
                (string) $occurrence->occurrence_key,
            );
        }
    }

    public function test_exclusions_additions_and_last_day_rule_persist_losslessly(): void
    {
        $organizer = $this->activeUser();
        Sanctum::actingAs($organizer, ['*']);

        $response = $this->apiPost('/v2/events/recurring', $this->payload([
            'title' => 'V2 month-end exceptions',
            'start_time' => '2027-01-31 09:00:00',
            'end_time' => '2027-01-31 10:00:00',
            'timezone' => 'UTC',
            'recurrence_rrule' => 'FREQ=MONTHLY;BYMONTHDAY=-1;COUNT=3',
            'recurrence_exdates' => ['2027-02-28 09:00:00'],
            'recurrence_additions' => ['2027-02-27 09:00:00'],
            'recurrence_frequency' => 'custom',
        ]))->assertCreated();

        $templateId = (int) $response->json('data.template.id');
        $rule = DB::table('event_recurrence_rules')->where('event_id', $templateId)->first();
        $dates = DB::table('events')
            ->where('tenant_id', $this->testTenantId)
            ->where('parent_event_id', $templateId)
            ->orderBy('start_time')
            ->pluck('occurrence_date')
            ->all();

        $this->assertSame('FREQ=MONTHLY;BYMONTHDAY=-1;COUNT=3', $rule->rrule);
        $this->assertNull($rule->day_of_month);
        $this->assertSame(['20270228T090000Z'], json_decode($rule->exdates, true, 512, JSON_THROW_ON_ERROR));
        $this->assertSame(['20270227T090000Z'], json_decode($rule->rdates, true, 512, JSON_THROW_ON_ERROR));
        $this->assertSame(['2027-01-31', '2027-02-27', '2027-03-31'], $dates);
    }

    public function test_regeneration_is_authorized_and_idempotent(): void
    {
        $organizer = $this->activeUser();
        $other = $this->activeUser();
        Sanctum::actingAs($organizer, ['*']);
        $created = $this->apiPost('/v2/events/recurring', $this->payload([
            'title' => 'V2 regeneration target',
            'recurrence_frequency' => 'weekly',
            'recurrence_days' => '2,4',
            'recurrence_ends_type' => 'after_count',
            'recurrence_ends_after_count' => 4,
        ]))->assertCreated();
        $templateId = (int) $created->json('data.template.id');
        $before = DB::table('events')->where('parent_event_id', $templateId)->pluck('occurrence_key')->all();

        $this->assertSame(0, EventService::regenerateRecurring($templateId, (int) $organizer->id));
        $after = DB::table('events')->where('parent_event_id', $templateId)->pluck('occurrence_key')->all();
        $this->assertSame($before, $after);

        $this->assertNull(EventService::regenerateRecurring($templateId, (int) $other->id));
        $this->assertSame('FORBIDDEN', EventService::getErrors()[0]['code']);
        $this->assertSame($before, DB::table('events')->where('parent_event_id', $templateId)->pluck('occurrence_key')->all());
    }

    public function test_template_is_not_registrable_but_concrete_occurrence_is(): void
    {
        $organizer = $this->activeUser();
        $attendee = $this->activeUser();
        Sanctum::actingAs($organizer, ['*']);
        $created = $this->apiPost('/v2/events/recurring', $this->payload([
            'title' => 'V2 concrete registration target',
            'recurrence_frequency' => 'weekly',
            'recurrence_ends_type' => 'after_count',
            'recurrence_ends_after_count' => 2,
        ]))->assertCreated();
        $templateId = (int) $created->json('data.template.id');
        $occurrenceId = (int) DB::table('events')
            ->where('parent_event_id', $templateId)
            ->orderBy('start_time')
            ->value('id');
        DB::table('events')->where('id', $occurrenceId)->update([
            'status' => 'active',
            'publication_status' => 'published',
        ]);

        Sanctum::actingAs($attendee, ['*']);
        $this->apiPost("/v2/events/{$templateId}/rsvp", ['status' => 'going'])
            ->assertNotFound();
        $this->apiPost("/v2/events/{$occurrenceId}/rsvp", ['status' => 'going'])
            ->assertOk();
        $this->assertDatabaseHas('event_rsvps', [
            'tenant_id' => $this->testTenantId,
            'event_id' => $occurrenceId,
            'user_id' => $attendee->id,
            'status' => 'going',
        ]);
    }

    public function test_unsupported_rule_rolls_back_template_rule_and_occurrences(): void
    {
        $organizer = $this->activeUser();
        Sanctum::actingAs($organizer, ['*']);
        $title = 'V2 rollback unsupported rule';

        $this->apiPost('/v2/events/recurring', $this->payload([
            'title' => $title,
            'recurrence_frequency' => 'custom',
            'recurrence_rrule' => 'FREQ=HOURLY;COUNT=10',
        ]))->assertUnprocessable();

        $this->assertSame(0, DB::table('events')
            ->where('tenant_id', $this->testTenantId)
            ->where('title', $title)
            ->count());
        $this->assertSame(0, DB::table('event_recurrence_rules')
            ->where('tenant_id', $this->testTenantId)
            ->where('recurrence_engine', EventRecurrenceService::ENGINE)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('events')
                    ->whereColumn('events.id', 'event_recurrence_rules.event_id');
            })
            ->count());
    }

    public function test_rollout_flag_off_preserves_legacy_engine_and_count_semantics(): void
    {
        config()->set('events.recurrence.engine_v2_enabled', false);
        $organizer = $this->activeUser();
        Sanctum::actingAs($organizer, ['*']);

        $created = $this->apiPost('/v2/events/recurring', $this->payload([
            'title' => 'Legacy recurrence remains authoritative',
            'recurrence_frequency' => 'weekly',
            'recurrence_ends_type' => 'after_count',
            'recurrence_ends_after_count' => 2,
        ]))->assertCreated();
        $templateId = (int) $created->json('data.template.id');

        $this->assertSame('legacy', DB::table('events')->where('id', $templateId)->value('recurrence_engine'));
        $this->assertSame('1', DB::table('events')->where('id', $templateId)->value('recurrence_engine_version'));
        $this->assertSame(2, DB::table('events')->where('parent_event_id', $templateId)->count());
    }

    private function activeUser(): User
    {
        return User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
    }

    /** @return array<string,mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'V2 recurrence fixture',
            'description' => 'Enterprise recurrence integration fixture.',
            'start_time' => '2027-06-15 09:00:00',
            'end_time' => '2027-06-15 10:00:00',
            'timezone' => 'Europe/Dublin',
            'all_day' => false,
            'location' => 'Community hall',
            'is_online' => false,
            'allow_remote_attendance' => false,
            'federated_visibility' => 'none',
        ], $overrides);
    }
}

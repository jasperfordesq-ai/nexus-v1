<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use App\Services\EventRecurrenceService;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Validation\ValidationException;
use Tests\Laravel\TestCase;

final class EventRecurrenceServiceTest extends TestCase
{
    private EventRecurrenceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('events.recurrence.max_occurrences', 366);
        config()->set('events.recurrence.max_horizon_years', 20);
        $this->service = app(EventRecurrenceService::class);
    }

    public function test_canonicalizes_biweekly_multiple_weekdays_and_count_includes_dtstart(): void
    {
        $start = $this->utcFromLocal('2027-01-04 09:00:00', 'Europe/Dublin');
        $definition = $this->service->normalize([
            'recurrence_rrule' => 'rrule:byday=WE,MO;count=5;interval=2;freq=weekly',
        ], $start, 'Europe/Dublin');

        $this->assertSame('FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,WE;COUNT=5', $definition['rrule']);
        $occurrences = $this->service->expand(
            $start,
            $start->modify('+1 hour'),
            'Europe/Dublin',
            $definition['rrule'],
        );

        $this->assertSame([
            '2027-01-04',
            '2027-01-06',
            '2027-01-18',
            '2027-01-20',
            '2027-02-01',
        ], array_column($occurrences, 'occurrence_date'));
        $this->assertCount(5, $occurrences);
        $this->assertSame('2027-01-04T09:00:00+00:00', $occurrences[0]['local_start']);
    }

    public function test_count_ten_produces_exactly_ten_concrete_instants(): void
    {
        $start = $this->utcFromLocal('2027-06-15 09:00:00', 'Europe/Dublin');
        $occurrences = $this->service->expand(
            $start,
            $start->modify('+90 minutes'),
            'Europe/Dublin',
            'FREQ=DAILY;COUNT=10',
        );

        $this->assertCount(10, $occurrences);
        $this->assertSame('2027-06-15', $occurrences[0]['occurrence_date']);
        $this->assertSame('2027-06-24', $occurrences[9]['occurrence_date']);
    }

    public function test_dublin_gap_and_fold_are_deterministic_and_restore_wall_time(): void
    {
        $gapStart = $this->utcFromLocal('2027-03-21 01:30:00', 'Europe/Dublin');
        $gap = $this->service->expand(
            $gapStart,
            $gapStart->modify('+1 hour'),
            'Europe/Dublin',
            'FREQ=WEEKLY;COUNT=3',
        );

        $this->assertSame('2027-03-21T01:30:00+00:00', $gap[0]['local_start']);
        $this->assertSame('2027-03-28T02:30:00+01:00', $gap[1]['local_start']);
        $this->assertSame('2027-04-04T01:30:00+01:00', $gap[2]['local_start']);

        $foldStart = $this->utcFromLocal('2027-10-24 01:30:00', 'Europe/Dublin');
        $first = $this->service->expand(
            $foldStart,
            $foldStart->modify('+1 hour'),
            'Europe/Dublin',
            'FREQ=WEEKLY;COUNT=3',
        );
        $second = $this->service->expand(
            $foldStart,
            $foldStart->modify('+1 hour'),
            'Europe/Dublin',
            'FREQ=WEEKLY;COUNT=3',
        );

        $this->assertSame($first, $second);
        $this->assertStringStartsWith('2027-10-31T01:30:00', $first[1]['local_start']);
        $this->assertSame('01:30:00', substr($first[2]['local_start'], 11, 8));
    }

    public function test_wall_time_survives_global_timezone_boundaries(): void
    {
        $cases = [
            ['Australia/Sydney', '2027-01-15 09:15:00', '+11:00'],
            ['Africa/Abidjan', '2027-01-15 09:15:00', '+00:00'],
            ['Asia/Kathmandu', '2027-01-15 09:15:00', '+05:45'],
            ['Australia/Eucla', '2027-01-15 09:15:00', '+08:45'],
            ['Pacific/Kiritimati', '2027-01-15 09:15:00', '+14:00'],
            ['Pacific/Pago_Pago', '2027-01-15 09:15:00', '-11:00'],
        ];

        foreach ($cases as [$timezone, $local, $offset]) {
            $start = $this->utcFromLocal($local, $timezone);
            $occurrences = $this->service->expand(
                $start,
                $start->modify('+1 hour'),
                $timezone,
                'FREQ=DAILY;COUNT=3',
            );

            $this->assertCount(3, $occurrences, $timezone);
            foreach ($occurrences as $occurrence) {
                $this->assertSame('09:15:00', substr($occurrence['local_start'], 11, 8), $timezone);
                $this->assertStringEndsWith($offset, $occurrence['local_start'], $timezone);
            }
        }
    }

    public function test_southern_hemisphere_dst_gap_recovers_original_wall_time(): void
    {
        $start = $this->utcFromLocal('2027-09-26 02:30:00', 'Australia/Sydney');
        $occurrences = $this->service->expand(
            $start,
            $start->modify('+1 hour'),
            'Australia/Sydney',
            'FREQ=WEEKLY;COUNT=3',
        );

        $this->assertSame('2027-09-26T02:30:00+10:00', $occurrences[0]['local_start']);
        $this->assertSame('2027-10-03T03:30:00+11:00', $occurrences[1]['local_start']);
        $this->assertSame('2027-10-10T02:30:00+11:00', $occurrences[2]['local_start']);
    }

    public function test_month_end_and_yearly_leap_rules_do_not_drift(): void
    {
        $monthStart = $this->utcFromLocal('2027-01-31 18:00:00', 'UTC');
        $monthEnds = $this->service->expand(
            $monthStart,
            null,
            'UTC',
            'FREQ=MONTHLY;BYMONTHDAY=-1;COUNT=4',
        );
        $this->assertSame([
            '2027-01-31',
            '2027-02-28',
            '2027-03-31',
            '2027-04-30',
        ], array_column($monthEnds, 'occurrence_date'));

        $leapStart = $this->utcFromLocal('2028-02-29 09:00:00', 'UTC');
        $leapDays = $this->service->expand(
            $leapStart,
            null,
            'UTC',
            'FREQ=YEARLY;BYMONTHDAY=29;BYMONTH=2;COUNT=3',
        );
        $this->assertSame([
            '2028-02-29',
            '2032-02-29',
            '2036-02-29',
        ], array_column($leapDays, 'occurrence_date'));
    }

    public function test_exdates_and_rdate_additions_are_merged_and_deduplicated(): void
    {
        $start = $this->utcFromLocal('2027-01-01 09:00:00', 'UTC');
        $definition = $this->service->normalize([
            'recurrence_rrule' => 'FREQ=DAILY;COUNT=3',
            'recurrence_exdates' => ['2027-01-02 09:00:00'],
            'recurrence_additions' => [
                '2027-01-03 09:00:00',
                '2027-01-10 09:00:00',
            ],
        ], $start, 'UTC');
        $occurrences = $this->service->expand(
            $start,
            $start->modify('+1 hour'),
            'UTC',
            $definition['rrule'],
            $definition['exdates'],
            $definition['rdates'],
        );

        $this->assertSame([
            '2027-01-01',
            '2027-01-03',
            '2027-01-10',
        ], array_column($occurrences, 'occurrence_date'));
    }

    public function test_until_is_inclusive_and_bounded_in_event_timezone(): void
    {
        $start = $this->utcFromLocal('2027-06-01 09:00:00', 'Europe/Dublin');
        $definition = $this->service->normalize([
            'recurrence_frequency' => 'daily',
            'recurrence_ends_type' => 'on_date',
            'recurrence_ends_on_date' => '2027-06-03',
        ], $start, 'Europe/Dublin');
        $occurrences = $this->service->expand(
            $start,
            null,
            'Europe/Dublin',
            $definition['rrule'],
        );

        $this->assertSame('on_date', $definition['ends_type']);
        $this->assertSame('2027-06-03', $definition['ends_on_date']);
        $this->assertCount(3, $occurrences);
    }

    public function test_unknown_or_silently_unsupported_rrule_parts_fail_closed(): void
    {
        $start = $this->utcFromLocal('2027-01-01 09:00:00', 'UTC');
        $invalid = [
            'FREQ=HOURLY;COUNT=2',
            'FREQ=DAILY;BYHOUR=9;COUNT=2',
            'FREQ=YEARLY;BYMONTHDAY=29;COUNT=2',
            'FREQ=WEEKLY;COUNT=2;COUNT=3',
            'FREQ=DAILY;COUNT=2;UNTIL=20270110T090000Z',
        ];

        foreach ($invalid as $rrule) {
            try {
                $this->service->normalize(['recurrence_rrule' => $rrule], $start, 'UTC');
                $this->fail("RRULE should have failed closed: {$rrule}");
            } catch (ValidationException $e) {
                $this->assertNotSame([], $e->errors());
            }
        }
    }

    public function test_occurrence_identity_is_stable_and_engine_versioned(): void
    {
        $first = $this->service->occurrenceKey(2, 50, '20270101T090000Z');
        $second = $this->service->occurrenceKey(2, 50, '20270101T090000Z');
        $other = $this->service->occurrenceKey(2, 50, '20270102T090000Z');

        $this->assertSame($first, $second);
        $this->assertNotSame($first, $other);
        $this->assertStringStartsWith('recurrence:2:50:', $first);
        $this->assertLessThanOrEqual(191, strlen($first));
        $this->assertSame('sabre-vobject', EventRecurrenceService::ENGINE);
        $this->assertSame('2', EventRecurrenceService::ENGINE_VERSION);
    }

    private function utcFromLocal(string $value, string $timezone): DateTimeImmutable
    {
        return (new DateTimeImmutable($value, new DateTimeZone($timezone)))
            ->setTimezone(new DateTimeZone('UTC'));
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Helpers;

use App\Helpers\IcsHelper;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;

final class IcsEnterpriseContractTest extends TestCase
{
    public function test_stable_uid_sequence_dtstamp_timezone_and_parser_contract(): void
    {
        $options = [
            'timezone' => 'Europe/Dublin',
            'tenant_id' => 2,
            'event_id' => 42,
            'occurrence_key' => 'event:2:42',
            'sequence' => 7,
            'updated_at' => '2027-05-01T12:00:00Z',
        ];
        $first = IcsHelper::generateIcs(
            'Enterprise calendar event',
            'Public description',
            '2027-06-15 09:00:00',
            '2027-06-15 10:00:00',
            $options,
        );
        $second = IcsHelper::generateIcs(
            'Enterprise calendar event',
            'Public description',
            '2027-06-15 09:00:00',
            '2027-06-15 10:00:00',
            $options,
        );

        $firstCalendar = $this->calendar($first);
        $secondCalendar = $this->calendar($second);
        $event = $firstCalendar->VEVENT;

        $this->assertSame((string) $event->UID, (string) $secondCalendar->VEVENT->UID);
        $this->assertStringEndsWith('@events.project-nexus.ie', (string) $event->UID);
        $this->assertSame('7', (string) $event->SEQUENCE);
        $this->assertSame('20270501T120000Z', (string) $event->DTSTAMP);
        $this->assertSame('Europe/Dublin', (string) $event->DTSTART['TZID']);
        $this->assertSame('20270615T090000', (string) $event->DTSTART);
        $this->assertSame('CONFIRMED', (string) $event->STATUS);
        $this->assertCount(1, $firstCalendar->select('VTIMEZONE'));
        $this->assertSame([], $firstCalendar->validate());
    }

    public function test_cancelled_lifecycle_and_recurrence_exceptions_round_trip(): void
    {
        $ics = IcsHelper::generateIcs(
            'Recurring session',
            'Description',
            '2027-01-01 09:00:00',
            '2027-01-01 10:00:00',
            [
                'timezone' => 'UTC',
                'event_id' => 10,
                'rrule' => 'FREQ=WEEKLY;COUNT=3',
                'exdates' => ['2027-01-08 09:00:00'],
                'rdates' => ['2027-01-10 09:00:00'],
                'exceptions' => [[
                    'recurrence_id' => '2027-01-15 09:00:00',
                    'start' => '2027-01-15 09:00:00',
                    'end' => '2027-01-15 10:00:00',
                    'operational_status' => 'cancelled',
                    'sequence' => 3,
                ]],
            ],
        );
        $calendar = $this->calendar($ics);
        $events = $calendar->select('VEVENT');

        $this->assertCount(2, $events);
        $this->assertSame('FREQ=WEEKLY;COUNT=3', (string) $events[0]->RRULE);
        $this->assertSame('20270108T090000Z', (string) $events[0]->EXDATE);
        $this->assertSame('20270110T090000Z', (string) $events[0]->RDATE);
        $this->assertSame((string) $events[0]->UID, (string) $events[1]->UID);
        $this->assertSame('20270115T090000Z', (string) $events[1]->{'RECURRENCE-ID'});
        $this->assertSame('CANCELLED', (string) $events[1]->STATUS);
        $this->assertSame('3', (string) $events[1]->SEQUENCE);
    }

    public function test_restricted_location_and_online_access_are_absent_by_default(): void
    {
        $default = $this->calendar(IcsHelper::generateIcs(
            'Private hybrid event',
            'Safe public description',
            '2027-01-01 09:00:00',
            '2027-01-01 10:00:00',
            [
                'timezone' => 'UTC',
                'location' => 'Restricted shelter room',
                'online_link' => 'https://meet.example.test/secret',
            ],
        ));

        $this->assertFalse(isset($default->VEVENT->LOCATION));
        $this->assertFalse(isset($default->VEVENT->URL));
        $this->assertStringNotContainsString('Restricted shelter room', $default->serialize());
        $this->assertStringNotContainsString('meet.example.test', $default->serialize());

        $authorised = $this->calendar(IcsHelper::generateIcs(
            'Authorised hybrid event',
            'Safe public description',
            '2027-01-01 09:00:00',
            '2027-01-01 10:00:00',
            [
                'timezone' => 'UTC',
                'location' => 'Community hall',
                'include_location' => true,
                'online_link' => 'https://meet.example.test/authorised',
                'include_online_access' => true,
            ],
        ));
        $this->assertSame('Community hall', (string) $authorised->VEVENT->LOCATION);
        $this->assertSame('https://meet.example.test/authorised', (string) $authorised->VEVENT->URL);
    }

    public function test_all_day_dates_remain_dates_across_dst(): void
    {
        $calendar = $this->calendar(IcsHelper::generateIcs(
            'All-day gathering',
            'Description',
            '2027-03-28',
            '2027-03-29',
            [
                'timezone' => 'Europe/Dublin',
                'all_day' => true,
            ],
        ));

        $this->assertSame('DATE', (string) $calendar->VEVENT->DTSTART['VALUE']);
        $this->assertSame('20270328', (string) $calendar->VEVENT->DTSTART);
        $this->assertSame('20270329', (string) $calendar->VEVENT->DTEND);
        $this->assertSame('', (string) $calendar->VEVENT->DTSTART['TZID']);
    }

    private function calendar(string $ics): VCalendar
    {
        $calendar = Reader::read($ics);
        $this->assertInstanceOf(VCalendar::class, $calendar);

        return $calendar;
    }
}

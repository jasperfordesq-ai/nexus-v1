<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Helpers;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Container\Container;
use Sabre\VObject\Component;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;

final class IcsHelper
{
    private const PRODID = '-//Nexus//Timebank Platform//EN';

    /**
     * Backward-compatible positional API.
     *
     * Legacy callers explicitly supplied a location, so this wrapper retains
     * it. New exports should use generateIcs(), which excludes location and
     * online-access details unless their inclusion is explicitly authorised.
     */
    public static function generate(
        mixed $summary,
        mixed $description,
        mixed $location,
        mixed $start,
        mixed $end,
    ): string {
        return self::generateIcs(
            (string) $summary,
            (string) $description,
            $start,
            $end,
            [
                'location' => (string) $location,
                'include_location' => true,
                'timezone' => 'UTC',
                'uid_domain' => 'nexus-timebank',
            ],
        );
    }

    /**
     * Generate an RFC 5545 calendar using sabre/vobject.
     *
     * @param DateTimeInterface|string $start
     * @param DateTimeInterface|string|null $end
     * @param array{
     *   timezone?:string,
     *   all_day?:bool,
     *   uid?:string,
     *   uid_domain?:string,
     *   uid_seed?:string,
     *   tenant_id?:int|string,
     *   event_id?:int|string,
     *   occurrence_key?:string,
     *   dtstamp?:DateTimeInterface|string,
     *   updated_at?:DateTimeInterface|string,
     *   sequence?:int,
     *   status?:string,
     *   lifecycle_status?:string,
     *   operational_status?:string,
     *   location?:string|null,
     *   include_location?:bool,
     *   online_link?:string|null,
     *   include_online_access?:bool,
     *   public_url?:string|null,
     *   include_public_url?:bool,
     *   rrule?:string|null,
     *   exdates?:array<int,DateTimeInterface|string>,
     *   rdates?:array<int,DateTimeInterface|string>,
     *   recurrence_id?:DateTimeInterface|string|null,
     *   exceptions?:array<int,array<string,mixed>>,
     *   include_timezone_definition?:bool
     * } $options
     */
    public static function generateIcs(
        string $summary,
        string $description,
        DateTimeInterface|string $start,
        DateTimeInterface|string|null $end = null,
        array $options = [],
    ): string {
        $zone = self::timezone((string) ($options['timezone'] ?? 'UTC'));
        $allDay = (bool) ($options['all_day'] ?? false);
        $startDate = self::date($start, $zone, $allDay);
        $endDate = $end !== null ? self::date($end, $zone, $allDay) : null;
        if ($endDate !== null && $endDate <= $startDate) {
            throw new \InvalidArgumentException('Calendar event end must be after start');
        }

        $calendar = new VCalendar([
            'VERSION' => '2.0',
            'PRODID' => self::PRODID,
            'CALSCALE' => 'GREGORIAN',
            'METHOD' => 'PUBLISH',
        ], false);

        if ($zone->getName() !== 'UTC'
            && (bool) ($options['include_timezone_definition'] ?? true)) {
            self::addTimezoneDefinition($calendar, $zone, $startDate);
        }

        $uid = self::uid($summary, $startDate, $options);
        $dtstamp = self::date(
            $options['dtstamp'] ?? $options['updated_at'] ?? new DateTimeImmutable('now', new DateTimeZone('UTC')),
            new DateTimeZone('UTC'),
            false,
        )->setTimezone(new DateTimeZone('UTC'));
        $sequence = max(0, (int) ($options['sequence'] ?? 0));
        $status = self::calendarStatus($options);

        /** @var VEvent $event */
        $event = $calendar->add('VEVENT', [], false);
        self::addCommonEventProperties(
            $event,
            $uid,
            $summary,
            $description,
            $startDate,
            $endDate,
            $dtstamp,
            $sequence,
            $status,
            $allDay,
            $options,
        );

        $rrule = trim((string) ($options['rrule'] ?? ''));
        if ($rrule !== '') {
            $rrule = str_starts_with(strtoupper($rrule), 'RRULE:') ? substr($rrule, 6) : $rrule;
            $event->add('RRULE', $rrule);
        }
        self::addDateCollection($event, 'EXDATE', $options['exdates'] ?? [], $zone, $allDay);
        self::addDateCollection($event, 'RDATE', $options['rdates'] ?? [], $zone, $allDay);

        if (($options['recurrence_id'] ?? null) !== null) {
            self::addDateProperty(
                $event,
                'RECURRENCE-ID',
                self::date($options['recurrence_id'], $zone, $allDay),
                $allDay,
            );
        }

        foreach (($options['exceptions'] ?? []) as $exception) {
            if (! is_array($exception) || ! isset($exception['recurrence_id'])) {
                throw new \InvalidArgumentException('Calendar recurrence exceptions require recurrence_id');
            }
            $exceptionStart = self::date(
                $exception['start'] ?? $exception['recurrence_id'],
                $zone,
                $allDay,
            );
            $exceptionEnd = isset($exception['end'])
                ? self::date($exception['end'], $zone, $allDay)
                : null;
            /** @var VEvent $override */
            $override = $calendar->add('VEVENT', [], false);
            self::addCommonEventProperties(
                $override,
                $uid,
                (string) ($exception['summary'] ?? $summary),
                (string) ($exception['description'] ?? $description),
                $exceptionStart,
                $exceptionEnd,
                $dtstamp,
                max($sequence, (int) ($exception['sequence'] ?? $sequence)),
                self::calendarStatus($exception),
                $allDay,
                array_merge($options, $exception),
            );
            self::addDateProperty(
                $override,
                'RECURRENCE-ID',
                self::date($exception['recurrence_id'], $zone, $allDay),
                $allDay,
            );
        }

        return $calendar->serialize();
    }

    /** @param array<string,mixed> $options */
    private static function addCommonEventProperties(
        VEvent $event,
        string $uid,
        string $summary,
        string $description,
        DateTimeImmutable $start,
        ?DateTimeImmutable $end,
        DateTimeImmutable $dtstamp,
        int $sequence,
        string $status,
        bool $allDay,
        array $options,
    ): void {
        $event->add('UID', $uid);
        $event->add('DTSTAMP', $dtstamp);
        self::addDateProperty($event, 'DTSTART', $start, $allDay);
        if ($end !== null) {
            self::addDateProperty($event, 'DTEND', $end, $allDay);
        }
        $event->add('SUMMARY', $summary);
        if ($description !== '') {
            $event->add('DESCRIPTION', $description);
        }
        $event->add('SEQUENCE', $sequence);
        $event->add('STATUS', $status);
        $event->add('TRANSP', 'OPAQUE');

        if ((bool) ($options['include_location'] ?? false)
            && isset($options['location'])
            && trim((string) $options['location']) !== '') {
            $event->add('LOCATION', trim((string) $options['location']));
        }
        if ((bool) ($options['include_online_access'] ?? false)
            && isset($options['online_link'])
            && filter_var($options['online_link'], FILTER_VALIDATE_URL) !== false) {
            $event->add('URL', (string) $options['online_link']);
        } elseif ((bool) ($options['include_public_url'] ?? false)
            && isset($options['public_url'])
            && filter_var($options['public_url'], FILTER_VALIDATE_URL) !== false) {
            $event->add('URL', (string) $options['public_url']);
        }
    }

    private static function addDateProperty(
        VEvent $event,
        string $name,
        DateTimeImmutable $date,
        bool $allDay,
    ): void {
        $event->add($name, $date, $allDay ? ['VALUE' => 'DATE'] : []);
    }

    /**
     * @param array<int,DateTimeInterface|string> $values
     */
    private static function addDateCollection(
        VEvent $event,
        string $name,
        array $values,
        DateTimeZone $zone,
        bool $allDay,
    ): void {
        if ($values === []) {
            return;
        }
        $dates = array_map(
            static fn (DateTimeInterface|string $value): DateTimeImmutable => self::date($value, $zone, $allDay),
            $values,
        );
        $event->add($name, $dates, $allDay ? ['VALUE' => 'DATE'] : []);
    }

    private static function date(
        DateTimeInterface|string $value,
        DateTimeZone $zone,
        bool $allDay,
    ): DateTimeImmutable {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value)->setTimezone($zone);
        }

        $value = trim($value);
        if ($value === '') {
            throw new \InvalidArgumentException('Calendar date cannot be empty');
        }
        try {
            if ($allDay && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) === 1) {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $zone);
                if (! $date instanceof DateTimeImmutable || $date->format('Y-m-d') !== $value) {
                    throw new \InvalidArgumentException('Invalid all-day calendar date');
                }

                return $date;
            }

            $hasOffset = preg_match('/(?:Z|[+-]\d{2}:?\d{2})$/iD', $value) === 1;
            return new DateTimeImmutable($value, $hasOffset ? null : $zone);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Invalid calendar date', 0, $e);
        }
    }

    private static function timezone(string $timezone): DateTimeZone
    {
        try {
            return new DateTimeZone($timezone);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Invalid calendar timezone', 0, $e);
        }
    }

    /** @param array<string,mixed> $options */
    private static function uid(string $summary, DateTimeImmutable $start, array $options): string
    {
        if (isset($options['uid'])) {
            $uid = trim((string) $options['uid']);
            if ($uid === '' || preg_match('/[\r\n]/', $uid) === 1) {
                throw new \InvalidArgumentException('Invalid calendar UID');
            }

            return $uid;
        }

        $seed = (string) ($options['uid_seed'] ?? implode('|', [
            (string) ($options['tenant_id'] ?? ''),
            (string) ($options['event_id'] ?? ''),
            (string) ($options['occurrence_key'] ?? ''),
            $summary,
            $start->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z'),
        ]));
        $domain = preg_replace('/[^A-Za-z0-9.-]/', '', (string) ($options['uid_domain'] ?? 'events.project-nexus.ie'));
        if ($domain === null || $domain === '') {
            $domain = 'events.project-nexus.ie';
        }

        return substr(hash('sha256', $seed), 0, 40) . '@' . $domain;
    }

    /** @param array<string,mixed> $options */
    private static function calendarStatus(array $options): string
    {
        $status = strtolower(trim((string) (
            $options['operational_status']
            ?? $options['lifecycle_status']
            ?? $options['status']
            ?? 'confirmed'
        )));

        return match ($status) {
            'cancelled', 'canceled', 'archived', 'deleted' => 'CANCELLED',
            'tentative', 'draft', 'pending' => 'TENTATIVE',
            default => 'CONFIRMED',
        };
    }

    private static function addTimezoneDefinition(
        VCalendar $calendar,
        DateTimeZone $zone,
        DateTimeImmutable $eventStart,
    ): void {
        $years = 20;
        $container = Container::getInstance();
        if ($container->bound('config')) {
            $years = (int) $container->make('config')->get(
                'events.recurrence.max_horizon_years',
                $years,
            );
        }
        $years = max(1, min($years, 100));
        $rangeStart = $eventStart->modify('-1 year')->getTimestamp();
        $rangeEnd = $eventStart->modify('+' . $years . ' years')->getTimestamp();
        $transitions = $zone->getTransitions($rangeStart, $rangeEnd);
        if ($transitions === false || $transitions === []) {
            return;
        }

        /** @var Component $timezone */
        $timezone = $calendar->add('VTIMEZONE', [
            'TZID' => $zone->getName(),
            'X-LIC-LOCATION' => $zone->getName(),
        ], false);
        $previous = $transitions[0];

        if (count($transitions) === 1) {
            $timezone->add($calendar->createComponent('STANDARD', [
                'DTSTART' => gmdate('Ymd\THis', $rangeStart + (int) $previous['offset']),
                'TZOFFSETFROM' => self::offset((int) $previous['offset']),
                'TZOFFSETTO' => self::offset((int) $previous['offset']),
                'TZNAME' => (string) $previous['abbr'],
            ], false));

            return;
        }

        foreach (array_slice($transitions, 1) as $transition) {
            $localOnset = gmdate(
                'Ymd\THis',
                (int) $transition['ts'] + (int) $transition['offset'],
            );
            $timezone->add($calendar->createComponent(
                (bool) $transition['isdst'] ? 'DAYLIGHT' : 'STANDARD',
                [
                    'DTSTART' => $localOnset,
                    'TZOFFSETFROM' => self::offset((int) $previous['offset']),
                    'TZOFFSETTO' => self::offset((int) $transition['offset']),
                    'TZNAME' => (string) $transition['abbr'],
                ],
                false,
            ));
            $previous = $transition;
        }
    }

    private static function offset(int $seconds): string
    {
        $sign = $seconds < 0 ? '-' : '+';
        $seconds = abs($seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remaining = $seconds % 60;

        return $sign . sprintf('%02d%02d', $hours, $minutes)
            . ($remaining !== 0 ? sprintf('%02d', $remaining) : '');
    }
}

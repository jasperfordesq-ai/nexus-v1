<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Validation\ValidationException;
use Sabre\VObject\InvalidDataException;
use Sabre\VObject\Property\ICalendar\Recur;
use Sabre\VObject\Recur\RRuleIterator;

/**
 * Narrow, fail-closed adapter around sabre/vobject recurrence primitives.
 *
 * The application deliberately supports a reviewed RRULE subset. Accepting a
 * property that the underlying iterator silently ignores would create a series
 * different from the one the organiser requested, so unknown or unsupported
 * combinations are rejected before any occurrence is persisted.
 */
final class EventRecurrenceService
{
    public const ENGINE = 'sabre-vobject';
    public const ENGINE_VERSION = '2';

    /** @var list<string> */
    private const CANONICAL_ORDER = [
        'FREQ',
        'INTERVAL',
        'BYDAY',
        'BYMONTHDAY',
        'BYMONTH',
        'WKST',
        'COUNT',
        'UNTIL',
    ];

    /** @var array<string,int> */
    private const WEEKDAY_ORDER = [
        'MO' => 1,
        'TU' => 2,
        'WE' => 3,
        'TH' => 4,
        'FR' => 5,
        'SA' => 6,
        'SU' => 7,
    ];

    /** @var array<string,string> */
    private const LEGACY_WEEKDAYS = [
        '0' => 'SU',
        '1' => 'MO',
        '2' => 'TU',
        '3' => 'WE',
        '4' => 'TH',
        '5' => 'FR',
        '6' => 'SA',
    ];

    /**
     * @param array<string,mixed> $input
     * @return array{
     *   frequency:string,
     *   interval:int,
     *   days_of_week:?string,
     *   day_of_month:?int,
     *   month_of_year:?int,
     *   rrule:string,
     *   ends_type:string,
     *   ends_after_count:?int,
     *   ends_on_date:?string,
     *   exdates:list<string>,
     *   rdates:list<string>,
     *   rule_hash:string
     * }
     */
    public function normalize(
        array $input,
        DateTimeInterface $startUtc,
        string $timezone,
    ): array {
        $zone = $this->timezone($timezone);
        $utc = new DateTimeZone('UTC');
        $startLocal = DateTimeImmutable::createFromInterface($startUtc)
            ->setTimezone($utc)
            ->setTimezone($zone);

        $rawRrule = $input['recurrence_rrule'] ?? null;
        if ($rawRrule !== null && ! is_string($rawRrule)) {
            $this->invalid('recurrence_rrule');
        }

        if (is_string($rawRrule) && trim($rawRrule) !== '') {
            $parts = $this->parseParts($rawRrule);
        } else {
            $parts = $this->partsFromLegacyInput($input, $startLocal);
        }

        $parts = $this->validateAndCanonicalizeParts($parts, $startLocal);
        $rrule = $this->serializeParts($parts);
        $exdates = $this->normalizeDateList(
            $input['recurrence_exdates'] ?? $input['exdates'] ?? [],
            $startLocal,
            $zone,
            'recurrence_exdates',
        );
        $rdates = $this->normalizeDateList(
            $input['recurrence_rdates'] ?? $input['recurrence_additions'] ?? $input['rdates'] ?? [],
            $startLocal,
            $zone,
            'recurrence_rdates',
        );

        $until = isset($parts['UNTIL']) ? (string) $parts['UNTIL'] : null;
        $untilLocal = $until !== null
            ? $this->parseCanonicalUtc($until)->setTimezone($zone)
            : null;

        return [
            'frequency' => strtolower((string) $parts['FREQ']),
            'interval' => isset($parts['INTERVAL']) ? (int) $parts['INTERVAL'] : 1,
            'days_of_week' => isset($parts['BYDAY'])
                ? implode(',', $this->listValue($parts['BYDAY']))
                : null,
            'day_of_month' => isset($parts['BYMONTHDAY'])
                ? (int) $this->listValue($parts['BYMONTHDAY'])[0]
                : null,
            'month_of_year' => isset($parts['BYMONTH'])
                ? (int) $this->listValue($parts['BYMONTH'])[0]
                : null,
            'rrule' => $rrule,
            'ends_type' => isset($parts['COUNT'])
                ? 'after_count'
                : (isset($parts['UNTIL']) ? 'on_date' : 'never'),
            'ends_after_count' => isset($parts['COUNT']) ? (int) $parts['COUNT'] : null,
            'ends_on_date' => $untilLocal?->format('Y-m-d'),
            'exdates' => $exdates,
            'rdates' => $rdates,
            'rule_hash' => hash('sha256', implode('|', [
                self::ENGINE,
                self::ENGINE_VERSION,
                $timezone,
                $startLocal->format('Y-m-d\TH:i:sP'),
                $rrule,
                implode(',', $exdates),
                implode(',', $rdates),
            ])),
        ];
    }

    /**
     * Expand a canonical recurrence into concrete UTC instants.
     *
     * COUNT is intentionally left to sabre/vobject, whose iterator counts
     * DTSTART as occurrence one. EXDATE removes generated instances after that
     * count; RDATE additions are then merged and de-duplicated.
     *
     * @param list<string> $exdates Canonical UTC values from normalize().
     * @param list<string> $rdates Canonical UTC values from normalize().
     * @return list<array{
     *   start_utc:string,
     *   end_utc:?string,
     *   local_start:string,
     *   occurrence_date:string,
     *   recurrence_id:string
     * }>
     */
    public function expand(
        DateTimeInterface $startUtc,
        ?DateTimeInterface $endUtc,
        string $timezone,
        string $rrule,
        array $exdates = [],
        array $rdates = [],
    ): array {
        $zone = $this->timezone($timezone);
        $utc = new DateTimeZone('UTC');
        $startLocal = DateTimeImmutable::createFromInterface($startUtc)
            ->setTimezone($utc)
            ->setTimezone($zone);
        $endLocal = $endUtc !== null
            ? DateTimeImmutable::createFromInterface($endUtc)->setTimezone($utc)->setTimezone($zone)
            : null;
        $parts = $this->validateAndCanonicalizeParts($this->parseParts($rrule), $startLocal);
        $canonicalRrule = $this->serializeParts($parts);
        $maxOccurrences = $this->maxOccurrences();
        $horizon = $startLocal->modify('+' . $this->maxHorizonYears() . ' years');

        $excluded = [];
        foreach ($exdates as $value) {
            $excluded[$this->parseCanonicalUtc($value)->getTimestamp()] = true;
        }

        try {
            $iterator = new RRuleIterator($canonicalRrule, $startLocal);
        } catch (InvalidDataException | \InvalidArgumentException $e) {
            $this->invalid('recurrence_rrule', $e);
        }

        /** @var array<int,DateTimeImmutable> $starts */
        $starts = [];
        $generated = 0;
        $finite = isset($parts['COUNT']) || isset($parts['UNTIL']);
        $iterator->rewind();

        while ($iterator->valid()) {
            $current = $iterator->current();
            if (! $current instanceof DateTimeInterface) {
                break;
            }

            $local = DateTimeImmutable::createFromInterface($current)->setTimezone($zone);
            if ($local > $horizon) {
                break;
            }

            $generated++;
            if ($generated > $maxOccurrences) {
                if ($finite) {
                    $this->invalid('recurrence_rrule');
                }
                break;
            }

            $timestamp = $local->getTimestamp();
            if (! isset($excluded[$timestamp])) {
                $starts[$timestamp] = $local;
            }

            $iterator->next();
        }

        foreach ($rdates as $value) {
            $addition = $this->parseCanonicalUtc($value)->setTimezone($zone);
            if ($addition < $startLocal || $addition > $horizon) {
                $this->invalid('recurrence_rdates');
            }
            if (! isset($excluded[$addition->getTimestamp()])) {
                $starts[$addition->getTimestamp()] = $addition;
            }
        }

        if (count($starts) > $maxOccurrences) {
            $this->invalid('recurrence_rdates');
        }

        ksort($starts, SORT_NUMERIC);
        $wallEnd = $this->wallEndDescriptor($startLocal, $endLocal);
        $occurrences = [];
        foreach ($starts as $local) {
            $occurrenceEnd = $wallEnd !== null
                ? $this->applyWallEnd($local, $wallEnd)
                : null;
            $startInstant = $local->setTimezone($utc);
            $endInstant = $occurrenceEnd?->setTimezone($utc);

            $occurrences[] = [
                'start_utc' => $startInstant->format('Y-m-d H:i:s'),
                'end_utc' => $endInstant?->format('Y-m-d H:i:s'),
                'local_start' => $local->format('Y-m-d\TH:i:sP'),
                'occurrence_date' => $local->format('Y-m-d'),
                'recurrence_id' => $startInstant->format('Ymd\THis\Z'),
            ];
        }

        return $occurrences;
    }

    public function occurrenceKey(int $tenantId, int $templateId, string $recurrenceId): string
    {
        return sprintf(
            'recurrence:%d:%d:%s',
            $tenantId,
            $templateId,
            substr(hash('sha256', self::ENGINE . '|' . self::ENGINE_VERSION . '|' . $recurrenceId), 0, 32),
        );
    }

    /** @param array<string,mixed> $input @return array<string,string|list<string>> */
    private function partsFromLegacyInput(array $input, DateTimeImmutable $startLocal): array
    {
        $frequency = strtolower(trim((string) ($input['recurrence_frequency'] ?? '')));
        if (! in_array($frequency, ['daily', 'weekly', 'monthly', 'yearly'], true)) {
            $this->invalid('recurrence_frequency');
        }

        $interval = filter_var(
            $input['recurrence_interval'] ?? 1,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 365]],
        );
        if ($interval === false) {
            $this->invalid('recurrence_interval');
        }

        $parts = [
            'FREQ' => strtoupper($frequency),
            'INTERVAL' => (string) $interval,
        ];

        if ($frequency === 'weekly' && isset($input['recurrence_days'])) {
            if (! is_string($input['recurrence_days'])) {
                $this->invalid('recurrence_days');
            }
            $days = [];
            foreach (explode(',', $input['recurrence_days']) as $day) {
                $day = strtoupper(trim($day));
                if ($day === '') {
                    continue;
                }
                $day = self::LEGACY_WEEKDAYS[$day] ?? $day;
                if (! isset(self::WEEKDAY_ORDER[$day])) {
                    $this->invalid('recurrence_days');
                }
                $days[$day] = true;
            }
            if ($days === []) {
                $this->invalid('recurrence_days');
            }
            $parts['BYDAY'] = array_keys($days);
        }

        if ($frequency === 'monthly' && isset($input['recurrence_day_of_month'])) {
            $parts['BYMONTHDAY'] = [(string) $input['recurrence_day_of_month']];
        }

        if ($frequency === 'yearly') {
            if (isset($input['recurrence_month_of_year'])) {
                $parts['BYMONTH'] = [(string) $input['recurrence_month_of_year']];
            }
            if (isset($input['recurrence_day_of_month'])) {
                $parts['BYMONTHDAY'] = [(string) $input['recurrence_day_of_month']];
                $parts['BYMONTH'] ??= [$startLocal->format('n')];
            }
        }

        $endsType = strtolower(trim((string) ($input['recurrence_ends_type'] ?? 'after_count')));
        if ($endsType === 'after_count') {
            $parts['COUNT'] = (string) ($input['recurrence_ends_after_count'] ?? 10);
        } elseif ($endsType === 'on_date') {
            $value = $input['recurrence_ends_on_date'] ?? null;
            if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) !== 1) {
                $this->invalid('recurrence_ends_on_date');
            }
            $untilLocal = $this->parseLocalWall($value . ' 23:59:59', $startLocal->getTimezone());
            $parts['UNTIL'] = $untilLocal->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
        } elseif ($endsType !== 'never') {
            $this->invalid('recurrence_ends_type');
        }

        return $parts;
    }

    /** @return array<string,string|list<string>> */
    private function parseParts(string $rrule): array
    {
        $rrule = trim($rrule);
        if (str_starts_with(strtoupper($rrule), 'RRULE:')) {
            $rrule = substr($rrule, 6);
        }
        if ($rrule === '' || strlen($rrule) > 2048 || strpbrk($rrule, "\r\n") !== false) {
            $this->invalid('recurrence_rrule');
        }

        $seen = [];
        foreach (explode(';', $rrule) as $segment) {
            $name = strtoupper((string) strstr($segment, '=', true));
            if ($name === '' || isset($seen[$name])) {
                $this->invalid('recurrence_rrule');
            }
            $seen[$name] = true;
        }

        try {
            /** @var array<string,string|list<string>> $parts */
            $parts = Recur::stringToArray($rrule);
        } catch (InvalidDataException $e) {
            $this->invalid('recurrence_rrule', $e);
        }

        return $parts;
    }

    /**
     * @param array<string,string|list<string>> $parts
     * @return array<string,string|list<string>>
     */
    private function validateAndCanonicalizeParts(array $parts, DateTimeImmutable $startLocal): array
    {
        foreach (array_keys($parts) as $name) {
            if (! in_array($name, self::CANONICAL_ORDER, true)) {
                $this->invalid('recurrence_rrule');
            }
        }

        $frequency = strtoupper($this->scalarValue($parts['FREQ'] ?? null, 'recurrence_rrule'));
        if (! in_array($frequency, ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'], true)) {
            $this->invalid('recurrence_rrule');
        }
        $parts['FREQ'] = $frequency;

        if (isset($parts['INTERVAL'])) {
            $parts['INTERVAL'] = (string) $this->positiveInteger(
                $parts['INTERVAL'],
                365,
                'recurrence_rrule',
            );
        }

        if (isset($parts['COUNT'])) {
            $parts['COUNT'] = (string) $this->positiveInteger(
                $parts['COUNT'],
                $this->maxOccurrences(),
                'recurrence_rrule',
            );
        }
        if (isset($parts['COUNT'], $parts['UNTIL'])) {
            $this->invalid('recurrence_rrule');
        }

        if (isset($parts['UNTIL'])) {
            $parts['UNTIL'] = $this->normalizeUntil(
                $this->scalarValue($parts['UNTIL'], 'recurrence_rrule'),
                $startLocal,
            );
        }

        if (isset($parts['BYDAY'])) {
            $days = [];
            foreach ($this->listValue($parts['BYDAY']) as $day) {
                $day = strtoupper(trim($day));
                if (! isset(self::WEEKDAY_ORDER[$day])) {
                    $this->invalid('recurrence_rrule');
                }
                $days[$day] = true;
            }
            uksort($days, static fn (string $a, string $b): int => self::WEEKDAY_ORDER[$a] <=> self::WEEKDAY_ORDER[$b]);
            $parts['BYDAY'] = array_keys($days);
        }

        if (isset($parts['BYMONTHDAY'])) {
            $parts['BYMONTHDAY'] = $this->integerList($parts['BYMONTHDAY'], -31, 31, true);
        }
        if (isset($parts['BYMONTH'])) {
            $parts['BYMONTH'] = $this->integerList($parts['BYMONTH'], 1, 12, false);
        }
        if (isset($parts['WKST'])) {
            $weekStart = strtoupper($this->scalarValue($parts['WKST'], 'recurrence_rrule'));
            if (! isset(self::WEEKDAY_ORDER[$weekStart])) {
                $this->invalid('recurrence_rrule');
            }
            $parts['WKST'] = $weekStart;
        }

        if ($frequency === 'WEEKLY') {
            if (isset($parts['BYMONTHDAY']) || isset($parts['BYMONTH'])) {
                $this->invalid('recurrence_rrule');
            }
        } elseif ($frequency === 'MONTHLY') {
            if (isset($parts['BYMONTH']) || isset($parts['WKST'])) {
                $this->invalid('recurrence_rrule');
            }
        } elseif ($frequency === 'YEARLY') {
            if (isset($parts['WKST'])
                || ((isset($parts['BYMONTHDAY']) || isset($parts['BYDAY'])) && ! isset($parts['BYMONTH']))) {
                $this->invalid('recurrence_rrule');
            }
        } elseif (isset($parts['BYMONTHDAY']) || isset($parts['WKST'])) {
            $this->invalid('recurrence_rrule');
        }

        return $parts;
    }

    /** @param array<string,string|list<string>> $parts */
    private function serializeParts(array $parts): string
    {
        $segments = [];
        foreach (self::CANONICAL_ORDER as $name) {
            if (! isset($parts[$name])) {
                continue;
            }
            $value = $parts[$name];
            $segments[] = $name . '=' . (is_array($value) ? implode(',', $value) : $value);
        }

        return implode(';', $segments);
    }

    /** @return list<string> */
    private function normalizeDateList(
        mixed $raw,
        DateTimeImmutable $startLocal,
        DateTimeZone $zone,
        string $field,
    ): array {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (is_string($raw)) {
            $raw = array_map('trim', explode(',', $raw));
        }
        if (! is_array($raw) || count($raw) > $this->maxOccurrences()) {
            $this->invalid($field);
        }

        $values = [];
        foreach ($raw as $value) {
            if (! is_string($value) || trim($value) === '') {
                $this->invalid($field);
            }
            $value = trim($value);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) === 1) {
                $value .= ' ' . $startLocal->format('H:i:s');
            }

            if (preg_match('/^\d{8}T\d{6}Z$/D', strtoupper($value)) === 1) {
                $date = $this->parseCanonicalUtc(strtoupper($value));
            } elseif (preg_match('/(?:Z|[+-]\d{2}:?\d{2})$/iD', $value) === 1) {
                try {
                    $date = new DateTimeImmutable($value);
                } catch (\Exception $e) {
                    $this->invalid($field, $e);
                }
            } else {
                $date = $this->parseLocalWall(str_replace('T', ' ', $value), $zone, $field);
            }

            $canonical = $date->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
            $values[$canonical] = true;
        }

        $result = array_keys($values);
        sort($result, SORT_STRING);

        return $result;
    }

    private function normalizeUntil(string $value, DateTimeImmutable $startLocal): string
    {
        $value = strtoupper(trim($value));
        if (preg_match('/^\d{8}$/D', $value) === 1) {
            $date = DateTimeImmutable::createFromFormat('!Ymd', $value, $startLocal->getTimezone());
            if (! $date instanceof DateTimeImmutable || $date->format('Ymd') !== $value) {
                $this->invalid('recurrence_rrule');
            }
            $until = $date->setTime(23, 59, 59)->setTimezone(new DateTimeZone('UTC'));
        } elseif (preg_match('/^\d{8}T\d{6}Z$/D', $value) === 1) {
            $until = $this->parseCanonicalUtc($value);
        } else {
            $this->invalid('recurrence_rrule');
        }

        if ($until < $startLocal->setTimezone(new DateTimeZone('UTC'))
            || $until > $startLocal->modify('+' . $this->maxHorizonYears() . ' years')->setTimezone(new DateTimeZone('UTC'))) {
            $this->invalid('recurrence_rrule');
        }

        return $until->format('Ymd\THis\Z');
    }

    private function parseCanonicalUtc(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Ymd\THis\Z', strtoupper($value), new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        if (! $date instanceof DateTimeImmutable
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Ymd\THis\Z') !== strtoupper($value)) {
            $this->invalid('recurrence_rrule');
        }

        return $date;
    }

    private function parseLocalWall(
        string $value,
        DateTimeZone $zone,
        string $field = 'recurrence_rrule',
    ): DateTimeImmutable {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, $zone);
        $errors = DateTimeImmutable::getLastErrors();
        if (! $date instanceof DateTimeImmutable
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d H:i:s') !== $value) {
            $this->invalid($field);
        }

        return $date;
    }

    /** @return array{days:int,hour:int,minute:int,second:int}|null */
    private function wallEndDescriptor(
        DateTimeImmutable $startLocal,
        ?DateTimeImmutable $endLocal,
    ): ?array {
        if ($endLocal === null) {
            return null;
        }

        $neutral = new DateTimeZone('UTC');
        $startDate = new DateTimeImmutable($startLocal->format('Y-m-d') . ' 00:00:00', $neutral);
        $endDate = new DateTimeImmutable($endLocal->format('Y-m-d') . ' 00:00:00', $neutral);

        return [
            'days' => max(0, (int) floor(($endDate->getTimestamp() - $startDate->getTimestamp()) / 86400)),
            'hour' => (int) $endLocal->format('H'),
            'minute' => (int) $endLocal->format('i'),
            'second' => (int) $endLocal->format('s'),
        ];
    }

    /** @param array{days:int,hour:int,minute:int,second:int} $descriptor */
    private function applyWallEnd(DateTimeImmutable $start, array $descriptor): DateTimeImmutable
    {
        $end = $descriptor['days'] > 0
            ? $start->modify('+' . $descriptor['days'] . ' days')
            : $start;

        return $end->setTime($descriptor['hour'], $descriptor['minute'], $descriptor['second']);
    }

    /** @return list<string> */
    private function integerList(mixed $raw, int $min, int $max, bool $rejectZero): array
    {
        $values = [];
        foreach ($this->listValue($raw) as $value) {
            if (preg_match('/^-?\d+$/D', $value) !== 1) {
                $this->invalid('recurrence_rrule');
            }
            $number = (int) $value;
            if ($number < $min || $number > $max || ($rejectZero && $number === 0)) {
                $this->invalid('recurrence_rrule');
            }
            $values[$number] = true;
        }
        ksort($values, SORT_NUMERIC);

        return array_map(static fn (int $value): string => (string) $value, array_keys($values));
    }

    /** @return list<string> */
    private function listValue(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];
        if ($values === []) {
            $this->invalid('recurrence_rrule');
        }

        return array_map(function (mixed $item): string {
            if (! is_string($item) && ! is_int($item)) {
                $this->invalid('recurrence_rrule');
            }
            $item = trim((string) $item);
            if ($item === '') {
                $this->invalid('recurrence_rrule');
            }

            return $item;
        }, array_values($values));
    }

    private function scalarValue(mixed $value, string $field): string
    {
        if (is_array($value) || (! is_string($value) && ! is_int($value))) {
            $this->invalid($field);
        }
        $value = trim((string) $value);
        if ($value === '') {
            $this->invalid($field);
        }

        return $value;
    }

    private function positiveInteger(mixed $value, int $max, string $field): int
    {
        $value = $this->scalarValue($value, $field);
        if (preg_match('/^\d+$/D', $value) !== 1) {
            $this->invalid($field);
        }
        $number = (int) $value;
        if ($number < 1 || $number > $max) {
            $this->invalid($field);
        }

        return $number;
    }

    private function timezone(string $timezone): DateTimeZone
    {
        try {
            return new DateTimeZone($timezone);
        } catch (\Exception $e) {
            $this->invalid('timezone', $e);
        }
    }

    private function maxOccurrences(): int
    {
        return max(1, min((int) config('events.recurrence.max_occurrences', 366), 5000));
    }

    private function maxHorizonYears(): int
    {
        return max(1, min((int) config('events.recurrence.max_horizon_years', 20), 100));
    }

    private function invalid(string $field, ?\Throwable $previous = null): never
    {
        // ValidationException intentionally owns the public, translated error
        // contract. The lower-level parser exception is never exposed because
        // it may contain implementation-specific RRULE details.
        unset($previous);

        throw ValidationException::withMessages([
            $field => [__('api.invalid_input')],
        ]);
    }
}

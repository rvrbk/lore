<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeZone;
use Throwable;

/**
 * Reads an iCalendar (.ics) feed and lists the events that fall inside a window, expanding recurring events.
 *
 * Supports DAILY, WEEKLY, MONTHLY and YEARLY rules with INTERVAL, COUNT, UNTIL, BYDAY, BYMONTHDAY, EXDATE and
 * moved or cancelled single occurrences, which covers what Google and iCloud export.
 */
class IcsCalendar
{
    private const DAYS = ['MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6, 'SU' => 0];

    /** Upper bound on generated occurrences per event, so a malformed rule cannot loop forever. */
    private const MAX_STEPS = 5000;

    /**
     * @return array<int, array{title: string, start: CarbonImmutable, end: CarbonImmutable, all_day: bool, location: ?string}>
     */
    public function eventsBetween(string $ics, CarbonInterface $from, CarbonInterface $to): array
    {
        $from = CarbonImmutable::instance($from);
        $to = CarbonImmutable::instance($to);
        $masters = [];
        $overrides = [];
        $standalone = [];

        foreach ($this->parse($ics) as $event) {
            if ($event['recurrence_id'] !== null) {
                $overrides[$event['uid']][$event['recurrence_id']] = $event;
            } elseif ($event['rrule'] !== null) {
                $masters[] = $event;
            } else {
                $standalone[] = $event;
            }
        }

        $found = [];
        foreach ($standalone as $event) {
            $found[] = $this->occurrence($event, $event['start']);
        }
        foreach ($overrides as $moved) {
            foreach ($moved as $event) {
                $found[] = $this->occurrence($event, $event['start']);
            }
        }
        foreach ($masters as $event) {
            foreach ($this->starts($event, $from, $to) as $start) {
                if (isset($overrides[$event['uid']][$start->getTimestamp()])) {
                    continue;
                }
                $found[] = $this->occurrence($event, $start);
            }
        }

        $events = [];
        foreach ($found as $item) {
            if ($item['cancelled']) {
                continue;
            }
            $overlaps = $item['end']->greaterThan($item['start'])
                ? $item['start']->lessThan($to) && $item['end']->greaterThan($from)
                : $item['start']->greaterThanOrEqualTo($from) && $item['start']->lessThan($to);
            if ($overlaps) {
                unset($item['cancelled']);
                $events[] = $item;
            }
        }
        usort($events, fn (array $a, array $b) => [$b['all_day'], $a['start']->getTimestamp()] <=> [$a['all_day'], $b['start']->getTimestamp()]);

        return $events;
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array{title: string, start: CarbonImmutable, end: CarbonImmutable, all_day: bool, location: ?string, cancelled: bool}
     */
    private function occurrence(array $event, CarbonImmutable $start): array
    {
        return [
            'title' => $event['title'],
            'start' => $start,
            'end' => $start->addSeconds($event['seconds']),
            'all_day' => $event['all_day'],
            'location' => $event['location'],
            'cancelled' => $event['cancelled'],
        ];
    }

    /**
     * Every start of a recurring event that could touch the window.
     *
     * @param  array<string, mixed>  $event
     * @return array<int, CarbonImmutable>
     */
    private function starts(array $event, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rule = $event['rrule'];
        $interval = max(1, (int) ($rule['INTERVAL'] ?? 1));
        $count = isset($rule['COUNT']) ? (int) $rule['COUNT'] : null;
        $until = null;
        if (isset($rule['UNTIL'])) {
            $parsed = $this->parseValue($rule['UNTIL'], [], $event['start']->getTimezone());
            $until = $parsed['all_day'] ? $parsed['date']->endOfDay() : $parsed['date'];
        }
        $first = $event['start'];
        $starts = [];
        $emitted = 0;

        $skipped = $count === null ? $this->periodsBefore($rule['FREQ'] ?? '', $first, $from, $interval) : 0;

        for ($step = $skipped; $step < $skipped + self::MAX_STEPS; $step++) {
            [$base, $candidates] = $this->period($rule['FREQ'] ?? '', $first, $step * $interval, $rule);
            $candidates = $this->atSetPositions($candidates, $rule['BYSETPOS'] ?? null);
            if ($base === null || $base->greaterThanOrEqualTo($to)) {
                break;
            }
            foreach ($candidates as $candidate) {
                if ($candidate->lessThan($first)) {
                    continue;
                }
                if (($until !== null && $candidate->greaterThan($until)) || ($count !== null && $emitted >= $count)) {
                    return $starts;
                }
                $emitted++;
                if (in_array($candidate->getTimestamp(), $event['exdates'], true)) {
                    continue;
                }
                $end = $candidate->addSeconds($event['seconds']);
                if ($candidate->lessThan($to) && ($end->greaterThan($from) || $candidate->greaterThanOrEqualTo($from))) {
                    $starts[] = $candidate;
                }
            }
        }

        return $starts;
    }

    /**
     * How many whole periods (a safe under-estimate) lie between the first start and the window, so long-running rules need not be walked from the start.
     */
    private function periodsBefore(string $frequency, CarbonImmutable $first, CarbonImmutable $from, int $interval): int
    {
        if ($from->lessThanOrEqualTo($first)) {
            return 0;
        }
        $elapsed = match ($frequency) {
            'DAILY' => (int) $first->diffInDays($from),
            'WEEKLY' => (int) $first->diffInWeeks($from),
            'MONTHLY' => (int) $first->diffInMonths($from),
            'YEARLY' => (int) $first->diffInYears($from),
            default => 0,
        };

        return max(0, intdiv($elapsed, $interval) - 1);
    }

    /**
     * The first moment of a recurrence period and the starts inside it, in order.
     *
     * @param  array<string, string>  $rule
     * @return array{0: ?CarbonImmutable, 1: array<int, CarbonImmutable>}
     */
    private function period(string $frequency, CarbonImmutable $first, int $offset, array $rule): array
    {
        $time = [$first->hour, $first->minute, $first->second];
        $byDay = array_filter(explode(',', $rule['BYDAY'] ?? ''));

        switch ($frequency) {
            case 'DAILY':
                $day = $first->addDays($offset);

                return [$day->startOfDay(), [$day]];

            case 'WEEKLY':
                $weekStart = $first->startOfWeek(CarbonImmutable::MONDAY)->addWeeks($offset);
                $weekdays = $byDay === [] ? [$first->dayOfWeek] : array_map(fn (string $day) => self::DAYS[substr($day, -2)] ?? $first->dayOfWeek, $byDay);
                $candidates = array_map(fn (int $weekday) => $weekStart->addDays(($weekday + 6) % 7)->setTime(...$time), $weekdays);
                usort($candidates, fn (CarbonImmutable $a, CarbonImmutable $b) => $a <=> $b);

                return [$weekStart, $candidates];

            case 'MONTHLY':
                $month = $first->startOfMonth()->addMonthsNoOverflow($offset);
                $candidates = [];
                if ($byDay !== []) {
                    foreach ($byDay as $spec) {
                        array_push($candidates, ...$this->weekdaysInMonth($month, $spec, $time));
                    }
                } else {
                    $monthDays = isset($rule['BYMONTHDAY']) ? array_map('intval', explode(',', $rule['BYMONTHDAY'])) : [$first->day];
                    foreach ($monthDays as $monthDay) {
                        $day = $monthDay < 0 ? $month->daysInMonth + $monthDay + 1 : $monthDay;
                        if ($day >= 1 && $day <= $month->daysInMonth) {
                            $candidates[] = $month->setDay($day)->setTime(...$time);
                        }
                    }
                }
                usort($candidates, fn (CarbonImmutable $a, CarbonImmutable $b) => $a <=> $b);

                return [$month, $candidates];

            case 'YEARLY':
                $year = $first->startOfYear()->addYears($offset);
                $candidates = checkdate($first->month, $first->day, $year->year) ? [$year->setDate($year->year, $first->month, $first->day)->setTime(...$time)] : [];

                return [$year, $candidates];
        }

        return [null, []];
    }

    /**
     * Keeps only the starts at the BYSETPOS positions (1 = first, -1 = last) of a period's starts, like "the last of the 28th, 29th and 30th".
     *
     * @param  array<int, CarbonImmutable>  $candidates
     * @return array<int, CarbonImmutable>
     */
    private function atSetPositions(array $candidates, ?string $positions): array
    {
        if ($positions === null || $positions === '') {
            return $candidates;
        }

        $picked = [];
        foreach (array_map('intval', explode(',', $positions)) as $position) {
            $index = $position > 0 ? $position - 1 : count($candidates) + $position;
            if ($position !== 0 && isset($candidates[$index])) {
                $picked[$index] = $candidates[$index];
            }
        }
        ksort($picked);

        return array_values($picked);
    }

    /**
     * The days in a month matching a BYDAY entry such as "TU", "2TU" or "-1FR".
     *
     * @param  array{0: int, 1: int, 2: int}  $time
     * @return array<int, CarbonImmutable>
     */
    private function weekdaysInMonth(CarbonImmutable $month, string $spec, array $time): array
    {
        if (! preg_match('/^([+-]?\d+)?(MO|TU|WE|TH|FR|SA|SU)$/', $spec, $match)) {
            return [];
        }
        $weekday = self::DAYS[$match[2]];
        $matches = [];
        for ($day = 1; $day <= $month->daysInMonth; $day++) {
            $date = $month->setDay($day)->setTime(...$time);
            if ($date->dayOfWeek === $weekday) {
                $matches[] = $date;
            }
        }
        if ($match[1] === '') {
            return $matches;
        }
        $index = (int) $match[1];
        $picked = $index > 0 ? ($matches[$index - 1] ?? null) : ($matches[count($matches) + $index] ?? null);

        return $picked === null ? [] : [$picked];
    }

    /**
     * @return array<int, array{uid: string, title: string, start: CarbonImmutable, seconds: int, all_day: bool, location: ?string, rrule: ?array<string, string>, exdates: array<int, int>, recurrence_id: ?int, cancelled: bool}>
     */
    private function parse(string $ics): array
    {
        $lines = preg_split('/\r\n|\n|\r/', preg_replace('/\r?\n[ \t]/', '', $ics)) ?: [];
        $events = [];
        $current = null;

        foreach ($lines as $line) {
            if ($line === 'BEGIN:VEVENT') {
                $current = [];

                continue;
            }
            if ($line === 'END:VEVENT') {
                if ($current !== null && ($event = $this->build($current)) !== null) {
                    $events[] = $event;
                }
                $current = null;

                continue;
            }
            if ($current === null || ! str_contains($line, ':')) {
                continue;
            }
            [$head, $value] = explode(':', $line, 2);
            $parts = explode(';', $head);
            $name = strtoupper(array_shift($parts));
            $params = [];
            foreach ($parts as $part) {
                [$key, $paramValue] = array_pad(explode('=', $part, 2), 2, '');
                $params[strtoupper($key)] = trim($paramValue, '"');
            }
            $current[$name][] = ['value' => $value, 'params' => $params];
        }

        return $events;
    }

    /**
     * @param  array<string, array<int, array{value: string, params: array<string, string>}>>  $properties
     * @return ?array{uid: string, title: string, start: CarbonImmutable, seconds: int, all_day: bool, location: ?string, rrule: ?array<string, string>, exdates: array<int, int>, recurrence_id: ?int, cancelled: bool}
     */
    private function build(array $properties): ?array
    {
        $startProperty = $properties['DTSTART'][0] ?? null;
        if ($startProperty === null) {
            return null;
        }

        try {
            $start = $this->parseValue($startProperty['value'], $startProperty['params']);
            $endProperty = $properties['DTEND'][0] ?? null;
            $seconds = $start['all_day'] ? 86400 : 0;
            if ($endProperty !== null) {
                $end = $this->parseValue($endProperty['value'], $endProperty['params']);
                $seconds = max(0, $end['date']->getTimestamp() - $start['date']->getTimestamp());
            } elseif (isset($properties['DURATION'][0])) {
                $seconds = $this->intervalSeconds($properties['DURATION'][0]['value']);
            }

            $exdates = [];
            foreach ($properties['EXDATE'] ?? [] as $exdate) {
                foreach (explode(',', $exdate['value']) as $value) {
                    $exdates[] = $this->parseValue($value, $exdate['params'], $start['date']->getTimezone())['date']->getTimestamp();
                }
            }

            $recurrenceId = null;
            if (isset($properties['RECURRENCE-ID'][0])) {
                $recurrenceId = $this->parseValue($properties['RECURRENCE-ID'][0]['value'], $properties['RECURRENCE-ID'][0]['params'], $start['date']->getTimezone())['date']->getTimestamp();
            }
        } catch (Throwable) {
            return null;
        }

        $rule = null;
        if (isset($properties['RRULE'][0])) {
            $rule = [];
            foreach (explode(';', $properties['RRULE'][0]['value']) as $part) {
                [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
                $rule[strtoupper($key)] = $value;
            }
        }

        return [
            'uid' => $properties['UID'][0]['value'] ?? spl_object_hash((object) $properties),
            'title' => $this->unescape($properties['SUMMARY'][0]['value'] ?? '') ?: '(no title)',
            'start' => $start['date'],
            'seconds' => $seconds,
            'all_day' => $start['all_day'],
            'location' => ($location = $this->unescape($properties['LOCATION'][0]['value'] ?? '')) !== '' ? $location : null,
            'rrule' => $rule,
            'exdates' => $exdates,
            'recurrence_id' => $recurrenceId,
            'cancelled' => strtoupper($properties['STATUS'][0]['value'] ?? '') === 'CANCELLED',
        ];
    }

    /**
     * @param  array<string, string>  $params
     * @return array{date: CarbonImmutable, all_day: bool}
     */
    private function parseValue(string $value, array $params, ?DateTimeZone $fallback = null): array
    {
        $fallback ??= new DateTimeZone(config('app.timezone'));

        if (strlen($value) === 8 || ($params['VALUE'] ?? '') === 'DATE') {
            return ['date' => CarbonImmutable::createFromFormat('Ymd', substr($value, 0, 8), config('app.timezone'))->startOfDay(), 'all_day' => true];
        }

        if (str_ends_with($value, 'Z')) {
            $zone = new DateTimeZone('UTC');
        } else {
            try {
                $zone = isset($params['TZID']) ? new DateTimeZone($params['TZID']) : $fallback;
            } catch (Throwable) {
                $zone = $fallback;
            }
        }

        $date = CarbonImmutable::createFromFormat('Ymd\THis', rtrim($value, 'Z'), $zone);

        return ['date' => $date, 'all_day' => false];
    }

    private function intervalSeconds(string $duration): int
    {
        $interval = new \DateInterval(ltrim($duration, '+-'));

        return $interval->d * 86400 + $interval->h * 3600 + $interval->i * 60 + $interval->s;
    }

    private function unescape(string $text): string
    {
        return trim(str_replace(['\\n', '\\N', '\\,', '\\;', '\\\\'], ["\n", "\n", ',', ';', '\\'], $text));
    }
}

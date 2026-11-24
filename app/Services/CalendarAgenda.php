<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\CalendarSource;
use App\Models\Reminder;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The next few days at a glance for the dashboard: events from the ICS feeds, Pointy bookings, open reminders and tasks that are due.
 */
class CalendarAgenda
{
    /** Days shown, today included. */
    public const DAYS = 4;

    public function __construct(private IcsCalendar $calendar) {}

    /**
     * @return array<int, array{date: CarbonImmutable, items: array<int, array{title: string, start: CarbonImmutable, end: CarbonImmutable, all_day: bool, location: ?string, color: string, kind: string}>}>
     */
    public function days(): array
    {
        $from = CarbonImmutable::now()->startOfDay();
        $to = $from->addDays(self::DAYS);
        $items = [...$this->feedEvents($from, $to), ...$this->bookings($from, $to), ...$this->reminders($from, $to), ...$this->tasks($from, $to)];

        $days = [];
        for ($offset = 0; $offset < self::DAYS; $offset++) {
            $dayStart = $from->addDays($offset);
            $dayEnd = $dayStart->addDay();
            $dayItems = array_values(array_filter($items, fn (array $item) => $item['start']->lessThan($dayEnd) && ($item['end']->greaterThan($dayStart) || $item['start']->greaterThanOrEqualTo($dayStart))));
            usort($dayItems, fn (array $a, array $b) => [! $a['all_day'], $a['start']->getTimestamp()] <=> [! $b['all_day'], $b['start']->getTimestamp()]);
            $days[] = ['date' => $dayStart, 'items' => $dayItems];
        }

        return $days;
    }

    /**
     * Downloads every feed and keeps the copy. A feed that fails keeps its last good copy.
     */
    public function refresh(): void
    {
        foreach (CalendarSource::all() as $source) {
            $this->fetch($source);
        }
    }

    /**
     * The feed's text from the cache, downloading it the first time.
     */
    private function body(CalendarSource $source): ?string
    {
        return Cache::get($source->cacheKey()) ?? $this->fetch($source);
    }

    private function fetch(CalendarSource $source): ?string
    {
        try {
            $response = Http::timeout(10)->get(preg_replace('#^webcal://#i', 'https://', $source->url));
        } catch (Throwable) {
            return null;
        }

        if ($response->failed() || ! str_contains($response->body(), 'BEGIN:VCALENDAR')) {
            return null;
        }

        Cache::forever($source->cacheKey(), $response->body());

        return $response->body();
    }

    /**
     * @return array<int, array{title: string, start: CarbonImmutable, end: CarbonImmutable, all_day: bool, location: ?string, color: string, kind: string}>
     */
    private function feedEvents(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $items = [];
        foreach (CalendarSource::orderBy('name')->get() as $source) {
            $body = $this->body($source);
            if ($body === null) {
                continue;
            }
            foreach ($this->calendar->eventsBetween($body, $from, $to) as $event) {
                $items[] = [...$event, 'color' => $source->color, 'kind' => $source->name];
            }
        }

        return $items;
    }

    /**
     * @return array<int, array{title: string, start: CarbonImmutable, end: CarbonImmutable, all_day: bool, location: ?string, color: string, kind: string}>
     */
    private function bookings(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return Booking::active()->with('type')->where('starts_at', '<', $to)->where('ends_at', '>', $from)->get()
            ->map(fn (Booking $booking) => [
                'title' => $booking->type->name.' with '.$booking->guest_name,
                'start' => CarbonImmutable::instance($booking->starts_at),
                'end' => CarbonImmutable::instance($booking->ends_at),
                'all_day' => false,
                'location' => $booking->meeting_url,
                'color' => '#34d399',
                'kind' => 'Pointy',
            ])->all();
    }

    /**
     * @return array<int, array{title: string, start: CarbonImmutable, end: CarbonImmutable, all_day: bool, location: ?string, color: string, kind: string}>
     */
    private function reminders(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return Reminder::open()->whereNull('booking_id')->where('due_at', '>=', $from)->where('due_at', '<', $to)->get()
            ->map(fn (Reminder $reminder) => [
                'title' => $reminder->title,
                'start' => CarbonImmutable::instance($reminder->due_at),
                'end' => CarbonImmutable::instance($reminder->due_at),
                'all_day' => false,
                'location' => null,
                'color' => '#fbbf24',
                'kind' => 'Reminder',
            ])->all();
    }

    /**
     * @return array<int, array{title: string, start: CarbonImmutable, end: CarbonImmutable, all_day: bool, location: ?string, color: string, kind: string}>
     */
    private function tasks(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return Task::where('status', '!=', 'done')->whereDate('due_on', '>=', $from->toDateString())->whereDate('due_on', '<', $to->toDateString())->get()
            ->map(fn (Task $task) => [
                'title' => $task->title,
                'start' => CarbonImmutable::parse($task->due_on->toDateString()),
                'end' => CarbonImmutable::parse($task->due_on->toDateString())->addDay(),
                'all_day' => true,
                'location' => null,
                'color' => '#38bdf8',
                'kind' => 'Task',
            ])->all();
    }
}

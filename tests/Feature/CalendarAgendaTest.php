<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingType;
use App\Models\CalendarSource;
use App\Models\Reminder;
use App\Models\Task;
use App\Models\User;
use App\Services\IcsCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CalendarAgendaTest extends TestCase
{
    use RefreshDatabase;

    private function ics(string ...$events): string
    {
        return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n".implode("\r\n", $events)."\r\nEND:VCALENDAR\r\n";
    }

    /** @return array<int, string> */
    private function titles(string $ics, string $from, string $to): array
    {
        return array_map(
            fn (array $event) => $event['title'].' '.($event['all_day'] ? $event['start']->format('md') : $event['start']->utc()->format('md H:i')),
            (new IcsCalendar)->eventsBetween($ics, CarbonImmutable::parse($from), CarbonImmutable::parse($to)),
        );
    }

    public function test_it_reads_single_events_in_their_own_time_zone_and_skips_those_outside_the_window(): void
    {
        $ics = $this->ics(
            "BEGIN:VEVENT\r\nUID:a\r\nSUMMARY:Dentist\\, Main St\r\nDTSTART;TZID=America/New_York:20261125T090000\r\nDTEND;TZID=America/New_York:20261125T100000\r\nEND:VEVENT",
            "BEGIN:VEVENT\r\nUID:b\r\nSUMMARY:Far away\r\nDTSTART:20261201T090000Z\r\nDTEND:20261201T100000Z\r\nEND:VEVENT",
        );

        $this->assertSame(['Dentist, Main St 1125 14:00'], $this->titles($ics, '2026-11-24 00:00', '2026-11-28 00:00'));
    }

    public function test_all_day_events_end_the_day_before_their_exclusive_end_date(): void
    {
        $ics = $this->ics("BEGIN:VEVENT\r\nUID:a\r\nSUMMARY:Trip\r\nDTSTART;VALUE=DATE:20261123\r\nDTEND;VALUE=DATE:20261125\r\nEND:VEVENT");

        $this->assertSame(['Trip 1123'], $this->titles($ics, '2026-11-24 00:00', '2026-11-25 00:00'));
        $this->assertSame([], $this->titles($ics, '2026-11-25 00:00', '2026-11-26 00:00'));
    }

    public function test_weekly_rules_skip_excluded_dates_and_follow_moved_or_cancelled_occurrences(): void
    {
        $ics = $this->ics(
            "BEGIN:VEVENT\r\nUID:standup\r\nSUMMARY:Standup\r\nDTSTART:20261102T080000Z\r\nDTEND:20261102T083000Z\r\nRRULE:FREQ=WEEKLY;BYDAY=MO,WE,FR\r\nEXDATE:20261125T080000Z\r\nEND:VEVENT",
            "BEGIN:VEVENT\r\nUID:standup\r\nRECURRENCE-ID:20261127T080000Z\r\nSUMMARY:Standup (moved)\r\nDTSTART:20261127T130000Z\r\nDTEND:20261127T133000Z\r\nEND:VEVENT",
            "BEGIN:VEVENT\r\nUID:standup\r\nRECURRENCE-ID:20261130T080000Z\r\nSTATUS:CANCELLED\r\nSUMMARY:Standup\r\nDTSTART:20261130T080000Z\r\nDTEND:20261130T083000Z\r\nEND:VEVENT",
        );

        $this->assertSame(
            ['Standup (moved) 1127 13:00'],
            $this->titles($ics, '2026-11-24 00:00', '2026-11-30 12:00'),
        );
    }

    public function test_monthly_ordinal_weekday_rules_and_counted_rules_end_when_exhausted(): void
    {
        $ics = $this->ics(
            "BEGIN:VEVENT\r\nUID:a\r\nSUMMARY:Board\r\nDTSTART:20260924T100000Z\r\nDTEND:20260924T110000Z\r\nRRULE:FREQ=MONTHLY;BYDAY=-1TH\r\nEND:VEVENT",
            "BEGIN:VEVENT\r\nUID:b\r\nSUMMARY:Course\r\nDTSTART:20261110T180000Z\r\nDTEND:20261110T190000Z\r\nRRULE:FREQ=WEEKLY;COUNT=2\r\nEND:VEVENT",
        );

        $this->assertSame(['Board 1126 10:00'], $this->titles($ics, '2026-11-24 00:00', '2026-12-01 00:00'));
        $this->assertSame(['Course 1117 18:00'], $this->titles($ics, '2026-11-16 00:00', '2026-11-24 00:00'));
    }

    public function test_the_dashboard_shows_feeds_bookings_reminders_and_due_tasks_for_the_next_six_days(): void
    {
        $this->travelTo('2026-11-24 08:00:00');
        Cache::flush();
        Http::fake(['calendar.example.com/*' => Http::response($this->ics(
            "BEGIN:VEVENT\r\nUID:a\r\nSUMMARY:Lunch with Sam\r\nDTSTART:20261125T120000Z\r\nDTEND:20261125T130000Z\r\nEND:VEVENT",
            "BEGIN:VEVENT\r\nUID:b\r\nSUMMARY:Too far ahead\r\nDTSTART:20261203T120000Z\r\nDTEND:20261203T130000Z\r\nEND:VEVENT",
        ))]);
        $this->actingAs(User::factory()->create());
        CalendarSource::factory()->create(['name' => 'Personal']);
        $type = BookingType::factory()->create(['name' => 'Intro call']);
        Booking::factory()->for($type, 'type')->create(['guest_name' => 'Lena', 'starts_at' => now()->addDay()->setHour(15), 'ends_at' => now()->addDay()->setHour(15)->addMinutes(30)]);
        Booking::factory()->for($type, 'type')->create(['guest_name' => 'Cancelled Carl', 'starts_at' => now()->addDay()->setHour(16), 'ends_at' => now()->addDay()->setHour(16)->addMinutes(30), 'cancelled_at' => now()]);
        Reminder::factory()->create(['title' => 'Renew the domain', 'due_at' => now()->addDays(2)]);
        Task::factory()->create(['title' => 'Send the invoice', 'status' => 'todo', 'due_on' => now()->addDays(5)->toDateString()]);
        Task::factory()->create(['title' => 'Finished already', 'status' => 'done', 'due_on' => now()->toDateString()]);

        $this->get(route('dashboard'))->assertOk()
            ->assertSeeInOrder(['Today', 'Tomorrow', 'Lunch with Sam', 'Intro call with Lena', 'Renew the domain', 'Send the invoice'])
            ->assertDontSee('Too far ahead')
            ->assertDontSee('Cancelled Carl')
            ->assertDontSee('Finished already');
    }

    public function test_a_broken_feed_does_not_break_the_dashboard(): void
    {
        Cache::flush();
        Http::fake(['calendar.example.com/*' => Http::response('nope', 500)]);
        $this->actingAs(User::factory()->create());
        CalendarSource::factory()->create();

        $this->get(route('dashboard'))->assertOk()->assertSee('Nothing planned.');
    }

    public function test_a_feed_can_be_added_and_removed(): void
    {
        Http::fake(['calendar.example.com/*' => Http::response($this->ics())]);
        $this->actingAs(User::factory()->create());

        $this->post(route('calendar-sources.store'), ['name' => 'Work', 'url' => 'webcal://calendar.example.com/work.ics', 'color' => '#ff0000'])->assertRedirect(route('dashboard'));
        $source = CalendarSource::firstOrFail();
        $this->assertSame('Work', $source->name);
        $this->assertNotNull(Cache::get($source->cacheKey()));

        $this->delete(route('calendar-sources.destroy', $source))->assertRedirect(route('dashboard'));
        $this->assertDatabaseCount('calendar_sources', 0);
        $this->assertNull(Cache::get($source->cacheKey()));
    }

    public function test_a_feed_address_must_be_a_web_address(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('calendar-sources.store'), ['name' => 'Bad', 'url' => 'file:///etc/passwd', 'color' => '#ff0000'])->assertSessionHasErrors('url');
        $this->assertDatabaseCount('calendar_sources', 0);
    }
}

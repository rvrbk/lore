<?php

namespace Tests\Feature;

use App\Models\AvailabilityException;
use App\Models\Booking;
use App\Models\BookingType;
use App\Models\CalendarSource;
use App\Models\JobScoutSnapshot;
use App\Models\Reminder;
use App\Models\User;
use App\Models\WorldClock;
use App\Services\SlotFinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_everything_a_signed_in_user_creates_belongs_to_them(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post(route('reminders.store'), ['title' => 'Mine', 'due_at' => now()->addDay()->toDateTimeString(), 'recurrence' => 'none']);
        $this->post(route('world-clocks.store'), ['city' => 'Tokyo (Asia)']);
        $this->post(route('calendar-sources.store'), ['name' => 'Work', 'url' => 'https://calendar.example.com/a.ics', 'color' => '#ff0000']);

        $this->assertSame($user->id, Reminder::sole()->user_id);
        $this->assertSame($user->id, WorldClock::sole()->user_id);
        $this->assertSame($user->id, CalendarSource::sole()->user_id);
    }

    public function test_users_only_see_their_own_reminders_clocks_calendars_and_meetings(): void
    {
        $mine = User::factory()->create();
        $theirs = User::factory()->create();
        Reminder::factory()->create(['user_id' => $mine->id, 'title' => 'My reminder']);
        Reminder::factory()->create(['user_id' => $theirs->id, 'title' => 'Their reminder']);
        WorldClock::factory()->create(['user_id' => $theirs->id, 'city' => 'Sydney (Australia)', 'timezone' => 'Australia/Sydney']);
        CalendarSource::factory()->create(['user_id' => $theirs->id, 'name' => 'Their calendar']);
        $type = BookingType::factory()->create(['user_id' => $theirs->id, 'name' => 'Their meeting type']);
        Booking::factory()->for($type, 'type')->create(['guest_name' => 'Their guest']);

        $this->actingAs($mine);
        $this->get(route('reminders.index'))->assertOk()->assertSee('My reminder')->assertDontSee('Their reminder');
        $this->get(route('dashboard'))->assertOk()->assertDontSee('data-world-clock="Australia/Sydney"', false)->assertDontSee('Their calendar')->assertDontSee('Their meeting type')->assertDontSee('Their guest');
        $this->get(route('pointy.index'))->assertOk()->assertDontSee('Their meeting type')->assertDontSee('Their guest');
    }

    public function test_users_cannot_change_each_others_things_by_guessing_ids(): void
    {
        $mine = User::factory()->create();
        $theirs = User::factory()->create();
        $reminder = Reminder::factory()->create(['user_id' => $theirs->id]);
        $clock = WorldClock::factory()->create(['user_id' => $theirs->id]);
        $source = CalendarSource::factory()->create(['user_id' => $theirs->id]);
        $type = BookingType::factory()->create(['user_id' => $theirs->id]);
        $exception = AvailabilityException::factory()->for($type, 'type')->create();
        $booking = Booking::factory()->for($type, 'type')->create();

        $this->actingAs($mine);
        $this->delete(route('reminders.destroy', $reminder))->assertNotFound();
        $this->post(route('reminders.complete', $reminder))->assertNotFound();
        $this->delete(route('world-clocks.destroy', $clock))->assertNotFound();
        $this->delete(route('calendar-sources.destroy', $source))->assertNotFound();
        $this->put(route('pointy.types.update', $type), [])->assertNotFound();
        $this->delete(route('pointy.types.destroy', $type))->assertNotFound();
        $this->post(route('pointy.exceptions.store', $type), ['date' => '2026-12-24'])->assertNotFound();
        $this->delete(route('pointy.exceptions.destroy', $exception))->assertNotFound();
        $this->post(route('pointy.bookings.cancel', $booking))->assertNotFound();

        $this->assertSame(1, Reminder::withoutGlobalScopes()->count());
        $this->assertSame(1, WorldClock::withoutGlobalScopes()->count());
        $this->assertSame(1, CalendarSource::withoutGlobalScopes()->count());
        $this->assertSame(1, BookingType::withoutGlobalScopes()->count());
        $this->assertFalse($booking->fresh()->isCancelled());
    }

    public function test_a_guest_booking_belongs_to_the_owner_of_the_meeting_type_and_reminds_only_them(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $type = BookingType::factory()->create(['user_id' => $owner->id, 'timezone' => 'UTC', 'days' => [1, 2, 3, 4, 5, 6, 7], 'min_notice_hours' => 0, 'start_time' => '00:00', 'end_time' => '23:59']);
        $slot = app(SlotFinder::class)->free($type)->first();

        // Someone else being signed in on the same browser must not get in the way of the guest page.
        $this->actingAs($other)->get(route('pointy.show', $type->slug))->assertOk();
        $this->actingAs($other)->post(route('pointy.book', $type->slug), ['starts_at' => $slot->toIso8601String(), 'guest_name' => 'Guest', 'guest_email' => 'guest@example.com', 'guest_timezone' => 'UTC'])->assertRedirect();

        $booking = Booking::withoutGlobalScopes()->sole();
        $this->assertSame($owner->id, $booking->user_id);
        $this->assertSame($owner->id, Reminder::withoutGlobalScopes()->sole()->user_id);
    }

    public function test_job_scout_numbers_are_kept_per_user(): void
    {
        $mine = User::factory()->create(['job_scout_email' => 'me@example.com']);
        $theirs = User::factory()->create(['job_scout_email' => 'them@example.com']);
        JobScoutSnapshot::create(['user_id' => $mine->id, 'day' => today(), 'new_matches' => 3]);
        JobScoutSnapshot::create(['user_id' => $theirs->id, 'day' => today(), 'new_matches' => 8]);

        $this->actingAs($mine)->get(route('dashboard'))->assertOk();
        $this->assertSame(1, JobScoutSnapshot::withoutGlobalScopes()->where('user_id', $mine->id)->count());
        $this->assertSame(2, JobScoutSnapshot::withoutGlobalScopes()->count());
    }
}

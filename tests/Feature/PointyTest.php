<?php

namespace Tests\Feature;

use App\Mail\BookingMail;
use App\Models\AvailabilityException;
use App\Models\Booking;
use App\Models\BookingType;
use App\Models\Reminder;
use App\Models\User;
use App\Services\SlotFinder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PointyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-23 06:00:00');
    }

    public function test_slots_follow_the_owners_hours_and_are_instants_that_show_in_the_guest_zone(): void
    {
        $type = BookingType::factory()->create(['timezone' => 'Europe/Amsterdam', 'days' => [1], 'start_time' => '09:00', 'end_time' => '10:00', 'min_notice_hours' => 0, 'max_days_ahead' => 8]);

        $slots = app(SlotFinder::class)->free($type);

        $this->assertSame(['2026-11-23 08:00', '2026-11-23 08:30', '2026-11-30 08:00', '2026-11-30 08:30'], $slots->map(fn ($slot) => $slot->format('Y-m-d H:i'))->all());
        $this->assertSame('03:00', $slots->first()->setTimezone('America/New_York')->format('H:i'));
    }

    public function test_booked_slots_minimum_notice_and_buffers_are_respected(): void
    {
        $type = BookingType::factory()->create(['timezone' => 'UTC', 'days' => [1], 'start_time' => '09:00', 'end_time' => '12:00', 'min_notice_hours' => 4, 'buffer_minutes' => 30, 'max_days_ahead' => 1]);
        Booking::factory()->create(['booking_type_id' => $type->id, 'starts_at' => Carbon::parse('2026-11-23 10:00:00', 'UTC'), 'ends_at' => Carbon::parse('2026-11-23 10:30:00', 'UTC')]);
        Booking::factory()->create(['booking_type_id' => $type->id, 'starts_at' => Carbon::parse('2026-11-23 11:00:00', 'UTC'), 'ends_at' => Carbon::parse('2026-11-23 11:30:00', 'UTC'), 'cancelled_at' => now()]);

        $times = app(SlotFinder::class)->free($type)->map(fn ($slot) => $slot->format('H:i'))->all();

        $this->assertSame(['09:00', '11:00'], $times);
    }

    public function test_a_guest_books_a_slot_and_both_sides_get_an_email_from_pointy(): void
    {
        Mail::fake();
        config(['services.pointy.owner_email' => 'owner@example.test']);
        $type = BookingType::factory()->create(['timezone' => 'UTC', 'days' => [1], 'min_notice_hours' => 0]);

        $this->get(route('pointy.show', ['slug' => $type->slug, 'tz' => 'Asia/Tokyo']))->assertOk()->assertSee('18:00');

        $this->post(route('pointy.book', $type->slug), ['starts_at' => '2026-11-23T09:00:00+00:00', 'guest_name' => 'Ann', 'guest_email' => 'ann@example.test', 'guest_timezone' => 'Asia/Tokyo'])
            ->assertRedirect();

        $booking = Booking::firstOrFail();
        $this->assertSame('2026-11-23 09:00:00', $booking->starts_at->copy()->utc()->format('Y-m-d H:i:s'));
        Mail::assertSent(BookingMail::class, fn (BookingMail $mail) => $mail->hasTo('ann@example.test') && $mail->hasFrom('pointy@verbeek.ug') && str_contains($mail->whenText(), '18:00') && str_contains($mail->whenText(), 'Asia/Tokyo'));
        Mail::assertSent(BookingMail::class, fn (BookingMail $mail) => $mail->hasTo('owner@example.test') && str_contains($mail->whenText(), '09:00'));
        $this->get(route('pointy.booking', $booking->token))->assertOk()->assertSee('18:00');
    }

    public function test_a_taken_or_invalid_slot_cannot_be_booked(): void
    {
        Mail::fake();
        $type = BookingType::factory()->create(['timezone' => 'UTC', 'days' => [1], 'min_notice_hours' => 0]);
        Booking::factory()->create(['booking_type_id' => $type->id, 'starts_at' => Carbon::parse('2026-11-23 09:00:00', 'UTC'), 'ends_at' => Carbon::parse('2026-11-23 09:30:00', 'UTC')]);
        $guest = ['guest_name' => 'Bo', 'guest_email' => 'bo@example.test', 'guest_timezone' => 'UTC'];

        $this->post(route('pointy.book', $type->slug), $guest + ['starts_at' => '2026-11-23T09:00:00Z'])->assertSessionHasErrors('starts_at');
        $this->post(route('pointy.book', $type->slug), $guest + ['starts_at' => '2026-11-23T03:00:00Z'])->assertSessionHasErrors('starts_at');

        $this->assertDatabaseCount('bookings', 1);
        Mail::assertNothingSent();
    }

    public function test_a_guest_can_cancel_with_their_link_and_the_slot_frees_up(): void
    {
        Mail::fake();
        $booking = Booking::factory()->create();

        $this->post(route('pointy.cancel', $booking->token))->assertRedirect(route('pointy.booking', $booking->token));

        $this->assertTrue($booking->fresh()->isCancelled());
        Mail::assertSent(BookingMail::class, fn (BookingMail $mail) => $mail->event === 'cancelled' && $mail->hasTo($booking->guest_email));
    }

    public function test_an_inactive_type_has_no_public_page(): void
    {
        $type = BookingType::factory()->create(['is_active' => false]);

        $this->get(route('pointy.show', $type->slug))->assertNotFound();
    }

    public function test_the_owner_manages_meeting_types_when_logged_in_only(): void
    {
        $this->get(route('pointy.index'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create());
        $this->post(route('pointy.types.store'), ['name' => 'Intro call', 'duration_minutes' => 30, 'timezone' => 'Europe/Amsterdam', 'days' => [1, 3], 'start_time' => '09:00', 'end_time' => '17:00', 'buffer_minutes' => 0, 'min_notice_hours' => 4, 'max_days_ahead' => 30, 'is_active' => 1])->assertRedirect(route('pointy.index'));

        $this->assertDatabaseHas('booking_types', ['slug' => 'intro-call', 'name' => 'Intro call']);
        $this->get(route('pointy.index'))->assertOk()->assertSee('/pointy/intro-call');
        $this->post(route('pointy.types.store'), ['name' => 'Bad', 'duration_minutes' => 30, 'timezone' => 'Nowhere/City', 'days' => [1], 'start_time' => '17:00', 'end_time' => '09:00', 'buffer_minutes' => 0, 'min_notice_hours' => 4, 'max_days_ahead' => 30])->assertSessionHasErrors(['timezone', 'end_time']);
    }

    public function test_booking_puts_a_reminder_in_lore_shortly_before_and_cancelling_removes_it(): void
    {
        Mail::fake();
        $type = BookingType::factory()->create(['name' => 'Intro call', 'timezone' => 'UTC', 'days' => [1], 'min_notice_hours' => 0]);

        $this->post(route('pointy.book', $type->slug), ['starts_at' => '2026-11-23T09:00:00Z', 'guest_name' => 'Ann', 'guest_email' => 'ann@example.test', 'guest_timezone' => 'Asia/Tokyo']);

        $reminder = Reminder::firstOrFail();
        $this->assertSame('Meeting: Intro call with Ann', $reminder->title);
        $this->assertSame('2026-11-23 08:45:00', $reminder->due_at->copy()->utc()->format('Y-m-d H:i:s'));
        $this->assertStringContainsString('ann@example.test', $reminder->notes);

        $this->post(route('pointy.cancel', Booking::firstOrFail()->token));

        $this->assertDatabaseCount('reminders', 0);
    }

    public function test_the_guest_confirmation_uses_the_notation_of_their_device_and_the_owner_gets_24_hours(): void
    {
        Mail::fake();
        config(['services.pointy.owner_email' => 'owner@example.test']);
        $type = BookingType::factory()->create(['timezone' => 'UTC', 'days' => [1], 'min_notice_hours' => 0]);

        $this->post(route('pointy.book', $type->slug), ['starts_at' => '2026-11-23T15:00:00Z', 'guest_name' => 'Ann', 'guest_email' => 'ann@example.test', 'guest_timezone' => 'UTC', 'guest_hour12' => '1']);

        Mail::assertSent(BookingMail::class, fn (BookingMail $mail) => $mail->hasTo('ann@example.test') && str_contains($mail->whenText(), '3:00 PM'));
        Mail::assertSent(BookingMail::class, fn (BookingMail $mail) => $mail->hasTo('owner@example.test') && str_contains($mail->whenText(), '15:00'));
    }

    public function test_bookings_get_the_meet_link_by_default_and_not_when_it_is_switched_off(): void
    {
        Mail::fake();
        $guest = ['guest_name' => 'Ann', 'guest_email' => 'ann@example.test', 'guest_timezone' => 'UTC'];
        $on = BookingType::factory()->create(['timezone' => 'UTC', 'days' => [1], 'min_notice_hours' => 0, 'meet_url' => 'https://meet.google.com/abc-defg-hij']);
        $off = BookingType::factory()->create(['timezone' => 'UTC', 'days' => [1], 'min_notice_hours' => 0, 'meet_url' => 'https://meet.google.com/abc-defg-hij', 'include_meet' => false]);

        $this->post(route('pointy.book', $on->slug), $guest + ['starts_at' => '2026-11-23T09:00:00Z']);
        $this->post(route('pointy.book', $off->slug), $guest + ['starts_at' => '2026-11-23T10:00:00Z']);

        $this->assertSame('https://meet.google.com/abc-defg-hij', Booking::where('booking_type_id', $on->id)->firstOrFail()->meeting_url);
        $this->assertNull(Booking::where('booking_type_id', $off->id)->firstOrFail()->meeting_url);
        $this->get(route('pointy.booking', Booking::where('booking_type_id', $on->id)->firstOrFail()->token))->assertSee('Join with Google Meet');
        $this->assertStringContainsString('https://meet.google.com/abc-defg-hij', Reminder::firstOrFail()->notes);
    }

    public function test_a_type_with_meet_on_needs_a_meet_link_somewhere(): void
    {
        $this->actingAs(User::factory()->create());
        $type = ['name' => 'Call', 'duration_minutes' => 30, 'timezone' => 'UTC', 'days' => [1], 'start_time' => '09:00', 'end_time' => '17:00', 'buffer_minutes' => 0, 'min_notice_hours' => 4, 'max_days_ahead' => 30];

        $this->post(route('pointy.types.store'), $type + ['include_meet' => 1])->assertSessionHasErrors('meet_url');
        $this->post(route('pointy.types.store'), $type + ['include_meet' => 1, 'meet_url' => 'https://example.com/x'])->assertSessionHasErrors('meet_url');
        $this->post(route('pointy.types.store'), $type)->assertSessionHasNoErrors();
        config(['services.pointy.meet_url' => 'https://meet.google.com/zzz-zzzz-zzz']);
        $this->post(route('pointy.types.store'), $type + ['name' => 'Call two', 'include_meet' => 1])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('booking_types', 2);
    }

    public function test_a_day_exception_closes_a_day_or_replaces_its_hours_or_opens_an_off_day(): void
    {
        $type = BookingType::factory()->create(['timezone' => 'UTC', 'days' => [1], 'start_time' => '09:00', 'end_time' => '10:00', 'min_notice_hours' => 0, 'max_days_ahead' => 8]);
        $slots = fn () => app(SlotFinder::class)->free($type->fresh())->map(fn ($slot) => $slot->format('m-d H:i'))->all();

        $this->assertSame(['11-23 09:00', '11-23 09:30', '11-30 09:00', '11-30 09:30'], $slots());

        AvailabilityException::factory()->create(['booking_type_id' => $type->id, 'date' => '2026-11-23']);
        AvailabilityException::factory()->create(['booking_type_id' => $type->id, 'date' => '2026-11-30', 'start_time' => '14:00', 'end_time' => '15:00']);
        AvailabilityException::factory()->create(['booking_type_id' => $type->id, 'date' => '2026-11-24', 'start_time' => '16:00', 'end_time' => '16:30']);

        $this->assertSame(['11-24 16:00', '11-30 14:00', '11-30 14:30'], $slots());
    }

    public function test_the_owner_adds_updates_and_removes_a_day_exception(): void
    {
        $type = BookingType::factory()->create();
        $this->post(route('pointy.exceptions.store', $type), ['date' => '2026-12-24'])->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create());
        $this->post(route('pointy.exceptions.store', $type), ['date' => '2026-12-24', 'note' => 'Christmas Eve'])->assertRedirect(route('pointy.index'));
        $this->assertDatabaseHas('availability_exceptions', ['booking_type_id' => $type->id, 'date' => '2026-12-24', 'start_time' => null]);

        $this->post(route('pointy.exceptions.store', $type), ['date' => '2026-12-24', 'start_time' => '10:00', 'end_time' => '12:00']);
        $this->assertDatabaseCount('availability_exceptions', 1);
        $this->assertDatabaseHas('availability_exceptions', ['date' => '2026-12-24', 'start_time' => '10:00', 'end_time' => '12:00']);
        $this->get(route('pointy.index'))->assertOk()->assertSee('Thu 24 Dec 2026');

        $this->post(route('pointy.exceptions.store', $type), ['date' => '2026-12-25', 'start_time' => '12:00', 'end_time' => '10:00'])->assertSessionHasErrors('end_time');
        $this->post(route('pointy.exceptions.store', $type), ['date' => '2026-12-25', 'start_time' => '12:00'])->assertSessionHasErrors('end_time');

        $this->delete(route('pointy.exceptions.destroy', AvailabilityException::firstOrFail()))->assertRedirect(route('pointy.index'));
        $this->assertDatabaseCount('availability_exceptions', 0);
    }

    public function test_a_weekday_can_have_its_own_hours_and_a_date_exception_still_wins(): void
    {
        $type = BookingType::factory()->create(['timezone' => 'UTC', 'days' => [1, 2], 'start_time' => '09:00', 'end_time' => '10:00', 'weekday_hours' => [1 => ['start_time' => '13:00', 'end_time' => '14:00']], 'min_notice_hours' => 0, 'max_days_ahead' => 9]);
        $slots = fn () => app(SlotFinder::class)->free($type->fresh())->map(fn ($slot) => $slot->format('m-d H:i'))->all();

        $this->assertSame(['11-23 13:00', '11-23 13:30', '11-24 09:00', '11-24 09:30', '11-30 13:00', '11-30 13:30', '12-01 09:00', '12-01 09:30'], $slots());

        AvailabilityException::factory()->create(['booking_type_id' => $type->id, 'date' => '2026-11-30', 'start_time' => '16:00', 'end_time' => '16:30']);

        $this->assertContains('11-30 16:00', $slots());
        $this->assertNotContains('11-30 13:00', $slots());
    }

    public function test_the_owner_saves_weekday_hours_and_only_for_days_that_are_on(): void
    {
        $this->actingAs(User::factory()->create());
        $base = ['name' => 'Intro call', 'duration_minutes' => 30, 'timezone' => 'Europe/Amsterdam', 'days' => [1, 5], 'start_time' => '09:00', 'end_time' => '17:00', 'buffer_minutes' => 0, 'min_notice_hours' => 4, 'max_days_ahead' => 30, 'is_active' => 1, 'include_meet' => 0];

        $this->post(route('pointy.types.store'), $base + ['weekday_hours' => [5 => ['start_time' => '09:00', 'end_time' => '12:00'], 3 => ['start_time' => '10:00', 'end_time' => '11:00'], 1 => ['start_time' => '', 'end_time' => '']]])->assertSessionHasNoErrors();

        $this->assertSame([5 => ['start_time' => '09:00', 'end_time' => '12:00']], BookingType::firstOrFail()->weekday_hours);

        $this->post(route('pointy.types.store'), $base + ['name' => 'Bad', 'weekday_hours' => [5 => ['start_time' => '12:00', 'end_time' => '09:00']]])->assertSessionHasErrors('weekday_hours.5.end_time');
        $this->post(route('pointy.types.store'), $base + ['name' => 'Half', 'weekday_hours' => [5 => ['start_time' => '12:00', 'end_time' => '']]])->assertSessionHasErrors('weekday_hours.5.end_time');
    }
}

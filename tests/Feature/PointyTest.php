<?php

namespace Tests\Feature;

use App\Mail\BookingMail;
use App\Models\Booking;
use App\Models\BookingType;
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
}

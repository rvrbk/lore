<?php

namespace Tests\Feature;

use App\Mail\BookingMail;
use App\Models\Booking;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorldClock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_time_notation_defaults_to_24_hours_and_can_be_changed(): void
    {
        $this->get(route('settings.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create());

        $this->get(route('settings.index'))->assertOk()->assertSee('System settings');
        $this->assertSame('24', Setting::timeFormat());

        $this->put(route('settings.update'), ['time_format' => '12'])->assertRedirect(route('settings.index'));
        $this->assertSame('12', Setting::timeFormat());

        $this->put(route('settings.update'), ['time_format' => '13'])->assertSessionHasErrors('time_format');
        $this->assertSame('12', Setting::timeFormat());
    }

    public function test_world_clocks_and_pointy_follow_the_chosen_notation(): void
    {
        $this->travelTo('2026-11-23 13:30:00');
        WorldClock::factory()->create(['city' => 'Tokyo (Asia)', 'timezone' => 'Asia/Tokyo']);
        $booking = Booking::factory()->create(['guest_timezone' => 'UTC', 'starts_at' => Carbon::parse('2026-11-24 15:00', 'UTC'), 'ends_at' => Carbon::parse('2026-11-24 15:30', 'UTC')]);
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))->assertSee('data-hour12="0"', false)->assertSee('21:30:00');
        $this->assertStringContainsString('15:00', (new BookingMail($booking, 'confirmed', false))->whenText());

        Setting::put(Setting::TIME_FORMAT, '12');

        $this->get(route('dashboard'))->assertSee('data-hour12="1"', false)->assertSee('9:30:00 PM');
        $this->assertStringContainsString('3:00 PM', (new BookingMail($booking, 'confirmed', false))->whenText());
        $this->get(route('pointy.booking', $booking->token))->assertSee('3:00 PM');
    }
}

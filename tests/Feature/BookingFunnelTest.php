<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\PointySite;
use App\Services\BookingFunnel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingFunnelTest extends TestCase
{
    use RefreshDatabase;

    public function test_bookings_are_set_against_the_visitors_of_the_analytics_site_with_the_same_name(): void
    {
        $site = PointySite::factory()->create(['name' => 'Verbeek']);
        $lonely = PointySite::factory()->create(['name' => 'Other']);
        Booking::factory()->count(2)->create(['pointy_site_id' => $site->id]);
        Booking::factory()->create(['pointy_site_id' => $site->id, 'cancelled_at' => now()]);
        Booking::factory()->create(['pointy_site_id' => $lonely->id]);

        $rows = app(BookingFunnel::class)->for(PointySite::orderBy('name')->get(), [['name' => 'verbeek', 'users' => 80, 'daily' => [1, 2, 3, 4, 5, 6, 7]]]);

        $this->assertSame('Other', $rows[0]['name']);
        $this->assertNull($rows[0]['users']);
        $this->assertSame(1, $rows[0]['bookings']);
        $this->assertSame('Verbeek', $rows[1]['name']);
        $this->assertSame(2, $rows[1]['bookings']);
        $this->assertSame(2.5, $rows[1]['rate']);
        $this->assertSame(2, $rows[1]['daily_bookings'][6]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorldClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorldClockTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_city_can_be_added_once_and_shows_on_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('world-clocks.store'), ['city' => 'Tokyo (Asia)'])->assertRedirect(route('dashboard'));
        $this->post(route('world-clocks.store'), ['city' => 'Tokyo (Asia)']);

        $this->assertDatabaseCount('world_clocks', 1);
        $this->assertDatabaseHas('world_clocks', ['city' => 'Tokyo (Asia)', 'timezone' => 'Asia/Tokyo']);
        $this->get(route('dashboard'))->assertOk()->assertSee('data-world-clock="Asia/Tokyo"', false);
    }

    public function test_an_unknown_city_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('world-clocks.store'), ['city' => 'Atlantis'])->assertSessionHasErrors('city');
        $this->assertDatabaseCount('world_clocks', 0);
    }

    public function test_a_clock_can_be_removed(): void
    {
        $this->actingAs(User::factory()->create());
        $clock = WorldClock::factory()->create();

        $this->delete(route('world-clocks.destroy', $clock))->assertRedirect(route('dashboard'));

        $this->assertDatabaseCount('world_clocks', 0);
    }

    public function test_clocks_require_a_login(): void
    {
        $this->post(route('world-clocks.store'), ['city' => 'Tokyo (Asia)'])->assertRedirect(route('login'));
    }
}

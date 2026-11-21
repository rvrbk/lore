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

    public function test_clocks_can_be_reordered(): void
    {
        $this->actingAs(User::factory()->create());
        $first = WorldClock::factory()->create(['position' => 1]);
        $second = WorldClock::factory()->create(['position' => 2]);

        $this->postJson(route('world-clocks.reorder'), ['order' => [$second->id, $first->id]])->assertOk();

        $this->assertSame([$second->id, $first->id], WorldClock::orderBy('position')->pluck('id')->all());
    }

    public function test_a_clock_can_be_switched_between_analog_and_digital(): void
    {
        $this->actingAs(User::factory()->create());
        $clock = WorldClock::factory()->create();

        $this->patch(route('world-clocks.style', $clock))->assertRedirect(route('dashboard'));
        $this->assertTrue($clock->fresh()->is_analog);

        $this->patch(route('world-clocks.style', $clock));
        $this->assertFalse($clock->fresh()->is_analog);
    }

    public function test_only_one_clock_can_be_shown_in_the_header(): void
    {
        $this->actingAs(User::factory()->create());
        $first = WorldClock::factory()->create();
        $second = WorldClock::factory()->create();

        $this->patch(route('world-clocks.header', $first))->assertRedirect(route('dashboard'));
        $this->patch(route('world-clocks.header', $second));

        $this->assertFalse($first->fresh()->is_header);
        $this->assertTrue($second->fresh()->is_header);
        $this->get(route('dashboard'))->assertSee('data-header-clock="'.$second->timezone.'"', false);

        $this->patch(route('world-clocks.header', $second));
        $this->get(route('dashboard'))->assertDontSee('data-header-clock', false);
    }

    public function test_clocks_require_a_login(): void
    {
        $this->post(route('world-clocks.store'), ['city' => 'Tokyo (Asia)'])->assertRedirect(route('login'));
    }
}

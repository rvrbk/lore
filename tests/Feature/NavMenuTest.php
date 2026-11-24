<?php

namespace Tests\Feature;

use App\Models\NavVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavMenuTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_visiting_a_menu_item_counts_but_polling_and_other_pages_do_not(): void
    {
        $this->get(route('tags.index'))->assertOk();
        $this->get(route('tags.index'))->assertOk();
        $this->get(route('dashboard'))->assertOk();
        $this->getJson(route('deploy.state'));

        $this->assertSame(['tags' => 2], NavVisit::where('user_id', $this->user->id)->pluck('visits', 'item')->all());
    }

    public function test_most_used_item_is_listed_first_after_home(): void
    {
        NavVisit::create(['user_id' => $this->user->id, 'item' => 'incidents', 'visits' => 5]);
        NavVisit::create(['user_id' => $this->user->id, 'item' => 'tags', 'visits' => 3]);

        $this->get(route('dashboard'))->assertSeeInOrder(['>Home</a>', 'Incidents', 'Tags', 'Kanban'], false);
    }

    public function test_menu_order_and_counts_are_per_user(): void
    {
        $other = User::factory()->create();
        NavVisit::create(['user_id' => $other->id, 'item' => 'incidents', 'visits' => 9]);

        $this->get(route('tags.index'))->assertOk();
        $this->get(route('dashboard'))->assertSeeInOrder(['>Home</a>', 'Tags', 'Incidents'], false);

        $this->assertSame(['tags' => 1], NavVisit::where('user_id', $this->user->id)->pluck('visits', 'item')->all());
        $this->assertSame(['incidents' => 9], NavVisit::where('user_id', $other->id)->pluck('visits', 'item')->all());
    }
}

<?php

namespace Tests\Feature;

use App\Models\NavVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_visiting_a_menu_item_counts_but_polling_and_other_pages_do_not(): void
    {
        $this->get(route('tags.index'))->assertOk();
        $this->get(route('tags.index'))->assertOk();
        $this->get(route('dashboard'))->assertOk();
        $this->getJson(route('deploy.state'));

        $this->assertSame(['tags' => 2], NavVisit::pluck('visits', 'item')->all());
    }

    public function test_most_used_item_is_listed_first_after_home(): void
    {
        NavVisit::create(['item' => 'incidents', 'visits' => 5]);
        NavVisit::create(['item' => 'tags', 'visits' => 3]);

        $this->get(route('dashboard'))->assertSeeInOrder(['>Home</a>', 'Incidents', 'Tags', 'Kanban'], false);
    }
}

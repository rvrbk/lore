<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PilotTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('pilot'))->assertRedirect(route('login'));
    }

    public function test_signed_in_users_get_train_the_pilot(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('pilot'))
            ->assertOk()
            ->assertSee('data-pilot', false);
    }
}

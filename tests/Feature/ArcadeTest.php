<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArcadeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('arcade'))->assertRedirect(route('login'));
    }

    public function test_signed_in_users_get_ufo_invaders(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('arcade'))
            ->assertOk()
            ->assertSee('data-invaders', false);
    }
}

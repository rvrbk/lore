<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RootCauseTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('root-cause'))->assertRedirect(route('login'));
    }

    public function test_signed_in_users_get_root_cause(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('root-cause'))
            ->assertOk()
            ->assertSee('data-root-cause', false);
    }
}

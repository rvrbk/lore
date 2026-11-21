<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CropCircleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('crop-circles'))->assertRedirect(route('login'));
    }

    public function test_signed_in_users_get_crop_circle_lab(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('crop-circles'))
            ->assertOk()
            ->assertSee('data-crop-circles', false);
    }
}

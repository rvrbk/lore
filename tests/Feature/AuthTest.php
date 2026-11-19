<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_login_and_login_is_sent_to_setup_on_first_run(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('login'))->assertRedirect(route('register'));
    }

    public function test_first_account_can_register_without_email_then_registration_closes(): void
    {
        $this->post(route('register'), ['username' => 'rik', 'password' => 'secret-pass', 'password_confirmation' => 'secret-pass'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertSame('rik', User::firstOrFail()->username);

        $this->post(route('logout'));
        $this->get(route('register'))->assertRedirect(route('login'));
        $this->post(route('register'), ['username' => 'eve', 'password' => 'secret-pass', 'password_confirmation' => 'secret-pass'])
            ->assertForbidden();
        $this->assertSame(1, User::count());
    }

    public function test_login_with_username_and_password_then_logout(): void
    {
        User::factory()->create(['username' => 'rik', 'password' => 'secret-pass']);

        $this->post(route('login'), ['username' => 'rik', 'password' => 'nope'])->assertSessionHasErrors('username');
        $this->assertGuest();

        $this->post(route('login'), ['username' => 'rik', 'password' => 'secret-pass'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->get(route('dashboard'))->assertOk()->assertSee('Log out');

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}

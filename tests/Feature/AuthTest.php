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

    public function test_anyone_can_register_with_an_email_and_registration_stays_open(): void
    {
        $this->post(route('register'), ['username' => 'rik', 'email' => 'rik@example.com', 'password' => 'secret-pass', 'password_confirmation' => 'secret-pass'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertSame('rik', User::firstOrFail()->username);

        $this->post(route('logout'));
        $this->get(route('register'))->assertOk();
        $this->post(route('register'), ['username' => 'eve', 'email' => 'eve@example.com', 'password' => 'secret-pass', 'password_confirmation' => 'secret-pass'])
            ->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertSame(2, User::count());
    }

    public function test_registering_requires_an_email(): void
    {
        $this->post(route('register'), ['username' => 'rik', 'password' => 'secret-pass', 'password_confirmation' => 'secret-pass'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame(0, User::count());
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

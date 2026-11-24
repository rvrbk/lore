<?php

namespace Tests\Feature;

use App\Models\Monitor;
use App\Models\Reminder;
use App\Models\User;
use App\Services\ExpiryWatcher;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_reset_link_is_sent_to_the_email_on_the_account_and_unknown_addresses_get_the_same_answer(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'rik@example.com']);

        $this->post(route('password.email'), ['email' => 'rik@example.com'])->assertSessionHas('status');
        $this->post(route('password.email'), ['email' => 'nobody@example.com'])->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertSentTimes(ResetPassword::class, 1);
    }

    public function test_the_link_in_the_email_leads_to_a_form_that_changes_the_password(): void
    {
        $user = User::factory()->create(['email' => 'rik@example.com', 'password' => 'old-password']);
        $token = Password::createToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => 'rik@example.com']))->assertOk()->assertSee('Choose a new password');
        $this->post(route('password.update'), ['token' => $token, 'email' => 'rik@example.com', 'password' => 'new-password', 'password_confirmation' => 'new-password'])
            ->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        $this->post(route('login'), ['username' => $user->username, 'password' => 'new-password'])->assertRedirect(route('dashboard'));
    }

    public function test_a_wrong_or_used_token_does_not_change_the_password(): void
    {
        $user = User::factory()->create(['email' => 'rik@example.com', 'password' => 'old-password']);

        $this->post(route('password.update'), ['token' => 'wrong', 'email' => 'rik@example.com', 'password' => 'new-password', 'password_confirmation' => 'new-password'])
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_registering_may_include_an_email_that_must_be_unique(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post(route('register'), ['username' => 'eve', 'email' => 'taken@example.com', 'password' => 'secret-pass', 'password_confirmation' => 'secret-pass'])->assertSessionHasErrors('email');
        $this->post(route('register'), ['username' => 'eve', 'email' => 'eve@example.com', 'password' => 'secret-pass', 'password_confirmation' => 'secret-pass'])->assertRedirect(route('dashboard'));

        $this->assertSame('eve@example.com', User::where('username', 'eve')->firstOrFail()->email);
    }

    public function test_users_set_their_own_email_and_job_scout_email_in_settings(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create(['email' => 'taken@example.com']);
        $this->actingAs($user);

        $this->get(route('settings.index'))->assertOk()->assertSee('Job Scout email');
        $this->put(route('settings.update'), ['email' => 'taken@example.com'])->assertSessionHasErrors('email');
        $this->put(route('settings.update'), ['email' => 'me@example.com', 'job_scout_email' => 'scout@example.com'])->assertRedirect(route('settings.index'));

        $this->assertSame('me@example.com', $user->fresh()->email);
        $this->assertSame('scout@example.com', $user->fresh()->job_scout_email);
        $this->assertNull($other->fresh()->job_scout_email);
    }

    public function test_domain_expiry_reminders_go_to_every_subscriber_and_nobody_else(): void
    {
        Http::fake();
        $subscriber = User::factory()->create();
        $second = User::factory()->create();
        $bystander = User::factory()->create();
        $monitor = Monitor::factory()->create(['name' => 'Shop']);
        $monitor->subscribers()->attach([$subscriber->id, $second->id]);

        app(ExpiryWatcher::class)->apply($monitor, null, CarbonImmutable::now()->addDays(60));

        $this->assertSame(3, Reminder::where('user_id', $subscriber->id)->count());
        $this->assertSame(3, Reminder::where('user_id', $second->id)->count());
        $this->assertSame(0, Reminder::where('user_id', $bystander->id)->count());
    }

    public function test_subscribing_and_unsubscribing_from_the_monitors_page(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $monitor = Monitor::factory()->create(['name' => 'Shop']);
        $this->actingAs($user);

        $this->get(route('monitors.index'))->assertSee('Subscribe to expiry reminders');
        $this->post(route('monitors.subscribe', $monitor))->assertRedirect();
        $this->assertTrue($monitor->subscribers()->whereKey($user->id)->exists());

        app(ExpiryWatcher::class)->apply($monitor, null, CarbonImmutable::now()->addDays(60));
        $unrelated = Reminder::factory()->create(['user_id' => $user->id, 'title' => 'Pay rent']);
        $this->assertSame(4, Reminder::where('user_id', $user->id)->count());

        $this->get(route('monitors.index'))->assertSee('Expiry reminders')->assertDontSee('Subscribe to expiry reminders');
        $this->delete(route('monitors.unsubscribe', $monitor))->assertRedirect();

        $this->assertFalse($monitor->subscribers()->whereKey($user->id)->exists());
        $this->assertSame([$unrelated->id], Reminder::where('user_id', $user->id)->pluck('id')->all());
    }
}

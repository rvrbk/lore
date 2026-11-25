<?php

namespace Tests\Feature;

use App\Http\Middleware\RequireTwoFactor;
use App\Models\User;
use App\Services\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UnlocksVault;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase, UnlocksVault;

    public function test_signing_in_with_two_factor_waits_for_the_code(): void
    {
        $user = $this->userWithTwoFactor();
        $user->update(['password' => 'secret-pass']);

        $this->post(route('login'), ['username' => $user->username, 'password' => 'secret-pass'])->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();

        $totp = new Totp;
        $this->post(route('two-factor.verify'), ['code' => $totp->code($user->two_factor_secret, $totp->currentStep())])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(session(RequireTwoFactor::PASSED));
    }

    public function test_a_wrong_password_or_wrong_code_does_not_sign_in(): void
    {
        $user = $this->userWithTwoFactor();
        $user->update(['password' => 'secret-pass']);

        $this->post(route('login'), ['username' => $user->username, 'password' => 'nope'])->assertSessionHasErrors('username');
        $this->post(route('login'), ['username' => $user->username, 'password' => 'secret-pass']);
        $this->post(route('two-factor.verify'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_a_code_cannot_be_used_twice(): void
    {
        $user = $this->userWithTwoFactor();
        $totp = new Totp;
        $code = $totp->code($user->two_factor_secret, $totp->currentStep());
        $user->update(['password' => 'secret-pass']);

        $this->post(route('login'), ['username' => $user->username, 'password' => 'secret-pass']);
        $this->post(route('two-factor.verify'), ['code' => $code])->assertRedirect();
        $this->post(route('logout'));

        $this->post(route('login'), ['username' => $user->username, 'password' => 'secret-pass']);
        $this->post(route('two-factor.verify'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_a_recovery_code_works_once(): void
    {
        $user = $this->userWithTwoFactor();
        $codes = $user->resetRecoveryCodes();
        $user->update(['password' => 'secret-pass']);

        $this->post(route('login'), ['username' => $user->username, 'password' => 'secret-pass']);
        $this->post(route('two-factor.verify'), ['code' => $codes[0]])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->post(route('logout'));

        $this->post(route('login'), ['username' => $user->username, 'password' => 'secret-pass']);
        $this->post(route('two-factor.verify'), ['code' => $codes[0]])->assertSessionHasErrors('code');
        $this->assertCount(9, $user->fresh()->two_factor_recovery_codes);
    }

    public function test_users_without_two_factor_sign_in_as_before(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass']);

        $this->post(route('login'), ['username' => $user->username, 'password' => 'secret-pass'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_setting_it_up_needs_a_correct_code_and_shows_recovery_codes_once(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

        $this->post(route('settings.two-factor.start'))->assertRedirect(route('settings.two-factor'));
        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertFalse($user->hasTwoFactorEnabled());

        $this->post(route('settings.two-factor.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());

        $totp = new Totp;
        $this->post(route('settings.two-factor.confirm'), ['code' => $totp->code($user->two_factor_secret, $totp->currentStep())])
            ->assertOk()->assertSee('recovery codes');
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
        $this->assertCount(10, $user->fresh()->two_factor_recovery_codes);
        $this->assertStringNotContainsString($user->two_factor_secret, (string) \DB::table('users')->value('two_factor_secret'));
    }

    public function test_turning_it_off_needs_a_recent_password_confirmation_and_locks_the_vault(): void
    {
        $user = $this->userWithTwoFactor();

        $this->unlockedAs($user)->delete(route('settings.two-factor.disable'))->assertRedirect(route('settings.two-factor'));
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
        $this->get(route('vault.index'))->assertRedirect(route('settings.two-factor'));

        $other = $this->userWithTwoFactor();
        $this->flushSession();
        $this->actingAs($other)->delete(route('settings.two-factor.disable'))->assertRedirect(route('password.confirm'));
        $this->assertTrue($other->fresh()->hasTwoFactorEnabled());
    }

    public function test_confirming_the_password_unlocks_the_next_step(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass']);
        $this->actingAs($user);

        $this->post(route('password.confirm'), ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->post(route('password.confirm'), ['password' => 'secret-pass'])->assertRedirect();
        $this->assertNotNull(session('auth.password_confirmed_at'));
    }
}

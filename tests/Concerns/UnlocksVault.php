<?php

namespace Tests\Concerns;

use App\Http\Middleware\RequireTwoFactor;
use App\Models\User;
use App\Services\Totp;

trait UnlocksVault
{
    /** A user with two-factor authentication switched on. */
    protected function userWithTwoFactor(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['two_factor_secret' => (new Totp)->generateSecret(), 'two_factor_confirmed_at' => now()])->save();

        return $user;
    }

    /** Signs the user in as someone who passed the second factor and just confirmed their password. */
    protected function unlockedAs(User $user): static
    {
        return $this->actingAs($user)->withSession([RequireTwoFactor::PASSED => true, 'auth.password_confirmed_at' => time()]);
    }

    protected function skipWithoutSodium(): void
    {
        if (! function_exists('sodium_crypto_box_seal')) {
            $this->markTestSkipped('The PHP sodium extension is not installed.');
        }
    }

    /** @return array{public: string, secret: string} base64 encoded keypair halves */
    protected function makeKeypair(): array
    {
        $keypair = sodium_crypto_box_keypair();

        return ['public' => base64_encode(sodium_crypto_box_publickey($keypair)), 'secret' => base64_encode(sodium_crypto_box_secretkey($keypair))];
    }
}

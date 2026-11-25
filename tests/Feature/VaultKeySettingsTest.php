<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Vault\VaultSealer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UnlocksVault;
use Tests\TestCase;

class VaultKeySettingsTest extends TestCase
{
    use RefreshDatabase, UnlocksVault;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipWithoutSodium();
        $this->user = User::factory()->create();
        $this->actingAs($this->user)->withSession(['auth.password_confirmed_at' => time()]);
    }

    public function test_a_user_saves_their_own_public_key_and_sees_its_fingerprint(): void
    {
        $key = VaultSealer::PUBLIC_PREFIX.$this->makeKeypair()['public'];

        $this->put(route('settings.vault-key.update'), ['vault_public_key' => $key])->assertRedirect(route('settings.vault-key'));

        $this->assertSame($key, $this->user->fresh()->vault_public_key);
        $this->get(route('settings.vault-key'))->assertOk()->assertSee(VaultSealer::fingerprint($key));
    }

    public function test_a_private_key_or_junk_is_refused(): void
    {
        $keys = $this->makeKeypair();

        $this->put(route('settings.vault-key.update'), ['vault_public_key' => VaultSealer::PRIVATE_PREFIX.$keys['secret']])
            ->assertSessionHasErrors(['vault_public_key' => 'That is your PRIVATE key. Never paste it here: keep it on your own computer and paste the public key instead.']);
        foreach (['not a key', base64_encode('too short'), base64_encode(str_repeat("\0", 32))] as $bad) {
            $this->put(route('settings.vault-key.update'), ['vault_public_key' => $bad])->assertSessionHasErrors('vault_public_key');
        }
        $this->assertNull($this->user->fresh()->vault_public_key);
    }

    public function test_changing_or_removing_the_key_needs_a_recent_password_confirmation(): void
    {
        $this->flushSession();
        $key = $this->makeKeypair()['public'];

        $this->put(route('settings.vault-key.update'), ['vault_public_key' => $key])->assertRedirect(route('password.confirm'));
        $this->delete(route('settings.vault-key.destroy'))->assertRedirect(route('password.confirm'));
        $this->assertNull($this->user->fresh()->vault_public_key);

        $this->withSession(['auth.password_confirmed_at' => time()]);
        $this->put(route('settings.vault-key.update'), ['vault_public_key' => $key]);
        $this->delete(route('settings.vault-key.destroy'))->assertRedirect(route('settings.vault-key'));
        $this->assertNull($this->user->fresh()->vault_public_key);
    }

    public function test_keys_are_per_user_and_the_private_half_is_never_stored(): void
    {
        $other = User::factory()->create();
        $other->forceFill(['vault_public_key' => $this->makeKeypair()['public']])->save();
        $keys = $this->makeKeypair();

        $this->put(route('settings.vault-key.update'), ['vault_public_key' => $keys['public']]);

        $this->assertNotSame($other->fresh()->vault_public_key, $this->user->fresh()->vault_public_key);
        $this->get(route('settings.vault-key'))->assertDontSee($other->vault_public_key);
        $this->assertStringNotContainsString($keys['secret'], json_encode(\DB::table('users')->get()));
    }

    public function test_fingerprints_and_prefixes_are_consistent(): void
    {
        $keys = $this->makeKeypair();

        $this->assertSame(VaultSealer::fingerprint($keys['public']), VaultSealer::fingerprint(VaultSealer::PUBLIC_PREFIX.$keys['public']));
        $this->assertMatchesRegularExpression('/^[0-9A-F]{4}( [0-9A-F]{4}){3}$/', VaultSealer::fingerprint($keys['public']));
        $this->assertNull(VaultSealer::fingerprint('nope'));
    }
}

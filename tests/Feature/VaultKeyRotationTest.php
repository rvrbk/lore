<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VaultItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VaultKeyRotationTest extends TestCase
{
    use RefreshDatabase;

    public function test_items_stay_readable_through_a_key_rotation_and_can_be_moved_to_the_new_key(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();
        $user->resetRecoveryCodes();
        VaultItem::factory()->create(['user_id' => $user->id, 'password' => 'rotate-me', 'username' => 'u', 'notes' => null]);
        $oldKey = config('app.key');
        $newKey = 'base64:'.base64_encode(Encrypter::generateKey(config('app.cipher')));

        $this->useKeys($newKey, [$oldKey]);
        $this->assertSame('rotate-me', VaultItem::withoutGlobalScopes()->sole()->password, 'the old key still reads old data');

        $this->artisan('vault:reencrypt')->assertSuccessful();

        $this->useKeys($newKey, []);
        $item = VaultItem::withoutGlobalScopes()->sole();
        $this->assertSame(['rotate-me', 'u', null], [$item->password, $item->username, $item->notes]);
        $this->assertSame('JBSWY3DPEHPK3PXP', $user->fresh()->two_factor_secret);
        $this->assertCount(10, $user->fresh()->two_factor_recovery_codes);
        $this->assertNull(DB::table('vault_items')->value('notes'));
    }

    /** @param list<string> $previous */
    private function useKeys(string $key, array $previous): void
    {
        config(['app.key' => $key, 'app.previous_keys' => $previous]);
        $this->app->forgetInstance('encrypter');
        Crypt::clearResolvedInstance('encrypter');
        Model::encryptUsing($this->app->make('encrypter'));
    }
}

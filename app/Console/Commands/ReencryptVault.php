<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\VaultItem;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

#[Signature('vault:reencrypt')]
#[Description('Encrypt vault items and two-factor secrets again with the current APP_KEY (run after rotating the key with APP_PREVIOUS_KEYS)')]
class ReencryptVault extends Command
{
    public function handle(): int
    {
        $items = 0;
        foreach (VaultItem::withoutGlobalScopes()->orderBy('id')->lazy() as $item) {
            DB::table('vault_items')->where('id', $item->id)->update([
                'username' => $item->username === null ? null : Crypt::encryptString($item->username),
                'password' => Crypt::encryptString($item->password),
                'notes' => $item->notes === null ? null : Crypt::encryptString($item->notes),
            ]);
            $items++;
        }

        $users = 0;
        foreach (User::whereNotNull('two_factor_secret')->orderBy('id')->lazy() as $user) {
            DB::table('users')->where('id', $user->id)->update([
                'two_factor_secret' => Crypt::encryptString($user->two_factor_secret),
                'two_factor_recovery_codes' => $user->two_factor_recovery_codes === null ? null : Crypt::encryptString(json_encode($user->two_factor_recovery_codes, JSON_THROW_ON_ERROR)),
            ]);
            $users++;
        }

        $this->info("Re-encrypted $items vault items and $users two-factor secrets. Once every backup you care about was made after this, APP_PREVIOUS_KEYS can go.");

        return self::SUCCESS;
    }
}

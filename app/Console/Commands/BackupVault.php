<?php

namespace App\Console\Commands;

use App\Services\Vault\VaultBackup;
use App\Services\Vault\VaultSealer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('vault:backup')]
#[Description('Write a backup of the password vault, sealed to the public key so the server cannot read it, and prune old ones')]
class BackupVault extends Command
{
    public function handle(VaultBackup $backup, VaultSealer $sealer): int
    {
        if (! $sealer->isAvailable()) {
            $this->error('Set VAULT_PUBLIC_KEY (see scripts/vault-keygen.php) and make sure the PHP sodium extension is installed.');
            Log::channel(config('vault.log_channel'))->error('vault backup failed: no usable public key or sodium extension');

            return self::FAILURE;
        }

        $path = $backup->create();
        Log::channel(config('vault.log_channel'))->info('vault backup written', ['file' => basename($path)]);
        $this->info('Sealed backup written to '.$path);

        return self::SUCCESS;
    }
}

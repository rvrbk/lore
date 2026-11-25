<?php

namespace App\Console\Commands;

use App\Services\Vault\VaultBackup;
use App\Services\Vault\VaultSealer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('vault:restore {file : A sealed backup (vault-*.sealed)} {--key-file= : File holding the base64 private key} {--dry-run : Only count what would be restored}')]
#[Description('Decrypt a sealed vault backup with your private key and put its items back (existing ids are overwritten)')]
class RestoreVault extends Command
{
    public function handle(VaultBackup $backup): int
    {
        $file = (string) $this->argument('file');
        $keyFile = (string) $this->option('key-file');
        if (! File::exists($file) || $keyFile === '' || ! File::exists($keyFile)) {
            $this->error('Give an existing backup file and --key-file=<path to your private key>.');

            return self::FAILURE;
        }

        try {
            $json = VaultSealer::open(File::get($file), File::get($keyFile));
            $result = $backup->restore($json, (bool) $this->option('dry-run'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! $this->option('dry-run')) {
            Log::channel(config('vault.log_channel'))->warning('vault restored', ['file' => basename($file), 'restored' => $result['restored'], 'skipped' => $result['skipped']]);
        }
        $this->info(($this->option('dry-run') ? 'Would restore ' : 'Restored ').$result['restored'].' items, skipped '.$result['skipped'].' (their user does not exist here).');

        return self::SUCCESS;
    }
}

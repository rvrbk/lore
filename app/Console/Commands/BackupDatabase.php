<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

#[Signature('lore:backup {--keep=14 : How many backups to keep}')]
#[Description('Write a consistent copy of the database (SQLite file or pg_dump) to storage/app/backups and prune old ones. Vault items are left out: vault:backup seals those.')]
class BackupDatabase extends Command
{
    public function handle(): int
    {
        $driver = DB::getDriverName();
        if (! in_array($driver, ['sqlite', 'pgsql'], true)) {
            $this->error('Backups are only implemented for SQLite and PostgreSQL.');

            return self::FAILURE;
        }

        $extension = $driver === 'sqlite' ? 'sqlite' : 'sql';
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);

        $path = $dir.'/lore-'.now()->format('Ymd-His').'.'.$extension;
        if ($driver === 'sqlite') {
            DB::statement('VACUUM INTO ?', [$path]);
            $this->leaveVaultOut($path);
        } else {
            $config = config('database.connections.pgsql');
            $result = Process::env(['PGPASSWORD' => (string) $config['password']])->run([
                'pg_dump', '--no-owner', '--exclude-table-data=vault_items', '--host', (string) $config['host'], '--port', (string) $config['port'],
                '--username', (string) $config['username'], '--file', $path, (string) $config['database'],
            ]);
            if ($result->failed()) {
                File::delete($path);
                $this->error('pg_dump failed: '.trim($result->errorOutput()));

                return self::FAILURE;
            }
        }
        chmod($path, 0640);

        $old = collect(File::glob($dir.'/lore-*.'.$extension))->sort()->reverse()->slice(max(1, (int) $this->option('keep')));
        $old->each(fn (string $file) => File::delete($file));

        $this->info('Backup written to '.$path.' ('.number_format(filesize($path) / 1024, 1).' KB), removed '.$old->count().' old.');

        return self::SUCCESS;
    }

    /**
     * Plain database backups sit on the server next to APP_KEY, so they must not contain the encrypted vault; vault:backup covers it.
     */
    private function leaveVaultOut(string $path): void
    {
        $copy = new \PDO('sqlite:'.$path);
        if ($copy->query("select 1 from sqlite_master where type = 'table' and name = 'vault_items'")->fetchColumn()) {
            $copy->exec('delete from vault_items');
            $copy->exec('vacuum');
        }
    }
}

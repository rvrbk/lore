<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

#[Signature('lore:backup {--keep=14 : How many backups to keep}')]
#[Description('Write a consistent copy of the SQLite database to storage/app/backups and prune old ones')]
class BackupDatabase extends Command
{
    public function handle(): int
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->error('Backups are only implemented for SQLite.');

            return self::FAILURE;
        }

        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);

        $path = $dir.'/lore-'.now()->format('Ymd-His').'.sqlite';
        DB::statement('VACUUM INTO ?', [$path]);
        chmod($path, 0640);

        $old = collect(File::glob($dir.'/lore-*.sqlite'))->sort()->reverse()->slice(max(1, (int) $this->option('keep')));
        $old->each(fn (string $file) => File::delete($file));

        $this->info('Backup written to '.$path.' ('.number_format(filesize($path) / 1024, 1).' KB), removed '.$old->count().' old.');

        return self::SUCCESS;
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** VACUUM cannot run inside a transaction, so this uses real migrations instead of RefreshDatabase. */
class BackupTest extends TestCase
{
    use DatabaseMigrations;

    public function test_backup_command_writes_a_database_copy_and_keeps_only_the_newest(): void
    {
        $dir = storage_path('app/backups');
        File::deleteDirectory($dir);
        File::ensureDirectoryExists($dir);
        foreach (['20200101-000000', '20200102-000000', '20200103-000000'] as $stamp) {
            File::put("$dir/lore-$stamp.sqlite", 'old');
        }

        $this->artisan('lore:backup --keep=2')->assertSuccessful();

        $files = collect(File::glob("$dir/lore-*.sqlite"))->map(fn ($f) => basename($f))->sort()->values();
        $this->assertCount(2, $files);
        $this->assertSame('lore-20200103-000000.sqlite', $files[0]);
        $this->assertStringStartsWith('SQLite format 3', File::get("$dir/".$files[1]));

        File::deleteDirectory($dir);
    }
}

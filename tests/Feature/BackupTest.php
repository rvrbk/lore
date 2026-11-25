<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VaultItem;
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

    public function test_the_plain_database_backup_leaves_the_vault_out(): void
    {
        $user = User::factory()->create();
        VaultItem::factory()->create(['user_id' => $user->id]);
        $dir = storage_path('app/backups');
        File::deleteDirectory($dir);

        $this->artisan('lore:backup')->assertSuccessful();

        $copy = new \PDO('sqlite:'.File::glob("$dir/lore-*.sqlite")[0]);
        $this->assertSame(0, (int) $copy->query('select count(*) from vault_items')->fetchColumn());
        $this->assertSame(1, (int) $copy->query('select count(*) from users')->fetchColumn());

        File::deleteDirectory($dir);
    }
}

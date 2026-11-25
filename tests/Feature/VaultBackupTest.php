<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VaultItem;
use App\Services\Vault\VaultBackupRetention;
use App\Services\Vault\VaultSealer;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Concerns\UnlocksVault;
use Tests\TestCase;

class VaultBackupTest extends TestCase
{
    use RefreshDatabase, UnlocksVault;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/lore-vault-backups-'.bin2hex(random_bytes(4));
        config(['vault.backup.path' => $this->dir]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    public function test_a_backup_restores_to_the_same_data_and_the_server_cannot_read_it(): void
    {
        $this->skipWithoutSodium();
        $keys = $this->makeKeypair();
        config(['vault.public_key' => $keys['public']]);
        $this->app->forgetInstance(VaultSealer::class);

        $alice = User::factory()->create(['username' => 'alice']);
        $bob = User::factory()->create(['username' => 'bob']);
        $revealed = now()->subDay()->startOfSecond();
        $first = VaultItem::factory()->create(['user_id' => $alice->id, 'name' => 'Bank', 'url' => 'https://bank.example', 'username' => 'alice-login', 'password' => 'backup-marker-5521', 'notes' => "multi\nline", 'last_revealed_at' => $revealed]);
        $second = VaultItem::factory()->create(['user_id' => $bob->id, 'name' => 'Mail', 'password' => 'bob-pass']);
        $before = VaultItem::withoutGlobalScopes()->orderBy('id')->get()->map->only(['id', 'user_id', 'name', 'url', 'username', 'password', 'notes'])->all();

        $this->artisan('vault:backup')->assertSuccessful();

        $files = File::glob($this->dir.'/vault-*.sealed');
        $this->assertCount(1, $files);
        $this->assertSame('0600', substr(sprintf('%o', fileperms($files[0])), -4));
        $sealed = File::get($files[0]);
        foreach (['backup-marker-5521', 'alice-login', 'Bank', 'bob-pass', config('app.key')] as $needle) {
            $this->assertStringNotContainsString($needle, $sealed);
        }
        $this->assertStringNotContainsString(config('app.key'), VaultSealer::open($sealed, $keys['secret']), 'the application key is never part of a backup');

        VaultItem::withoutGlobalScopes()->delete();
        $this->assertSame(0, DB::table('vault_items')->count());

        $keyFile = $this->dir.'/private.key';
        File::put($keyFile, $keys['secret']);
        $this->artisan('vault:restore', ['file' => $files[0], '--key-file' => $keyFile])->assertSuccessful();

        $after = VaultItem::withoutGlobalScopes()->orderBy('id')->get();
        $this->assertSame($before, $after->map->only(['id', 'user_id', 'name', 'url', 'username', 'password', 'notes'])->all());
        $this->assertTrue($revealed->equalTo($after->first()->last_revealed_at));
        $this->assertTrue($first->created_at->equalTo($after->first()->created_at));
        $this->assertStringNotContainsString('backup-marker-5521', (string) DB::table('vault_items')->where('id', $first->id)->value('password'), 'restored secrets are encrypted again');

        // Restoring twice overwrites by id instead of duplicating.
        $this->artisan('vault:restore', ['file' => $files[0], '--key-file' => $keyFile])->assertSuccessful();
        $this->assertSame(2, DB::table('vault_items')->count());
        $this->assertSame($second->id, $after->last()->id);
    }

    public function test_restore_refuses_the_wrong_key_and_a_dry_run_changes_nothing(): void
    {
        $this->skipWithoutSodium();
        $keys = $this->makeKeypair();
        config(['vault.public_key' => $keys['public']]);
        $this->app->forgetInstance(VaultSealer::class);
        VaultItem::factory()->create(['user_id' => User::factory()->create()->id]);
        $this->artisan('vault:backup')->assertSuccessful();
        $file = File::glob($this->dir.'/vault-*.sealed')[0];
        VaultItem::withoutGlobalScopes()->delete();

        $wrong = $this->dir.'/wrong.key';
        File::put($wrong, $this->makeKeypair()['secret']);
        $this->artisan('vault:restore', ['file' => $file, '--key-file' => $wrong])->assertFailed();

        $right = $this->dir.'/right.key';
        File::put($right, $keys['secret']);
        $this->artisan('vault:restore', ['file' => $file, '--key-file' => $right, '--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, DB::table('vault_items')->count());
    }

    public function test_items_of_unknown_users_are_skipped_when_restoring(): void
    {
        $this->skipWithoutSodium();
        $keys = $this->makeKeypair();
        config(['vault.public_key' => $keys['public']]);
        $this->app->forgetInstance(VaultSealer::class);
        $user = User::factory()->create(['username' => 'gone']);
        VaultItem::factory()->create(['user_id' => $user->id]);
        $this->artisan('vault:backup')->assertSuccessful();
        $file = File::glob($this->dir.'/vault-*.sealed')[0];
        $keyFile = $this->dir.'/private.key';
        File::put($keyFile, $keys['secret']);

        $user->delete();
        $this->assertSame(0, DB::table('vault_items')->count());
        $this->artisan('vault:restore', ['file' => $file, '--key-file' => $keyFile])->assertSuccessful();

        $this->assertSame(0, DB::table('vault_items')->count());
    }

    public function test_the_backup_fails_loudly_without_a_public_key(): void
    {
        config(['vault.public_key' => null]);
        $this->app->forgetInstance(VaultSealer::class);

        $this->artisan('vault:backup')->assertFailed();
        $this->assertSame([], File::glob($this->dir.'/*'));
    }

    public function test_backups_are_scheduled_daily(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'vault:backup'));

        $this->assertNotNull($event);
        $this->assertSame('30 3 * * *', $event->expression);
    }

    public function test_retention_keeps_seven_daily_and_four_weekly_backups(): void
    {
        File::ensureDirectoryExists($this->dir);
        // Two backups a day for 60 days, ending on a Wednesday.
        $now = CarbonImmutable::parse('2026-11-25 12:00:00');
        $all = [];
        for ($day = 0; $day < 60; $day++) {
            foreach (['03:30:00', '15:00:00'] as $time) {
                $at = $now->subDays($day)->setTimeFromTimeString($time);
                $all[] = $path = $this->dir.'/vault-'.$at->format('Ymd-His').'.sealed';
                File::put($path, 'x');
            }
        }
        File::put($this->dir.'/notes.txt', 'unrelated');

        $deleted = (new VaultBackupRetention)->prune($this->dir, 7, 4);

        $kept = collect(File::glob($this->dir.'/vault-*.sealed'))->map(fn ($f) => basename($f, '.sealed'))->sort()->values()->all();
        $this->assertSame(count($all) - $deleted, count($kept));
        // Daily: the newest backup of each of the last 7 days (25 Nov back to 19 Nov), always the later one of the day.
        foreach (range(0, 6) as $day) {
            $this->assertContains('vault-'.$now->subDays($day)->format('Ymd').'-150000', $kept);
        }
        $this->assertNotContains('vault-20261125-033000', $kept, 'only one backup is kept per day');
        // Weekly: the newest backup of each of the 4 most recent weeks; this week and last are already covered by the dailies.
        $this->assertContains('vault-20261115-150000', $kept);
        $this->assertContains('vault-20261108-150000', $kept);
        $this->assertCount(7 + 2, $kept);
        $this->assertFileExists($this->dir.'/notes.txt');
    }
}

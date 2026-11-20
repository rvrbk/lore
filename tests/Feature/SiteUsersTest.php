<?php

namespace Tests\Feature;

use App\Models\Monitor;
use App\Services\SiteUsers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class SiteUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_command_counts_each_site_with_a_folder(): void
    {
        Cache::flush();
        Monitor::factory()->create(['name' => 'job-scout', 'deploy_path' => '/var/www/job-scout']);
        Monitor::factory()->create(['name' => 'no-folder', 'deploy_path' => null]);
        Process::fake(['*' => Process::result('noise LORE_USER_COUNT=42')]);

        $this->artisan('sites:count-users')->assertSuccessful();

        Process::assertRanTimes(fn ($process) => $process->path === '/var/www/job-scout', 1);
        $this->assertSame([['name' => 'job-scout', 'users' => 42]], (new SiteUsers)->counts());
    }

    public function test_a_site_whose_count_fails_or_sits_outside_var_www_shows_as_unavailable(): void
    {
        Cache::flush();
        Monitor::factory()->create(['name' => 'broken', 'deploy_path' => '/var/www/broken']);
        Process::fake(['*' => Process::result('', 'boom', 1)]);

        $this->assertSame([['name' => 'broken', 'users' => null]], (new SiteUsers)->refresh());
        $this->assertNull((new SiteUsers)->countAt('/etc'));
        Process::assertRanTimes(fn () => true, 1);
    }
}

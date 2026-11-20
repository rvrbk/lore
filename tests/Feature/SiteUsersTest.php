<?php

namespace Tests\Feature;

use App\Models\Monitor;
use App\Models\User;
use App\Services\SiteUsers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class SiteUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_command_counts_each_site_with_a_folder_and_the_dashboard_shows_the_tiles(): void
    {
        Cache::flush();
        Monitor::factory()->create(['name' => 'job-scout', 'deploy_path' => '/var/www/job-scout']);
        Monitor::factory()->create(['name' => 'no-folder', 'deploy_path' => null]);
        Process::fake(['*' => Process::result('noise LORE_USER_COUNT=42')]);

        $this->artisan('sites:count-users')->assertSuccessful();

        Process::assertRanTimes(fn ($process) => $process->path === '/var/www/job-scout', 1);
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))->assertOk()->assertSeeInOrder(["rows in each site's users table", 'job-scout', '42'], false)
            ->assertDontSee('<span class="truncate text-sm font-medium text-zinc-100">no-folder</span>', false);
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

    public function test_the_section_is_hidden_until_counts_exist(): void
    {
        Cache::flush();

        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk()->assertDontSee("rows in each site's users table");
    }
}

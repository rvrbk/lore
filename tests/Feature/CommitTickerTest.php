<?php

namespace Tests\Feature;

use App\Models\Monitor;
use App\Models\User;
use App\Services\CommitLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class CommitTickerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->actingAs(User::factory()->create());
    }

    public function test_the_dashboard_ticker_shows_commit_messages_from_every_repository_newest_first(): void
    {
        Monitor::factory()->create(['name' => 'job-scout', 'deploy_path' => '/var/www/job-scout']);
        Process::fake([
            '*' => Process::sequence()
                ->push("aaa111\t1000\tAda\tOlder lore commit\n")
                ->push("bbb222\t2000\tGrace\tNewer scout commit\n"),
        ]);

        $this->get(route('dashboard'))->assertOk()
            ->assertSeeInOrder(['job-scout', 'bbb222', 'Grace', 'Newer scout commit', 'lore', 'aaa111', 'Ada', 'Older lore commit']);
    }

    public function test_the_ticker_is_hidden_and_the_dashboard_still_loads_when_git_fails(): void
    {
        Process::fake(['*' => Process::result(errorOutput: 'fatal: not a git repository', exitCode: 128)]);

        $this->assertSame([], app(CommitLog::class)->latest());
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Latest commits');
    }

    public function test_commits_are_cached_so_git_runs_once_per_repository(): void
    {
        Process::fake(['*' => Process::result("aaa111\t1000\tAda\tOne commit\n")]);

        app(CommitLog::class)->latest();
        app(CommitLog::class)->latest();

        Process::assertRanTimes(fn ($process) => true, 1);
    }
}

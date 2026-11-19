<?php

namespace Tests\Feature;

use App\Models\Deployment;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class DeployTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['services.deploy.enabled' => true]);
        $this->actingAs(User::factory()->create());
    }

    public function test_deploy_all_queues_every_registered_site_with_lore_last_and_skips_busy_ones(): void
    {
        Monitor::factory()->create(['name' => 'job-scout', 'deploy_path' => '/var/www/job-scout']);
        Monitor::factory()->create(['name' => 'verbeek-ug', 'deploy_path' => '/var/www/verbeek-ug']);
        Monitor::factory()->create(['name' => 'no-folder', 'deploy_path' => null]);

        $this->post(route('deploy.store'))->assertRedirect(route('deploy.index'));
        $this->post(route('deploy.store'));

        $this->assertSame(['job-scout', 'verbeek-ug', 'lore'], Deployment::orderBy('id')->pluck('site')->all());
        $this->get(route('deploy.index'))->assertOk()->assertSee('Waiting for the scheduler')->assertDontSee('no-folder');
    }

    public function test_a_single_site_can_be_deployed_and_unknown_paths_are_refused(): void
    {
        Monitor::factory()->create(['name' => 'job-scout', 'deploy_path' => '/var/www/job-scout']);

        $this->post(route('deploy.store'), ['path' => '/etc'])->assertStatus(422);
        $this->post(route('deploy.store'), ['path' => '/var/www/job-scout'])->assertRedirect();

        $this->assertSame(['job-scout'], Deployment::pluck('site')->all());
    }

    public function test_deploying_is_refused_when_switched_off(): void
    {
        config(['services.deploy.enabled' => false]);

        $this->post(route('deploy.store'))->assertForbidden();
        $this->get(route('deploy.index'))->assertOk()->assertSee('switched off');
    }

    public function test_the_site_form_only_accepts_folders_directly_under_var_www(): void
    {
        Http::fake();

        $this->post(route('analytics.store'), ['name' => 'x', 'url' => 'https://x.test', 'deploy_path' => '/etc/passwd'])->assertSessionHasErrors('deploy_path');
        $this->post(route('analytics.store'), ['name' => 'x', 'url' => 'https://x.test', 'deploy_path' => '/var/www/../etc'])->assertSessionHasErrors('deploy_path');
        $this->from(route('deploy.index'))->post(route('analytics.store'), ['name' => 'x', 'url' => 'https://x.test', 'deploy_path' => '/var/www/x'])->assertRedirect(route('deploy.index'));
        $this->get(route('deploy.index'))->assertSee('Add a site')->assertSee('/var/www/x');

        $this->assertSame('/var/www/x', Monitor::sole()->deploy_path);
    }

    public function test_the_scheduler_reports_how_far_behind_the_branch_is(): void
    {
        Process::fake([
            '*--abbrev-ref*' => Process::result("feature-x\n"),
            '*--short*' => Process::result("abc1234\n"),
            '*-1*--format=%s*' => Process::result("Current work\n"),
            '*fetch*' => Process::result(''),
            '*rev-list*' => Process::result("2\n"),
            '*-n*HEAD..FETCH_HEAD*' => Process::result("def5678 Second\n123abcd First\n"),
            '*' => Process::result(''),
        ]);

        $this->artisan('lore:deploy')->assertSuccessful();

        $this->get(route('deploy.index'))->assertOk()
            ->assertSee('feature-x')->assertSee('abc1234')->assertSee('2 new commits')->assertSee('def5678 Second');
    }

    public function test_a_requested_deployment_pulls_the_current_branch_and_records_the_output(): void
    {
        Process::fake([
            '*--abbrev-ref*' => Process::result("main\n"),
            '*rev-parse*HEAD*' => Process::sequence()->push(Process::result("aaaaaaa\n"))->push(Process::result("bbbbbbb\n"))->whenEmpty(Process::result("bbbbbbb\n")),
            '*diff*' => Process::result("resources/views/dashboard.blade.php\n"),
            '*' => Process::result('done'),
        ]);
        Deployment::create(['site' => 'lore', 'path' => base_path()]);

        $this->artisan('lore:deploy')->assertSuccessful();

        $deployment = Deployment::sole();
        $this->assertSame('succeeded', $deployment->status);
        $this->assertSame('main', $deployment->branch);
        $this->assertSame('aaaaaaa', $deployment->from_commit);
        $this->assertStringContainsString('Pull origin/main', $deployment->output);
        Process::assertRan(fn ($process) => $this->commandLine($process) === "'git' 'pull' '--ff-only' 'origin' 'main'");
        Process::assertRan(fn ($process) => str_contains($this->commandLine($process), "'migrate'"));
        Process::assertRan(fn ($process) => str_contains($this->commandLine($process), "'up'"));
    }

    public function test_a_failing_pull_stops_the_deployment_but_brings_the_site_back_up(): void
    {
        Process::fake([
            '*rev-parse*' => Process::result("main\n"),
            '*pull*' => Process::result('', 'error: local changes would be overwritten', 1),
            '*' => Process::result('ok'),
        ]);
        Deployment::create(['site' => 'lore', 'path' => base_path()]);

        $this->artisan('lore:deploy')->assertSuccessful();

        $deployment = Deployment::sole();
        $this->assertSame('failed', $deployment->status);
        $this->assertStringContainsString('local changes would be overwritten', $deployment->output);
        Process::assertDidntRun(fn ($process) => str_contains($this->commandLine($process), "'migrate'"));
        Process::assertRan(fn ($process) => str_contains($this->commandLine($process), "'up'"));
    }

    private function commandLine(object $process): string
    {
        return is_array($process->command) ? implode(' ', array_map(fn (string $part) => "'{$part}'", $process->command)) : $process->command;
    }

    public function test_another_laravel_site_is_deployed_in_its_own_folder_without_a_lore_backup(): void
    {
        $dir = sys_get_temp_dir().'/lore-deploy-'.uniqid();
        mkdir($dir);
        touch($dir.'/artisan');
        file_put_contents($dir.'/composer.json', '{}');
        file_put_contents($dir.'/package.json', '{"scripts":{"build":"vite build"}}');
        Process::fake(['*--abbrev-ref*' => Process::result("main\n"), '*' => Process::result('ok')]);
        Deployment::create(['site' => 'other', 'path' => $dir]);

        $this->artisan('lore:deploy')->assertSuccessful();

        $this->assertSame('succeeded', Deployment::sole()->status);
        Process::assertRan(fn ($process, $result) => $process->path === $dir && str_contains($this->commandLine($process), "'migrate'"));
        Process::assertRan(fn ($process) => $process->path === $dir && str_contains($this->commandLine($process), "'run' 'build'"));
        Process::assertDidntRun(fn ($process) => str_contains($this->commandLine($process), 'lore:backup'));

        unlink($dir.'/artisan');
        unlink($dir.'/composer.json');
        unlink($dir.'/package.json');
        rmdir($dir);
    }
}

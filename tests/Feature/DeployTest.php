<?php

namespace Tests\Feature;

use App\Models\Deployment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

    public function test_pressing_deploy_queues_one_deployment_for_the_current_branch(): void
    {
        $this->post(route('deploy.store'))->assertRedirect(route('deploy.index'));
        $this->post(route('deploy.store'));

        $this->assertSame(1, Deployment::count());
        $this->get(route('deploy.index'))->assertOk()->assertSee('Waiting for the scheduler');
    }

    public function test_deploying_is_refused_when_switched_off(): void
    {
        config(['services.deploy.enabled' => false]);

        $this->post(route('deploy.store'))->assertForbidden();
        $this->get(route('deploy.index'))->assertOk()->assertSee('switched off');
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
        Deployment::create();

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
        Deployment::create();

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
}

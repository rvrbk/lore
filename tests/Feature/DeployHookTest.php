<?php

namespace Tests\Feature;

use App\Models\Deployment;
use App\Models\Monitor;
use App\Models\User;
use App\Services\GitHubDeployHook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class DeployHookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['services.deploy.enabled' => true, 'services.github.token' => 'ghp_test', 'app.url' => 'https://lore.test']);
    }

    private function site(): Monitor
    {
        return Monitor::factory()->create(['name' => 'my-site', 'deploy_path' => '/var/www/my-site', 'deploy_token' => 'secret-token']);
    }

    private function fakeGitHub(): void
    {
        if (! extension_loaded('sodium')) {
            $this->markTestSkipped('The sodium extension is not installed.');
        }
        $keypair = sodium_crypto_box_keypair();
        Process::fake(['*' => Process::result("https://github.com/rvrbk/my-site.git\n")]);
        Http::fake([
            'api.github.com/repos/rvrbk/my-site/actions/secrets/public-key' => Http::response(['key_id' => 'k1', 'key' => base64_encode(sodium_crypto_box_publickey($keypair))]),
            'api.github.com/repos/rvrbk/my-site/actions/secrets/LORE_DEPLOY_TOKEN' => Http::response('', 201),
            'api.github.com/repos/rvrbk/my-site/contents/.github/workflows/lore-deploy.yml' => Http::sequence()->push('', 404)->push(['content' => '', 'sha' => 'abc'])->whenEmpty(Http::response(['content' => ''])),
        ]);
    }

    public function test_a_push_with_the_right_token_queues_a_deployment_that_shows_under_deploy(): void
    {
        $site = $this->site();
        Process::fake(['*' => Process::result("main\n")]);

        $this->withToken('secret-token')->postJson(route('api.deploy.hook', $site))->assertAccepted()->assertJson(['queued' => true]);

        $deployment = Deployment::sole();
        $this->assertSame(['my-site', '/var/www/my-site', 'main', 'requested'], [$deployment->site, $deployment->path, $deployment->branch, $deployment->status]);

        $this->actingAs(User::factory()->create())->get(route('deploy.index'))->assertSee('Waiting for the scheduler');
    }

    public function test_a_second_push_while_one_is_waiting_does_not_queue_another(): void
    {
        $site = $this->site();
        Process::fake(['*' => Process::result("main\n")]);

        $this->withToken('secret-token')->postJson(route('api.deploy.hook', $site))->assertAccepted();
        $this->withToken('secret-token')->postJson(route('api.deploy.hook', $site))->assertOk()->assertJson(['queued' => false]);

        $this->assertSame(1, Deployment::count());
    }

    public function test_the_hook_refuses_wrong_or_missing_tokens_sites_without_a_token_and_a_switched_off_deployer(): void
    {
        $site = $this->site();
        $tokenless = Monitor::factory()->create(['deploy_path' => '/var/www/other']);

        $this->postJson(route('api.deploy.hook', $site))->assertForbidden();
        $this->withToken('wrong')->postJson(route('api.deploy.hook', $site))->assertForbidden();
        $this->withToken('')->postJson(route('api.deploy.hook', $tokenless))->assertForbidden();

        config(['services.deploy.enabled' => false]);
        $this->withToken('secret-token')->postJson(route('api.deploy.hook', $site))->assertForbidden();

        $this->assertSame(0, Deployment::count());
    }

    public function test_the_generated_workflow_is_valid_yaml_that_posts_the_secret_to_the_hook(): void
    {
        $site = $this->site();

        $workflow = Yaml::parse(app(GitHubDeployHook::class)->workflow($site));

        $this->assertSame(['main', 'master'], $workflow['on']['push']['branches']);
        $step = $workflow['jobs']['deploy']['steps'][0];
        $this->assertSame('${{ secrets.LORE_DEPLOY_TOKEN }}', $step['env']['LORE_DEPLOY_TOKEN']);
        $this->assertStringContainsString('Authorization: Bearer $LORE_DEPLOY_TOKEN', $step['run']);
        $this->assertStringContainsString("https://lore.test/api/deploy/{$site->id}", $step['run']);
    }

    public function test_adding_a_site_commits_the_workflow_and_stores_the_secret_on_its_repository(): void
    {
        $this->fakeGitHub();
        $this->actingAs(User::factory()->create());

        $this->from(route('deploy.index'))
            ->post(route('analytics.store'), ['name' => 'my-site', 'url' => 'https://my-site.test', 'deploy_path' => '/var/www/my-site'])
            ->assertRedirect(route('deploy.index'))
            ->assertSessionHas('hook');

        $monitor = Monitor::where('name', 'my-site')->sole();
        $this->assertNotNull($monitor->deploy_token);
        Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
            && str_ends_with($request->url(), '/contents/.github/workflows/lore-deploy.yml')
            && str_contains(base64_decode($request['content']), "https://lore.test/api/deploy/{$monitor->id}")
            && str_contains(base64_decode($request['content']), 'branches: [main, master]')
            && ! str_contains(base64_decode($request['content']), $monitor->deploy_token));
        Http::assertSent(fn (Request $request) => $request->method() === 'PUT' && str_ends_with($request->url(), '/secrets/LORE_DEPLOY_TOKEN') && $request['key_id'] === 'k1');
    }

    public function test_a_site_is_still_added_when_the_workflow_cannot_be_installed(): void
    {
        Process::fake(['*' => Process::result('', 'fatal: not found', 1)]);
        Http::fake();
        $this->actingAs(User::factory()->create());

        $this->from(route('deploy.index'))
            ->post(route('analytics.store'), ['name' => 'my-site', 'url' => 'https://my-site.test', 'deploy_path' => '/var/www/my-site'])
            ->assertSessionHas('hook_error');

        $this->assertSame(1, Monitor::count());
        Http::assertNotSent(fn (Request $request) => $request->url() !== 'https://my-site.test');
    }

    public function test_the_command_sets_up_existing_sites_and_reports_failures(): void
    {
        $this->fakeGitHub();
        $this->site();
        Monitor::factory()->create(['deploy_path' => null]);

        $this->artisan('lore:install-deploy-hook')->expectsOutputToContain('my-site: set up on rvrbk/my-site')->assertSuccessful();

        Process::fake(['*' => Process::result('', '', 1)]);
        $this->artisan('lore:install-deploy-hook my-site')->expectsOutputToContain('has no GitHub origin remote')->assertFailed();
    }
}

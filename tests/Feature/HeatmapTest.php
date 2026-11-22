<?php

namespace Tests\Feature;

use App\Models\HeatClick;
use App\Models\Monitor;
use App\Models\User;
use App\Services\GitHubHeatmapSnippet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class HeatmapTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function click(Monitor $monitor, array $overrides = [], ?string $origin = null): TestResponse
    {
        $payload = json_encode(array_merge(['k' => $monitor->heat_key, 'p' => '/pricing?utm=x', 'x' => 500, 'y' => 320, 'w' => 1440], $overrides));

        return $this->call('POST', route('api.heat.store'), [], [], [], array_filter([
            'CONTENT_TYPE' => 'text/plain',
            'HTTP_ORIGIN' => $origin,
        ]), $payload);
    }

    public function test_every_new_site_gets_its_own_heat_key(): void
    {
        $this->assertNotSame(Monitor::factory()->create()->heat_key, Monitor::factory()->create()->heat_key);
        $this->assertNotEmpty(Monitor::factory()->create()->heat_key);
    }

    public function test_a_click_from_the_site_is_stored_without_its_query_string(): void
    {
        $monitor = Monitor::factory()->create(['url' => 'https://example.com']);

        $this->click($monitor, origin: 'https://www.example.com')->assertNoContent();

        $click = HeatClick::sole();
        $this->assertSame([$monitor->id, '/pricing', 500, 320, 'desktop'], [$click->monitor_id, $click->path, $click->x, $click->y, $click->device]);
    }

    public function test_a_narrow_viewport_counts_as_mobile(): void
    {
        $monitor = Monitor::factory()->create();

        $this->click($monitor, ['w' => 390])->assertNoContent();

        $this->assertSame('mobile', HeatClick::sole()->device);
    }

    public function test_clicks_with_an_unknown_key_another_origin_or_bad_data_are_ignored(): void
    {
        $monitor = Monitor::factory()->create(['url' => 'https://example.com']);

        $this->click($monitor, ['k' => 'nope'])->assertNoContent();
        $this->click($monitor, origin: 'https://evil.test')->assertNoContent();
        $this->click($monitor, ['x' => 5000])->assertNoContent();

        $this->assertSame(0, HeatClick::count());
    }

    public function test_the_heatmap_pages_show_clicks_per_site_and_page(): void
    {
        $monitor = Monitor::factory()->create(['name' => 'my-site', 'url' => 'https://example.com']);
        HeatClick::factory()->count(3)->for($monitor)->create(['path' => '/pricing']);
        $this->actingAs(User::factory()->create());

        $this->get(route('analytics.index'))->assertOk()->assertSee('my-site')->assertSee('3');
        $this->get(route('analytics.heatmaps.show', $monitor))->assertOk()->assertSee('Clicks per day')->assertSee('3 clicks', false)->assertSee('/pricing (3)', false);
    }

    public function test_the_heatmap_pages_need_a_login(): void
    {
        $this->get(route('analytics.index'))->assertRedirect(route('login'));
    }

    public function test_the_tracker_tag_goes_before_head_and_is_not_added_twice(): void
    {
        $monitor = Monitor::factory()->create(['heat_key' => 'abc']);
        config(['app.url' => 'https://lore.test']);
        $snippet = app(GitHubHeatmapSnippet::class);
        $html = "<html>\n<head>\n    <title>x</title>\n</head>\n</html>";

        $once = $snippet->withTag($html, $monitor);

        $this->assertStringContainsString('<script defer src="https://lore.test/heat.js" data-key="abc" data-lore-heat></script>', $once);
        $this->assertLessThan(strpos($once, '</head>'), strpos($once, 'heat.js'));
        $this->assertSame($once, $snippet->withTag($once, $monitor));
    }

    public function test_installing_commits_the_tag_to_each_layout_with_a_head(): void
    {
        config(['services.github.token' => 'ghp_test', 'app.url' => 'https://lore.test']);
        $monitor = Monitor::factory()->create(['deploy_path' => '/var/www/my-site', 'heat_key' => 'abc']);
        Process::fake(['*' => Process::result("https://github.com/rvrbk/my-site.git\n")]);
        Http::fake([
            'api.github.com/repos/rvrbk/my-site/contents/resources/views/app.blade.php' => Http::sequence()
                ->push(['content' => base64_encode("<head>\n</head>"), 'sha' => 's1'])
                ->push(['content' => ''], 200),
            'api.github.com/repos/rvrbk/my-site/contents/*' => Http::response('', 404),
        ]);

        $changed = app(GitHubHeatmapSnippet::class)->install($monitor);

        $this->assertSame(['resources/views/app.blade.php'], $changed);
        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && str_contains(base64_decode($request['content']), 'data-key="abc"')
            && $request['sha'] === 's1');
    }

    public function test_the_site_page_draws_clicks_on_a_plain_grid_without_any_screenshot(): void
    {
        $monitor = Monitor::factory()->create(['url' => 'https://example.com']);
        HeatClick::factory()->for($monitor)->create(['path' => '/pricing', 'y' => 100]);
        $this->actingAs(User::factory()->create());

        $this->get(route('analytics.heatmaps.show', $monitor))->assertOk()->assertSee('heat-canvas', false)->assertDontSee('<iframe', false)->assertDontSee('screenshot', false);
    }

    public function test_lore_pages_carry_the_tracker_of_the_site_served_on_their_own_host(): void
    {
        Monitor::factory()->create(['url' => 'http://localhost/', 'heat_key' => 'lorekey']);
        $this->actingAs(User::factory()->create());

        $this->get(route('analytics.index'))->assertOk()->assertSee('data-key="lorekey"', false)->assertSee('All sites');
    }
}

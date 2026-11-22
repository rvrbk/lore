<?php

namespace Tests\Feature;

use App\Models\HeatClick;
use App\Models\Monitor;
use App\Models\User;
use App\Services\GitHubHeatmapSnippet;
use App\Services\PageScreenshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
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

        $this->get(route('heatmaps.index'))->assertOk()->assertSee('my-site')->assertSee('3');
        $this->get(route('heatmaps.show', $monitor))->assertOk()->assertSee('/pricing (3)', false)->assertSee('https://example.com/pricing');
    }

    public function test_the_heatmap_pages_need_a_login(): void
    {
        $this->get(route('heatmaps.index'))->assertRedirect(route('login'));
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

    private function fakeChromium(): void
    {
        config(['services.heatmap.chromium' => '/bin/sh']);
        Storage::fake('local');
        Process::fake(function ($process) {
            $argument = collect($process->command)->first(fn ($part) => str_starts_with($part, '--screenshot='));
            file_put_contents(substr($argument, strlen('--screenshot=')), base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNgYGD4DwABBAEAwS2OUAAAAABJRU5ErkJggg=='));

            return Process::result();
        });
    }

    public function test_the_screenshot_command_captures_clicked_pages_once_until_they_go_stale(): void
    {
        $this->fakeChromium();
        $monitor = Monitor::factory()->create(['url' => 'https://example.com']);
        HeatClick::factory()->for($monitor)->create(['path' => '/pricing', 'y' => 2000]);

        $this->artisan('heatmaps:screenshots')->assertSuccessful();
        $this->artisan('heatmaps:screenshots')->assertSuccessful();

        Process::assertRanTimes(fn ($process) => in_array('https://example.com/pricing', $process->command, true)
            && in_array('--window-size=1280,2600', $process->command, true), 1);
        Storage::disk('local')->assertExists("screenshots/{$monitor->id}/desktop-".sha1('/pricing').'.png');

        $this->travel(PageScreenshot::STALE_AFTER_DAYS + 1)->days();
        $this->artisan('heatmaps:screenshots')->assertSuccessful();
        Process::assertRanTimes(fn ($process) => in_array('https://example.com/pricing', $process->command, true), 2);
    }

    public function test_the_heatmap_page_draws_on_the_screenshot_when_there_is_one(): void
    {
        $this->fakeChromium();
        $monitor = Monitor::factory()->create(['url' => 'https://example.com']);
        HeatClick::factory()->for($monitor)->create(['path' => '/pricing', 'y' => 100]);
        $this->actingAs(User::factory()->create());

        $this->get(route('heatmaps.show', $monitor))->assertOk()->assertSee('<iframe', false)->assertDontSee('<img id="heat-frame"', false);

        $this->post(route('heatmaps.refresh', $monitor), ['path' => '/pricing', 'device' => 'desktop'])->assertRedirect();
        $this->get(route('heatmaps.show', $monitor))->assertOk()->assertSee('<img id="heat-frame"', false);
        $this->get(route('heatmaps.screenshot', ['monitor' => $monitor, 'path' => '/pricing', 'device' => 'desktop']))->assertOk();
        $this->get(route('heatmaps.screenshot', ['monitor' => $monitor, 'path' => '/other']))->assertNotFound();
    }

    public function test_paths_that_are_not_plain_url_paths_are_never_captured(): void
    {
        $this->fakeChromium();
        $monitor = Monitor::factory()->create();

        $this->assertFalse(app(PageScreenshot::class)->capture($monitor, '/a b;rm', 'desktop', 1200));
        Process::assertNothingRan();
    }
}

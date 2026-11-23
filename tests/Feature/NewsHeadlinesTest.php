<?php

namespace Tests\Feature;

use App\Models\NewsItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NewsHeadlinesTest extends TestCase
{
    use RefreshDatabase;

    private function feed(string ...$titles): string
    {
        return '<?xml version="1.0"?><rss><channel>'.collect($titles)->map(fn (string $title) => "<item><title>{$title}</title></item>")->implode('').'</channel></rss>';
    }

    public function test_the_headlines_endpoint_returns_the_feed_titles_and_caches_them(): void
    {
        Cache::flush();
        config(['services.news.feed' => 'https://news.example/rss.xml', 'services.news.ufo_feed' => '', 'services.news.world_feed' => '', 'services.news.tech_feed' => '', 'services.news.extra_feeds' => []]);
        Http::fake(['news.example/*' => Http::response($this->feed('First &amp; foremost', 'Second story'))]);
        $this->actingAs(User::factory()->create());

        $this->getJson(route('news.headlines'))->assertOk()->assertExactJson(['items' => [['kind' => 'headline', 'tag' => null, 'text' => 'First & foremost'], ['kind' => 'headline', 'tag' => null, 'text' => 'Second story']]]);
        $this->getJson(route('news.headlines'))->assertOk();

        Http::assertSentCount(1);
    }

    public function test_a_broken_feed_gives_no_headlines_and_is_not_cached(): void
    {
        Cache::flush();
        config(['services.news.feed' => 'https://news.example/rss.xml', 'services.news.ufo_feed' => '', 'services.news.world_feed' => '', 'services.news.tech_feed' => '', 'services.news.extra_feeds' => []]);
        Http::fake(['news.example/*' => Http::sequence()->push('nope', 500)->push($this->feed('Back online'))]);
        $this->actingAs(User::factory()->create());

        $this->getJson(route('news.headlines'))->assertOk()->assertExactJson(['items' => []]);
        $this->getJson(route('news.headlines'))->assertOk()->assertExactJson(['items' => [['kind' => 'headline', 'tag' => null, 'text' => 'Back online']]]);
    }

    public function test_pushed_nwsfd_news_is_mixed_with_the_public_feed_and_needs_relevance_five_and_alerts_expire_after_eight_hours(): void
    {
        Cache::flush();
        config(['services.news.feed' => 'https://news.example/rss.xml', 'services.news.ufo_feed' => 'https://ufo.example/rss.xml', 'services.news.world_feed' => 'https://world.example/rss.xml', 'services.news.tech_feed' => 'https://tech.example/rss.xml', 'services.news.extra_feeds' => [['url' => 'https://more.example/rss.xml', 'tag' => 'Science']]]);
        Http::fake(['news.example/*' => Http::response($this->feed('BBC one', 'BBC two', 'BBC three')), 'ufo.example/*' => Http::response($this->feed('Lights over Phoenix')), 'world.example/*' => Http::response($this->feed('Guardian one')), 'tech.example/*' => Http::response($this->feed('New chip announced')), 'more.example/*' => Http::response($this->feed('Guardian one', 'Comet spotted'))]);
        NewsItem::factory()->create(['title' => 'Strike at Schiphol', 'category' => 'Travel', 'source_name' => 'NOS', 'relevance' => 5]);
        NewsItem::factory()->create(['title' => 'Too low', 'relevance' => 4]);
        NewsItem::factory()->alert()->create(['title' => 'Ebola outbreak in Kampala', 'source_name' => 'Reuters', 'published_at' => now()->subHours(2)]);
        NewsItem::factory()->alert()->create(['title' => 'Stale alert', 'published_at' => now()->subHours(9)]);
        NewsItem::factory()->create(['title' => 'Too old', 'published_at' => now()->subDays(3)]);
        $this->actingAs(User::factory()->create());

        $this->getJson(route('news.headlines'))->assertOk()->assertExactJson(['items' => [
            ['kind' => 'alert', 'tag' => null, 'text' => 'Ebola outbreak in Kampala (Reuters)'],
            ['kind' => 'headline', 'tag' => null, 'text' => 'BBC one'],
            ['kind' => 'news', 'tag' => 'Tech', 'text' => 'New chip announced'],
            ['kind' => 'headline', 'tag' => null, 'text' => 'Guardian one'],
            ['kind' => 'news', 'tag' => 'UFO', 'text' => 'Lights over Phoenix'],
            ['kind' => 'news', 'tag' => 'Travel', 'text' => 'Strike at Schiphol (NOS)'],
            ['kind' => 'headline', 'tag' => null, 'text' => 'BBC two'],
            ['kind' => 'news', 'tag' => 'Science', 'text' => 'Comet spotted'],
            ['kind' => 'headline', 'tag' => null, 'text' => 'BBC three'],
        ]]);
    }

    public function test_nwsfd_can_push_items_with_the_shared_token(): void
    {
        config(['services.nwsfd.token' => 'secret-token']);
        $item = ['id' => 'abc', 'title' => 'Fuel prices jump', 'source_name' => 'NOS', 'region' => 'netherlands', 'category' => 'Energy', 'url' => 'https://nos.nl/x', 'relevance' => 8, 'alert' => true, 'published_at' => now()->getTimestampMs()];
        NewsItem::factory()->create(['external_id' => 'old', 'published_at' => now()->subDays(10)]);

        $this->postJson(route('api.news.store'), ['items' => [$item]], ['Authorization' => 'Bearer secret-token'])->assertOk()->assertJson(['stored' => 1]);
        $this->postJson(route('api.news.store'), ['items' => [array_merge($item, ['title' => 'Fuel prices jump again'])]], ['Authorization' => 'Bearer secret-token'])->assertOk();

        $this->assertDatabaseCount('news_items', 1);
        $this->assertDatabaseHas('news_items', ['external_id' => 'abc', 'title' => 'Fuel prices jump again', 'is_alert' => true]);
    }

    public function test_the_push_is_refused_without_the_right_token_or_when_unconfigured(): void
    {
        $payload = ['items' => []];

        $this->postJson(route('api.news.store'), $payload)->assertStatus(503);

        config(['services.nwsfd.token' => 'secret-token']);
        $this->postJson(route('api.news.store'), $payload)->assertUnauthorized();
        $this->postJson(route('api.news.store'), $payload, ['Authorization' => 'Bearer wrong'])->assertUnauthorized();
        $this->postJson(route('api.news.store'), ['items' => [['title' => 'no id']]], ['Authorization' => 'Bearer secret-token'])->assertUnprocessable();
    }

    public function test_the_headlines_endpoint_requires_a_login(): void
    {
        $this->get(route('news.headlines'))->assertRedirect(route('login'));
    }
}

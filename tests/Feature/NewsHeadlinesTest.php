<?php

namespace Tests\Feature;

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
        config(['services.news.feed' => 'https://news.example/rss.xml']);
        Http::fake(['news.example/*' => Http::response($this->feed('First &amp; foremost', 'Second story'))]);
        $this->actingAs(User::factory()->create());

        $this->getJson(route('news.headlines'))->assertOk()->assertExactJson(['headlines' => ['First & foremost', 'Second story']]);
        $this->getJson(route('news.headlines'))->assertOk();

        Http::assertSentCount(1);
    }

    public function test_a_broken_feed_gives_no_headlines_and_is_not_cached(): void
    {
        Cache::flush();
        config(['services.news.feed' => 'https://news.example/rss.xml']);
        Http::fake(['news.example/*' => Http::sequence()->push('nope', 500)->push($this->feed('Back online'))]);
        $this->actingAs(User::factory()->create());

        $this->getJson(route('news.headlines'))->assertOk()->assertExactJson(['headlines' => []]);
        $this->getJson(route('news.headlines'))->assertOk()->assertExactJson(['headlines' => ['Back online']]);
    }

    public function test_the_headlines_endpoint_requires_a_login(): void
    {
        $this->get(route('news.headlines'))->assertRedirect(route('login'));
    }
}

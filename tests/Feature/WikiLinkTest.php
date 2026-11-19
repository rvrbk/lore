<?php

namespace Tests\Feature;

use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WikiLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_wiki_links_resolve_prefer_the_current_space_and_missing_titles_offer_creation(): void
    {
        $ops = Space::create(['name' => 'Ops']);
        $other = Space::create(['name' => 'Other']);
        $otherTarget = $other->pages()->create(['title' => 'Deploys', 'body' => 'other']);
        $target = $ops->pages()->create(['title' => 'Deploys', 'body' => 'ops']);
        $source = $ops->pages()->create(['title' => 'Home', 'body' => "See [[deploys]] and [[Deploys|the guide]] and [[Nope]].\n\nCode: `[[Deploys]]`\n\n```\n[[Deploys]]\n```"]);

        $html = $source->html();

        $this->assertStringContainsString('<a href="'.route('pages.show', $target).'">deploys</a>', $html);
        $this->assertStringContainsString('<a href="'.route('pages.show', $target).'">the guide</a>', $html);
        $this->assertStringNotContainsString(route('pages.show', $otherTarget), $html);
        $this->assertStringContainsString(e(route('pages.create', ['space' => $ops, 'title' => 'Nope'])), str_replace('&amp;', '&amp;', $html));
        $this->assertSame(2, substr_count($html, '[[Deploys]]'), 'Inline code and fenced code stay untouched.');
    }

    public function test_page_lists_the_pages_that_link_to_it(): void
    {
        $space = Space::create(['name' => 'Ops']);
        $target = $space->pages()->create(['title' => 'Runbook 100%']);
        $space->pages()->create(['title' => 'Alpha', 'body' => 'go to [[Runbook 100%]]']);
        $space->pages()->create(['title' => 'Beta', 'body' => 'see [[Runbook 100%|the runbook]]']);
        $space->pages()->create(['title' => 'Unrelated', 'body' => 'mentions Runbook 100% without a link']);

        $this->assertSame(['Alpha', 'Beta'], $target->backlinks()->pluck('title')->all());
        $response = $this->get(route('pages.show', $target))->assertOk()->assertSee('Linked from');
        $this->assertSame(['Alpha', 'Beta'], $response->viewData('backlinks')->pluck('title')->all());
    }
}

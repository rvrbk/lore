<?php

namespace Tests\Feature;

use App\Models\Monitor;
use App\Models\Page;
use App\Models\Space;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_tag_names_are_normalised_and_unused_tags_are_removed(): void
    {
        $this->assertSame(['ops', 'on-call', 'v20'], Tag::normalize('Ops, #on call, ops, , V2.0'));

        $space = Space::create(['name' => 'Ops']);
        $page = $space->pages()->create(['title' => 'A']);
        $page->syncTagNames('alpha, beta');
        $this->assertSame('alpha, beta', $page->fresh()->tagList());

        $page->syncTagNames('beta');
        $this->assertSame(['beta'], Tag::pluck('name')->all(), 'A tag nothing uses any more is deleted.');
    }

    public function test_pages_and_tasks_can_be_tagged_browsed_and_filtered(): void
    {
        $space = Space::create(['name' => 'Ops']);
        $this->post(route('pages.store', $space), ['title' => 'Runbook', 'body' => 'x', 'tags' => 'server, Ops'])->assertRedirect();
        $this->post(route('tasks.store'), ['title' => 'Patch box', 'tags' => 'server']);
        $this->post(route('tasks.store'), ['title' => 'Write post', 'tags' => 'blog']);

        $this->get(route('tags.index'))->assertOk()->assertSee('#server')->assertSee('#blog');
        $this->get(route('tags.show', 'server'))->assertOk()->assertSee('Runbook')->assertSee('Patch box')->assertDontSee('Write post');
        $this->get(route('tasks.index', ['tag' => 'server']))->assertOk()->assertSee('Patch box')->assertDontSee('Write post');
        $this->get(route('pages.show', Page::firstOrFail()))->assertSee('#server', false);

        $this->put(route('pages.update', Page::firstOrFail()), ['title' => 'Runbook', 'body' => 'x', 'tags' => '']);
        $this->assertSame('', Page::firstOrFail()->tagList());
        $this->assertSame(['server', 'blog'], Task::with('tags')->get()->flatMap->tags->pluck('name')->all());
    }

    public function test_tag_suggestions_combine_tags_sites_and_pages_without_duplicates(): void
    {
        $space = Space::create(['name' => 'Ops']);
        $space->pages()->create(['title' => 'Server Runbook'])->syncTagNames('server');
        $space->pages()->create(['title' => 'Server']);
        Monitor::create(['name' => 'Fuganda', 'url' => 'https://example.com']);

        $this->getJson(route('tags.suggestions'))
            ->assertOk()
            ->assertExactJson([
                ['name' => 'server', 'kind' => 'tag'],
                ['name' => 'fuganda', 'kind' => 'site'],
                ['name' => 'server-runbook', 'kind' => 'page'],
            ]);
    }
}

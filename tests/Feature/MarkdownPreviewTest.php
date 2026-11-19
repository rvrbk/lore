<?php

namespace Tests\Feature;

use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkdownPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_preview_renders_like_a_saved_page_including_links_and_stripping_scripts(): void
    {
        $space = Space::create(['name' => 'Ops']);
        $page = $space->pages()->create(['title' => 'Runbook']);

        $html = $this->postJson(route('markdown.preview'), ['body' => "# Hi <script>x</script>\n\nSee [[Runbook]]", 'space' => $space->slug])->assertOk()->json('html');

        $this->assertStringContainsString('<h1>Hi', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString(route('pages.show', $page), $html);
        $this->postJson(route('markdown.preview'), [])->assertOk()->assertJson(['html' => '']);

        auth()->logout();
        $this->postJson(route('markdown.preview'), ['body' => 'x'])->assertUnauthorized();
    }
}

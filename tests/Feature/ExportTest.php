<?php

namespace Tests\Feature;

use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    /**
     * @return array<string, string> zip entry name => contents
     */
    private function zipEntries($response): array
    {
        $zip = new ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
        }

        return $entries;
    }

    public function test_a_space_exports_as_nested_markdown_files(): void
    {
        $space = Space::create(['name' => 'Ops']);
        $parent = $space->pages()->create(['title' => 'Servers', 'body' => '# Servers']);
        $parent->syncTagNames('infra');
        $space->pages()->create(['parent_id' => $parent->id, 'title' => 'a/b: "c"', 'body' => 'child']);
        $space->pages()->create(['title' => 'Servers', 'body' => 'dupe']);

        $response = $this->get(route('export.space', $space))->assertOk()->assertHeader('Content-Type', 'application/zip');
        $entries = $this->zipEntries($response);

        $this->assertEqualsCanonicalizing(['Servers.md', 'Servers/a-b- -c.md', 'Servers (2).md'], array_keys($entries));
        $this->assertStringContainsString('tags: [infra]', $entries['Servers.md']);
        $this->assertStringContainsString("---\n\n# Servers", $entries['Servers.md']);
    }

    public function test_export_everything_puts_each_space_in_its_own_folder(): void
    {
        Space::create(['name' => 'Ops'])->pages()->create(['title' => 'One']);
        Space::create(['name' => 'Home'])->pages()->create(['title' => 'Two']);

        $entries = $this->zipEntries($this->get(route('export.all'))->assertOk());

        $this->assertEqualsCanonicalizing(['Home/Two.md', 'Ops/One.md'], array_keys($entries));
        auth()->logout();
        $this->get(route('export.all'))->assertRedirect(route('login'));
    }
}

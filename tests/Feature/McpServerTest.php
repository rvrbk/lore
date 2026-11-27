<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Reminder;
use App\Models\Space;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class McpServerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['email' => 'me@example.com']);
        Sanctum::actingAs($this->user);
    }

    /**
     * Call a tool through the HTTP endpoint and return the decoded JSON it answered with.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function callTool(string $tool, array $arguments = []): array
    {
        $response = $this->postJson('/mcp/lore', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => $tool, 'arguments' => $arguments],
        ])->assertOk();

        $result = $response->json('result');
        $text = $result['content'][0]['text'] ?? '';

        return ['error' => (bool) ($result['isError'] ?? false) || $response->json('error') !== null, 'text' => $text, 'data' => json_decode($text, true) ?? [], 'raw' => $response->json()];
    }

    public function test_the_endpoint_needs_a_token(): void
    {
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', '')->postJson('/mcp/lore', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertUnauthorized();
    }

    public function test_pages_can_be_created_updated_listed_and_deleted(): void
    {
        $space = Space::create(['name' => 'Ops']);

        $created = $this->callTool('create-page-tool', ['space' => 'ops', 'title' => 'Runbook', 'body' => 'Step one', 'tags' => ['Ops']])['data'];
        $this->assertSame(['ops'], $created['tags']);
        $page = Page::findOrFail($created['id']);
        $this->assertSame(1, $page->revisions()->count());

        $child = $this->callTool('create-page-tool', ['space' => 'ops', 'title' => 'Child', 'parent_id' => $page->id])['data'];
        $this->callTool('update-page-tool', ['id' => $page->id, 'body' => 'Step two', 'parent_id' => $child['id']]);
        $this->assertSame('Step one', $page->refresh()->body, 'a page cannot move under its own child');

        $this->callTool('update-page-tool', ['id' => $page->id, 'body' => 'Step two']);
        $this->assertSame('Step two', $page->refresh()->body);
        $this->assertSame(2, $page->revisions()->count());
        $this->assertSame(['ops'], $page->tags->pluck('name')->all(), 'tags are kept when not passed');

        $this->assertSame(['Runbook'], array_column($this->callTool('list-pages-tool', ['query' => 'two'])['data']['pages'], 'title'));
        $this->assertSame('Step two', $this->callTool('get-page-tool', ['id' => $page->id])['data']['body']);

        $this->callTool('delete-page-tool', ['id' => $page->id]);
        $this->assertModelMissing($page);
        $this->assertSame($space->id, Page::find($child['id'])->space_id);
        $this->assertTrue($this->callTool('get-page-tool', ['id' => $page->id])['error']);
    }

    public function test_tasks_move_between_columns_and_complete_their_reminder(): void
    {
        $a = $this->callTool('create-task-tool', ['title' => 'A', 'remind_at' => now()->addDay()->toIso8601String(), 'tags' => ['work']])['data'];
        $b = $this->callTool('create-task-tool', ['title' => 'B'])['data'];
        $this->assertSame([1, 2], [$a['position'], $b['position']]);
        $this->assertNotNull($a['remind_at']);

        $this->callTool('update-task-tool', ['id' => $b['id'], 'position' => 0]);
        $this->assertSame(['B', 'A'], array_column($this->callTool('list-tasks-tool', ['status' => 'todo'])['data']['tasks'], 'title'));

        $done = $this->callTool('update-task-tool', ['id' => $a['id'], 'status' => 'done', 'priority' => 'high'])['data'];
        $this->assertSame(['done', 'high'], [$done['status'], $done['priority']]);
        $this->assertNull($done['remind_at']);
        $this->assertSame(1, Reminder::whereNotNull('completed_at')->count());
        $this->assertSame(['work'], $done['tags']);

        $this->assertTrue($this->callTool('create-task-tool', ['title' => 'X', 'status' => 'nope'])['error']);

        $this->callTool('delete-task-tool', ['id' => $b['id']]);
        $this->assertSame(1, Task::count());
    }

    public function test_reminders_can_be_created_rescheduled_completed_and_deleted(): void
    {
        $due = now()->addDay()->startOfHour();
        $made = $this->callTool('create-reminder-tool', ['title' => 'Call Ada', 'due_at' => $due->toIso8601String(), 'recurrence' => 'weekly'])['data'];
        $reminder = Reminder::findOrFail($made['id']);
        $this->assertSame($this->user->id, $reminder->user_id);

        $this->assertTrue($this->callTool('create-reminder-tool', ['title' => 'Bad', 'due_at' => $due->toIso8601String(), 'recurrence' => 'hourly'])['error']);

        $reminder->update(['notified_at' => now()]);
        $this->callTool('update-reminder-tool', ['id' => $reminder->id, 'title' => 'Call Ada back', 'due_at' => $due->copy()->addHours(2)->toIso8601String()]);
        $this->assertSame('Call Ada back', $reminder->refresh()->title);
        $this->assertNull($reminder->notified_at);

        $this->callTool('update-reminder-tool', ['id' => $reminder->id, 'completed' => true]);
        $this->assertNull($reminder->refresh()->completed_at, 'a recurring reminder rolls forward instead');
        $this->assertTrue($reminder->due_at->isAfter($due->copy()->addDays(6)));

        $once = $this->callTool('create-reminder-tool', ['title' => 'Once', 'due_at' => $due->toIso8601String()])['data'];
        $this->callTool('update-reminder-tool', ['id' => $once['id'], 'completed' => true]);
        $this->assertSame(['Call Ada back'], array_column($this->callTool('list-reminders-tool')['data']['reminders'], 'title'));
        $this->assertCount(2, $this->callTool('list-reminders-tool', ['include_completed' => true])['data']['reminders']);
        $this->callTool('update-reminder-tool', ['id' => $once['id'], 'completed' => false]);
        $this->assertNull(Reminder::find($once['id'])->completed_at);

        $this->callTool('delete-reminder-tool', ['id' => $once['id']]);
        $this->assertNull(Reminder::find($once['id']));
    }

    public function test_reminders_of_other_users_are_invisible(): void
    {
        $theirs = Reminder::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->assertTrue($this->callTool('get-reminder-tool', ['id' => $theirs->id])['error']);
        $this->assertTrue($this->callTool('update-reminder-tool', ['id' => $theirs->id, 'title' => 'Mine now'])['error']);
        $this->assertTrue($this->callTool('delete-reminder-tool', ['id' => $theirs->id])['error']);
        $this->assertSame([], $this->callTool('list-reminders-tool')['data']['reminders']);
        $this->assertModelExists($theirs);
    }

    public function test_tokens_are_made_and_revoked_on_the_settings_page_after_confirming_the_password(): void
    {
        $this->get(route('settings.mcp'))->assertOk()->assertSee(route('mcp.lore'));

        $this->post(route('settings.mcp.store'), ['name' => 'Laptop'])->assertRedirect(route('password.confirm'));
        $this->withSession(['auth.password_confirmed_at' => time()]);

        $this->post(route('settings.mcp.store'), ['name' => ''])->assertSessionHasErrors('name');
        $this->post(route('settings.mcp.store'), ['name' => 'Laptop'])->assertRedirect(route('settings.mcp'))->assertSessionHas('new_token');
        $this->get(route('settings.mcp'))->assertSee('Laptop')->assertSee('never used');

        $token = $this->user->tokens()->firstOrFail();
        $stranger = User::factory()->create()->createToken('theirs')->accessToken;
        $this->delete(route('settings.mcp.destroy', $stranger->id))->assertNotFound();
        $this->delete(route('settings.mcp.destroy', $token->id))->assertRedirect(route('settings.mcp'));
        $this->assertSame(0, $this->user->tokens()->count());
        $this->assertSame(1, $stranger->newQuery()->count());
    }
}

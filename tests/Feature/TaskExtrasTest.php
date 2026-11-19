<?php

namespace Tests\Feature;

use App\Models\Space;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskExtrasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_tasks_can_link_a_page_and_keep_a_checklist(): void
    {
        $page = Space::create(['name' => 'Ops'])->pages()->create(['title' => 'Runbook']);

        $this->post(route('tasks.store'), ['title' => 'Ship it', 'page_id' => $page->id]);
        $task = Task::firstOrFail();
        $this->put(route('tasks.update', $task), [
            'title' => 'Ship it', 'status' => 'todo', 'page_id' => $page->id, 'recurrence' => 'none',
            'checklist' => [['text' => 'Build', 'done' => '1'], ['text' => 'Deploy'], ['text' => '  ']],
        ])->assertRedirect(route('tasks.index'));

        $task->refresh();
        $this->assertSame([['text' => 'Build', 'done' => true], ['text' => 'Deploy', 'done' => false]], $task->checklist);
        $this->assertSame(['done' => 1, 'total' => 2], $task->checklistProgress());
        $this->get(route('tasks.index'))->assertSee('☑ 1/2')->assertSee('↗ Runbook');
        $this->get(route('pages.show', $page))->assertSee('Ship it');

        $this->post(route('tasks.store'), ['title' => 'Bad', 'page_id' => 999])->assertSessionHasErrors('page_id');
    }

    public function test_finishing_a_recurring_task_creates_the_next_one(): void
    {
        $this->travelTo('2026-03-04 10:00:00');
        $task = Task::createAtBottom(['title' => 'Water plants', 'status' => 'in_progress', 'due_on' => '2026-03-01', 'recurrence' => 'weekly', 'checklist' => [['text' => 'Ferns', 'done' => true]]]);
        $task->syncTagNames('home');

        $this->postJson(route('tasks.move', $task), ['status' => 'done', 'position' => 0])->assertOk();

        $this->assertSame(2, Task::count());
        $task->refresh();
        $this->assertSame('done', $task->status);
        $this->assertSame('none', $task->recurrence, 'The finished task no longer repeats.');

        $next = Task::where('status', 'todo')->firstOrFail();
        $this->assertSame('Water plants', $next->title);
        $this->assertSame('weekly', $next->recurrence);
        $this->assertSame('2026-03-08', $next->due_on->format('Y-m-d'), 'The next due date is in the future.');
        $this->assertSame([['text' => 'Ferns', 'done' => false]], $next->checklist);
        $this->assertSame('home', $next->tagList());

        $this->postJson(route('tasks.move', $task), ['status' => 'done', 'position' => 0]);
        $this->assertSame(2, Task::count(), 'Moving within Done does not spawn again.');
    }

    public function test_one_off_tasks_do_not_repeat(): void
    {
        $task = Task::createAtBottom(['title' => 'Once', 'status' => 'todo']);
        $this->postJson(route('tasks.move', $task), ['status' => 'done', 'position' => 0]);
        $this->assertSame(1, Task::count());
    }
}

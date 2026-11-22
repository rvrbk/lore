<?php

namespace Tests\Feature;

use App\Models\Reminder;
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

    public function test_a_task_reminder_can_be_set_moved_and_cleared(): void
    {
        $task = Task::create(['title' => 'Call dentist']);
        $payload = ['title' => 'Call dentist', 'status' => 'todo', 'recurrence' => 'none'];

        $this->put(route('tasks.update', $task), $payload + ['remind_at' => '2030-01-02T09:00'])->assertRedirect();
        $reminder = Reminder::where('task_id', $task->id)->sole();
        $this->assertSame('Call dentist', $reminder->title);

        $this->put(route('tasks.update', $task), $payload + ['remind_at' => '2030-01-03T10:30']);
        $this->assertSame(1, Reminder::where('task_id', $task->id)->count());
        $this->assertSame('2030-01-03 10:30', $reminder->fresh()->due_at->format('Y-m-d H:i'));

        $this->put(route('tasks.update', $task), $payload + ['remind_at' => '']);
        $this->assertSame(0, Reminder::where('task_id', $task->id)->count());
    }

    public function test_finishing_a_task_completes_its_reminder(): void
    {
        $task = Task::create(['title' => 'Pay rent']);
        $task->syncReminder('2030-01-02T09:00');

        $task->moveTo('done', 0);

        $this->assertNotNull(Reminder::where('task_id', $task->id)->sole()->completed_at);
    }

    public function test_reminders_can_be_linked_to_a_task_and_follow_its_deletion(): void
    {
        $task = Task::create(['title' => 'Renew domain']);

        $this->post(route('reminders.store'), [
            'title' => 'Renew domain', 'due_at' => '2030-01-02T09:00', 'recurrence' => 'none', 'task_id' => $task->id,
        ])->assertRedirect();
        $this->assertSame($task->id, Reminder::sole()->task_id);
        $this->get(route('reminders.index'))->assertOk()->assertSee('Task: Renew domain');

        $task->delete();
        $this->assertSame(0, Reminder::count());
    }

    public function test_a_new_reminder_without_a_task_does_not_get_a_board_entry(): void
    {
        $page = Space::create(['name' => 'Ops'])->pages()->create(['title' => 'Runbook']);

        $this->post(route('reminders.store'), [
            'title' => 'Review runbook', 'due_at' => '2030-01-02T09:00', 'recurrence' => 'none', 'page_id' => $page->id,
        ])->assertRedirect();

        $this->assertSame(0, Task::count());
        $this->assertNull(Reminder::sole()->task_id);
    }

    public function test_a_reminder_for_an_existing_task_does_not_add_another_card(): void
    {
        $task = Task::create(['title' => 'Existing']);

        $this->post(route('reminders.store'), [
            'title' => 'Existing', 'due_at' => '2030-01-02T09:00', 'recurrence' => 'none', 'task_id' => $task->id,
        ]);

        $this->assertSame(1, Task::count());
    }
}

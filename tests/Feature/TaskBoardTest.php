<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskBoardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    /**
     * @return array<int, string>
     */
    private function titles(string $status): array
    {
        return Task::where('status', $status)->orderBy('position')->pluck('title')->all();
    }

    public function test_board_shows_tasks_in_their_columns_and_new_tasks_land_at_the_bottom(): void
    {
        $this->post(route('tasks.store'), ['title' => 'First', 'status' => 'todo'])->assertRedirect(route('tasks.index'));
        $this->post(route('tasks.store'), ['title' => 'Second', 'status' => 'todo']);
        $this->post(route('tasks.store'), ['title' => 'Shipped', 'status' => 'done']);
        $this->post(route('tasks.store'), ['title' => 'Bad', 'status' => 'nonsense'])->assertSessionHasErrors('status');

        $this->assertSame(['First', 'Second'], $this->titles('todo'));
        $this->get(route('tasks.index'))->assertOk()->assertSeeInOrder(['To do', 'First', 'Second', 'In progress', 'Done', 'Shipped']);
    }

    public function test_dragging_moves_a_task_between_columns_and_reorders_both(): void
    {
        $a = Task::createAtBottom(['title' => 'A', 'status' => 'todo']);
        $b = Task::createAtBottom(['title' => 'B', 'status' => 'todo']);
        $c = Task::createAtBottom(['title' => 'C', 'status' => 'todo']);
        $x = Task::createAtBottom(['title' => 'X', 'status' => 'in_progress']);
        $y = Task::createAtBottom(['title' => 'Y', 'status' => 'in_progress']);

        $this->postJson(route('tasks.move', $b), ['status' => 'in_progress', 'position' => 1])->assertOk();
        $this->assertSame(['A', 'C'], $this->titles('todo'));
        $this->assertSame(['X', 'B', 'Y'], $this->titles('in_progress'));
        $this->assertSame([0, 1], Task::where('status', 'todo')->orderBy('position')->pluck('position')->all());

        $this->postJson(route('tasks.move', $c), ['status' => 'todo', 'position' => 0])->assertOk();
        $this->assertSame(['C', 'A'], $this->titles('todo'));

        $this->postJson(route('tasks.move', $a), ['status' => 'done', 'position' => 99])->assertOk();
        $this->assertSame(['A'], $this->titles('done'));
        $this->postJson(route('tasks.move', $a), ['status' => 'bogus', 'position' => 0])->assertJsonValidationErrors('status');
    }

    public function test_task_can_be_edited_into_another_column_and_deleted(): void
    {
        $task = Task::createAtBottom(['title' => 'Write docs', 'status' => 'todo']);
        Task::createAtBottom(['title' => 'Other', 'status' => 'done']);

        $this->put(route('tasks.update', $task), ['title' => 'Write better docs', 'status' => 'done', 'priority' => 'high', 'due_on' => '2030-01-02', 'description' => 'soon'])
            ->assertRedirect(route('tasks.index'));

        $task->refresh();
        $this->assertSame(['Other', 'Write better docs'], $this->titles('done'));
        $this->assertSame('high', $task->priority);
        $this->assertSame('2030-01-02', $task->due_on->format('Y-m-d'));

        $this->delete(route('tasks.destroy', $task));
        $this->assertSame(1, Task::count());
    }

    public function test_guests_cannot_use_the_board(): void
    {
        auth()->logout();
        $this->get(route('tasks.index'))->assertRedirect(route('login'));
        $this->postJson(route('tasks.move', Task::factory()->create()), ['status' => 'done', 'position' => 0])->assertUnauthorized();
    }
}

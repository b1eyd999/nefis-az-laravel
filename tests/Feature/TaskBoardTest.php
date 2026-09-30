<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The work board on the phone: tasks with a day they are due, and notes.
 */
class TaskBoardTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['name' => 'Admin JM', 'role' => User::ADMIN]);
        $this->manager = User::factory()->create(['name' => 'İsa Abbasov', 'role' => User::MANAGER]);
    }

    /** A photograph the size a phone sends. */
    private function shot(): UploadedFile
    {
        $im = imagecreatetruecolor(1200, 900);
        imagefill($im, 0, 0, imagecolorallocate($im, 200, 120, 60));
        ob_start();
        imagejpeg($im);

        return UploadedFile::fake()->createWithContent('IMG_0431.jpg', (string) ob_get_clean());
    }

    public function test_the_board_is_for_everyone_who_works_here_and_nobody_else(): void
    {
        $this->get(route('phone.tasks.index'))->assertRedirect(route('login'));

        $customer = User::factory()->create(['role' => User::CUSTOMER]);
        $this->actingAs($customer)->get(route('phone.tasks.index'))->assertForbidden();

        $this->actingAs($this->manager)->get(route('phone.tasks.index'))->assertOk()->assertSee('İşlər');
    }

    public function test_a_task_is_written_down_with_its_day_and_its_person(): void
    {
        $this->actingAs($this->owner)->post(route('phone.tasks.store'), [
            'kind' => 'task',
            'title' => 'Lent almaq',
            'body' => 'Atlas, qırmızı',
            'due_date' => now()->addDay()->toDateString(),
            'due_time' => '18:30',
            'user_id' => $this->manager->id,
        ])->assertRedirect();

        $task = Task::firstOrFail();
        $this->assertSame('Lent almaq', $task->title);
        $this->assertSame('todo', $task->status);
        $this->assertSame($this->manager->id, $task->user_id);
        $this->assertSame($this->owner->id, $task->created_by);
        $this->assertSame('18:30', $task->due_at->format('H:i'));
        $this->assertSame('sabah 18:30', $task->dueLabel());

        $this->actingAs($this->manager)->get(route('phone.tasks.index'))
            ->assertOk()
            ->assertSee('Lent almaq')
            ->assertSee('Atlas, qırmızı');
    }

    public function test_one_button_moves_a_task_along_and_back(): void
    {
        $this->actingAs($this->owner)->post(route('phone.tasks.store'), ['kind' => 'task', 'title' => 'Çap et']);
        $task = Task::firstOrFail();

        $this->actingAs($this->manager)->patch(route('phone.tasks.update', $task), ['advance' => 1]);
        $this->assertSame('doing', $task->fresh()->status);
        $this->assertNull($task->fresh()->done_at);

        $this->actingAs($this->manager)->patch(route('phone.tasks.update', $task), ['advance' => 1]);
        $done = $task->fresh();
        $this->assertSame('done', $done->status);
        $this->assertNotNull($done->done_at, 'the day it was finished is stamped, not typed');

        $this->actingAs($this->manager)->patch(route('phone.tasks.update', $task), ['advance' => 1]);
        $this->assertSame('todo', $task->fresh()->status);
        $this->assertNull($task->fresh()->done_at);
    }

    public function test_a_late_task_says_so_and_is_shown_first(): void
    {
        $this->actingAs($this->owner)->post(route('phone.tasks.store'), [
            'kind' => 'task', 'title' => 'Gecikən iş', 'due_date' => now()->subDays(2)->toDateString(),
        ]);
        $this->actingAs($this->owner)->post(route('phone.tasks.store'), [
            'kind' => 'task', 'title' => 'Sonrakı iş', 'due_date' => now()->addDays(5)->toDateString(),
        ]);

        $late = Task::where('title', 'Gecikən iş')->firstOrFail();
        $this->assertTrue($late->isOverdue());
        $this->assertSame('2 gün gecikib', $late->dueLabel());

        $html = $this->actingAs($this->owner)->get(route('phone.tasks.index'))->assertOk()->getContent();
        $this->assertLessThan(
            strpos($html, 'Sonrakı iş'),
            strpos($html, 'Gecikən iş'),
            'what is late belongs at the top'
        );
        $this->assertStringContainsString('Gecikib', $html);
    }

    public function test_mine_shows_only_what_was_given_to_me(): void
    {
        $this->actingAs($this->owner)->post(route('phone.tasks.store'), [
            'kind' => 'task', 'title' => 'Mənim işim', 'user_id' => $this->manager->id,
        ]);
        $this->actingAs($this->owner)->post(route('phone.tasks.store'), ['kind' => 'task', 'title' => 'Hamının işi']);

        $this->actingAs($this->manager)->get(route('phone.tasks.index', ['mine' => 1]))
            ->assertOk()
            ->assertSee('Mənim işim')
            ->assertDontSee('Hamının işi');
    }

    public function test_a_note_is_kept_without_a_day_or_a_state(): void
    {
        $this->actingAs($this->owner)->post(route('phone.tasks.store'), [
            'kind' => 'note', 'title' => 'Kuryerin nömrəsi', 'body' => '+994 50 000 00 00',
            // Even if the form sends them, a note has no use for these.
            'due_date' => now()->addDay()->toDateString(), 'user_id' => $this->manager->id,
        ])->assertRedirect();

        $note = Task::notes()->firstOrFail();
        $this->assertNull($note->due_at);
        $this->assertNull($note->user_id);

        $this->actingAs($this->owner)->get(route('phone.tasks.index', ['tab' => 'notes']))
            ->assertOk()
            ->assertSee('Kuryerin nömrəsi')
            ->assertSee('+994 50 000 00 00');
    }

    public function test_a_picture_can_be_kept_with_the_work_and_taken_off_again(): void
    {
        Storage::fake('public');

        $this->actingAs($this->owner)->post(route('phone.tasks.store'), [
            'kind' => 'task', 'title' => 'Bu lenti al', 'photos' => [$this->shot(), $this->shot()],
        ])->assertRedirect();

        $task = Task::firstOrFail();
        $this->assertCount(2, $task->photos);
        foreach ($task->photos as $path) {
            $this->assertStringStartsWith('tasks/', $path);
            Storage::disk('public')->assertExists($path);
        }

        $this->actingAs($this->owner)->get(route('phone.tasks.index'))
            ->assertOk()
            ->assertSee(\App\Support\Media::url($task->photos[0]), false);

        // One taken off, one more added.
        $dropped = $task->photos[0];
        $this->actingAs($this->owner)->patch(route('phone.tasks.update', $task), [
            'title' => 'Bu lenti al', 'remove' => [$dropped], 'photos' => [$this->shot()],
        ])->assertRedirect();

        $after = $task->fresh();
        $this->assertCount(2, $after->photos);
        $this->assertNotContains($dropped, $after->photos);
        Storage::disk('public')->assertMissing($dropped);

        // Deleting the task takes its pictures with it.
        $left = $after->photos;
        $this->actingAs($this->owner)->delete(route('phone.tasks.destroy', $after));
        foreach ($left as $path) {
            Storage::disk('public')->assertMissing($path);
        }
    }

    public function test_a_task_can_be_changed_and_deleted(): void
    {
        $this->actingAs($this->owner)->post(route('phone.tasks.store'), ['kind' => 'task', 'title' => 'Köhnə ad']);
        $task = Task::firstOrFail();

        $this->actingAs($this->owner)->patch(route('phone.tasks.update', $task), [
            'title' => 'Yeni ad', 'status' => 'doing', 'due_date' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertSame('Yeni ad', $task->fresh()->title);
        $this->assertSame('doing', $task->fresh()->status);

        $this->actingAs($this->owner)->delete(route('phone.tasks.destroy', $task))->assertRedirect();
        $this->assertSame(0, Task::count());
    }
}

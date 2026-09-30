<?php

namespace App\Http\Controllers\Phone;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use App\Support\ImageStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * The work board on the phone: what has to be done, by when and by whom, and
 * the notes beside it.
 *
 * Everything is shared — three people work here, and a task somebody else
 * finished is exactly what the third wants to see. A task may be given to
 * someone; one nobody was given is anybody's.
 */
class TaskController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->query('tab') === 'notes' ? 'notes' : 'tasks';
        $mine = $request->boolean('mine');
        $me = $request->user();

        $tasks = Task::tasks()
            ->with(['owner:id,name', 'author:id,name'])
            ->when($mine, fn ($q) => $q->where('user_id', $me->id))
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        $open = $tasks->reject->isDone();

        return view('phone.tasks', [
            'tab' => $tab,
            'mine' => $mine,
            'groups' => [
                'Gecikib' => $open->filter->isOverdue()->values(),
                'Bu gün' => $open->filter(fn (Task $t) => $t->due_at && ! $t->isOverdue() && $t->due_at->isToday())->values(),
                'Yaxın günlər' => $open->filter(fn (Task $t) => $t->due_at && ! $t->isOverdue() && ! $t->due_at->isToday())->values(),
                'Vaxtsız' => $open->filter(fn (Task $t) => $t->due_at === null)->values(),
            ],
            // Yesterday's finished work is worth seeing; last month's is not.
            'done' => $tasks->filter->isDone()
                ->filter(fn (Task $t) => $t->done_at === null || $t->done_at->gt(now()->subDays(7)))
                ->sortByDesc('done_at')->values(),
            'notes' => Task::notes()->with('author:id,name')->orderByDesc('updated_at')->get(),
            'staff' => User::query()
                ->where(fn ($q) => $q->whereIn('role', [User::ADMIN, User::MANAGER])->orWhere('is_admin', true))
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:task,note'],
            'title' => ['required', 'string', 'max:160'],
            'body' => ['nullable', 'string', 'max:4000'],
            'due_date' => ['nullable', 'date'],
            'due_time' => ['nullable', 'string', 'max:5'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'photos' => ['nullable', 'array', 'max:6'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
        ], [], ['title' => 'Başlıq']);

        Task::create([
            'kind' => $data['kind'],
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'status' => 'todo',
            'due_at' => $data['kind'] === Task::TASK ? self::when($data) : null,
            'user_id' => $data['kind'] === Task::TASK ? ($data['user_id'] ?? null) : null,
            'created_by' => $request->user()->id,
            'photos' => self::keep($request->file('photos', [])) ?: null,
        ]);

        return back()->with('phone.flash', [
            'title' => $data['kind'] === Task::NOTE ? 'Qeyd yazıldı' : 'İş əlavə olundu',
        ]);
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        // One button moves it on; the sheet changes everything else.
        if ($request->filled('advance')) {
            $task->forceFill(['status' => $task->nextStatus()])->save();

            return back()->with('phone.flash', ['title' => $task->title, 'body' => $task->statusLabel()]);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['nullable', 'string', 'max:4000'],
            'due_date' => ['nullable', 'date'],
            'due_time' => ['nullable', 'string', 'max:5'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'in:todo,doing,done'],
            'photos' => ['nullable', 'array', 'max:6'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
            'remove' => ['nullable', 'array'],
            'remove.*' => ['string'],
        ], [], ['title' => 'Başlıq']);

        /* What was ticked for removal leaves the list and the disk; what was
           chosen is added to what is already there. */
        $gone = $data['remove'] ?? [];
        $kept = collect($task->photos ?? [])->reject(fn (string $path) => in_array($path, $gone, true));
        foreach ($gone as $path) {
            if (in_array($path, $task->photos ?? [], true)) {
                Storage::disk('public')->delete($path);
            }
        }
        $photos = $kept->merge(self::keep($request->file('photos', [])))->values()->all();

        $task->fill([
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'status' => $task->kind === Task::NOTE ? $task->status : ($data['status'] ?? $task->status),
            'due_at' => $task->kind === Task::NOTE ? null : self::when($data),
            'user_id' => $task->kind === Task::NOTE ? null : ($data['user_id'] ?? null),
            'photos' => $photos ?: null,
        ])->save();

        return back()->with('phone.flash', ['title' => 'Yadda saxlanıldı', 'body' => $task->title]);
    }

    public function destroy(Task $task): RedirectResponse
    {
        $was = $task->title;
        $task->delete();

        return back()->with('phone.flash', ['title' => 'Silindi', 'body' => $was]);
    }

    /**
     * Keeps the pictures small: a telephone's photograph is four thousand
     * pixels wide, and this hosting carries every one of them.
     *
     * @param  array<int, \Illuminate\Http\UploadedFile>  $files
     * @return array<int, string>
     */
    private static function keep(array $files): array
    {
        // A full-size photograph decoded by GD needs more than the default.
        @ini_set('memory_limit', '512M');

        return collect($files)->filter()->map(function ($file) {
            [$path] = ImageStore::store($file, Task::DIRECTORY, 'task', 82, 1600);

            return $path;
        })->values()->all();
    }

    /** The day and, if it was given, the hour. */
    private static function when(array $data): ?Carbon
    {
        if (blank($data['due_date'] ?? null)) {
            return null;
        }

        $at = Carbon::parse($data['due_date'])->startOfDay();
        if (filled($data['due_time'] ?? null) && preg_match('/^(\d{1,2}):(\d{2})$/', (string) $data['due_time'], $m)) {
            $at->setTime((int) $m[1], (int) $m[2]);
        }

        return $at;
    }
}

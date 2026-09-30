@extends('phone.layout')

@section('title', 'İşlər — Nefis admin')
@section('heading', $tab === 'notes' ? 'Qeydlər' : 'İşlər')
@section('tab', 'tasks')
@section('sub', $tab === 'notes'
    ? 'Yadda qalsın deyə yazılanlar'
    : ($groups['Gecikib']->count() ? $groups['Gecikib']->count() . ' iş gecikib' : 'Vaxtında gedir'))

@section('top-right')
  <button class="ph-btn ph-btn-sm ph-btn-primary" data-sheet="new-{{ $tab === 'notes' ? 'note' : 'task' }}">+</button>
@endsection

@section('chips')
  <div class="ph-chips">
    <a class="ph-chip {{ $tab === 'tasks' && ! $mine ? 'on' : '' }}" href="{{ route('phone.tasks.index') }}">İşlər</a>
    <a class="ph-chip {{ $tab === 'tasks' && $mine ? 'on' : '' }}" href="{{ route('phone.tasks.index', ['mine' => 1]) }}">Mənim</a>
    <a class="ph-chip {{ $tab === 'notes' ? 'on' : '' }}" href="{{ route('phone.tasks.index', ['tab' => 'notes']) }}">Qeydlər</a>
  </div>
@endsection

@php $hours = collect(range(0, 47))->map(fn ($i) => sprintf('%02d:%02d', intdiv($i, 2), $i % 2 ? 30 : 0)); @endphp

@php
  /* One card, whether it is a task or a note: the same shape, so the eye does
     not have to learn two. */
  $person = fn ($user) => $user?->name ? \Illuminate\Support\Str::of($user->name)->explode(' ')->first() : null;
@endphp

@section('content')
  @if($tab === 'notes')
    @forelse($notes as $note)
      <div class="ph-block">
        <div class="ph-line"><b>{{ $note->title }}</b></div>
        @if($note->body)
          <div class="small" style="white-space:pre-wrap; margin-top:.25rem;">{{ $note->body }}</div>
        @endif
        @include('phone.partials.task-photos', ['task' => $note])
        <div class="small" style="margin-top:.4rem; opacity:.7;">
          {{ $note->updated_at->format('d.m.Y H:i') }}@if($person($note->author)) · {{ $person($note->author) }}@endif
        </div>
        <div class="ph-btns" style="margin-top:.6rem;">
          <button class="ph-btn ph-btn-sm" data-sheet="edit-{{ $note->id }}">Dəyiş</button>
        </div>
      </div>
      @include('phone.partials.task-sheet', ['task' => $note, 'staff' => $staff])
    @empty
      <p class="ph-empty">Hələ qeyd yoxdur. Sağ yuxarıdakı «+» ilə yazın.</p>
    @endforelse
  @else
    @php $any = collect($groups)->sum(fn ($g) => $g->count()); @endphp

    @foreach($groups as $heading => $list)
      @continue($list->isEmpty())
      <div class="ph-day">{{ $heading }}</div>
      @foreach($list as $task)
        <div class="ph-block">
          <div class="ph-line">
            <b>{{ $task->title }}</b>
            <span class="ph-chip {{ $task->status === 'doing' ? 'on' : '' }}" style="margin-left:auto;">{{ $task->statusLabel() }}</span>
          </div>
          @if($task->body)
            <div class="small" style="white-space:pre-wrap; margin-top:.25rem;">{{ $task->body }}</div>
          @endif
          @include('phone.partials.task-photos', ['task' => $task])
          <div class="small" style="margin-top:.4rem;">
            @if($task->dueLabel())
              <span class="{{ $task->isOverdue() ? 'ph-warn' : '' }}">🕑 {{ $task->dueLabel() }}</span>
            @endif
            @if($person($task->owner)) · {{ $person($task->owner) }} @endif
          </div>
          <div class="ph-btns" style="margin-top:.6rem;">
            <form method="POST" action="{{ route('phone.tasks.update', $task) }}" data-once style="display:inline;">
              @csrf @method('PATCH')
              <input type="hidden" name="advance" value="1">
              <button class="ph-btn ph-btn-sm ph-btn-primary">{{ $task->nextLabel() }}</button>
            </form>
            <button class="ph-btn ph-btn-sm" data-sheet="edit-{{ $task->id }}">Dəyiş</button>
          </div>
        </div>
        @include('phone.partials.task-sheet', ['task' => $task, 'staff' => $staff])
      @endforeach
    @endforeach

    @if(! $any)
      <p class="ph-empty">İş yoxdur. Sağ yuxarıdakı «+» ilə əlavə edin.</p>
    @endif

    @if($done->isNotEmpty())
      <div class="ph-day">Bu həftə bitənlər</div>
      @foreach($done as $task)
        <div class="ph-block" style="opacity:.65;">
          <div class="ph-line">
            <b style="text-decoration:line-through;">{{ $task->title }}</b>
            <span class="small" style="margin-left:auto;">{{ $task->done_at?->format('d.m') }}</span>
          </div>
          <div class="ph-btns" style="margin-top:.5rem;">
            <form method="POST" action="{{ route('phone.tasks.update', $task) }}" data-once style="display:inline;">
              @csrf @method('PATCH')
              <input type="hidden" name="advance" value="1">
              <button class="ph-btn ph-btn-sm">Yenidən aç</button>
            </form>
            <form method="POST" action="{{ route('phone.tasks.destroy', $task) }}" data-once style="display:inline;"
                  onsubmit="return confirm('Silinsin?');">
              @csrf @method('DELETE')
              <button class="ph-btn ph-btn-sm ph-btn-danger">Sil</button>
            </form>
          </div>
        </div>
      @endforeach
    @endif
  @endif

  {{-- Adding one. Two sheets, because a note asks for less than a task. --}}
  <dialog class="sheet" id="new-task">
    <div class="sheet-in">
      <div class="sheet-grip"></div>
      <h2>Yeni iş</h2>
      <form method="POST" action="{{ route('phone.tasks.store') }}" data-once enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="kind" value="task">
        <label class="ph-field">
          <span>Nə etmək lazımdır</span>
          <input type="text" name="title" maxlength="160" required placeholder="Məs. lent almaq">
        </label>
        <label class="ph-field">
          <span>Təfərrüat</span>
          <textarea name="body" rows="3" maxlength="4000" placeholder="İstəyə bağlı"></textarea>
        </label>
        <label class="ph-field">
          <span>Son tarix</span>
          <input type="date" name="due_date" min="{{ now()->subYear()->toDateString() }}" data-placeholder="Tarix seçin">
        </label>
        <label class="ph-field">
          <span>Saat</span>
          {{-- Not <input type="time">: the browser opens its own dark wheel,
               which belongs to another program. --}}
          <select name="due_time" data-fancy>
            <option value="">Saatsız</option>
            @foreach($hours as $hour)
              <option value="{{ $hour }}">{{ $hour }}</option>
            @endforeach
          </select>
        </label>
        <label class="ph-field">
          <span>Kimə</span>
          <select name="user_id">
            <option value="">Hamıya</option>
            @foreach($staff as $person)
              <option value="{{ $person->id }}">{{ $person->name }}</option>
            @endforeach
          </select>
        </label>
        <label class="ph-field">
          <span>Şəkil</span>
          <input type="file" name="photos[]" accept="image/*" multiple>
        </label>
        <div class="ph-btns">
          <button class="ph-btn ph-btn-primary">Əlavə et</button>
          <button type="button" class="ph-btn" data-close>Bağla</button>
        </div>
      </form>
    </div>
  </dialog>

  <dialog class="sheet" id="new-note">
    <div class="sheet-in">
      <div class="sheet-grip"></div>
      <h2>Yeni qeyd</h2>
      <form method="POST" action="{{ route('phone.tasks.store') }}" data-once enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="kind" value="note">
        <label class="ph-field">
          <span>Başlıq</span>
          <input type="text" name="title" maxlength="160" required placeholder="Məs. kuryerin nömrəsi">
        </label>
        <label class="ph-field">
          <span>Mətn</span>
          <textarea name="body" rows="5" maxlength="4000"></textarea>
        </label>
        <label class="ph-field">
          <span>Şəkil</span>
          <input type="file" name="photos[]" accept="image/*" multiple>
        </label>
        <div class="ph-btns">
          <button class="ph-btn ph-btn-primary">Yaz</button>
          <button type="button" class="ph-btn" data-close>Bağla</button>
        </div>
      </form>
    </div>
  </dialog>
@endsection

{{-- Changing one thing on the board: the same sheet for a task and a note,
     with the parts a note has no use for left out. --}}
<dialog class="sheet" id="edit-{{ $task->id }}">
  <div class="sheet-in">
    <div class="sheet-grip"></div>
    <h2>{{ $task->kind === \App\Models\Task::NOTE ? 'Qeyd' : 'İş' }}</h2>
    <form method="POST" action="{{ route('phone.tasks.update', $task) }}" data-once enctype="multipart/form-data">
      @csrf @method('PATCH')
      <label class="ph-field">
        <span>Başlıq</span>
        <input type="text" name="title" maxlength="160" required value="{{ $task->title }}">
      </label>
      <label class="ph-field">
        <span>{{ $task->kind === \App\Models\Task::NOTE ? 'Mətn' : 'Təfərrüat' }}</span>
        <textarea name="body" rows="{{ $task->kind === \App\Models\Task::NOTE ? 5 : 3 }}" maxlength="4000">{{ $task->body }}</textarea>
      </label>
      @if($task->kind !== \App\Models\Task::NOTE)
        <label class="ph-field">
          <span>Son tarix</span>
          <input type="date" name="due_date" value="{{ $task->due_at?->toDateString() }}" data-placeholder="Tarix seçin">
        </label>
        <label class="ph-field">
          <span>Saat</span>
          @php $at = $task->due_at && $task->due_at->format('H:i') !== '00:00' ? $task->due_at->format('H:i') : ''; @endphp
          <select name="due_time" data-fancy>
            <option value="">Saatsız</option>
            {{-- An hour the list does not have (03:17, set from somewhere else)
                 is kept rather than quietly rounded away. --}}
            @if($at && ! $hours->contains($at))
              <option value="{{ $at }}" selected>{{ $at }}</option>
            @endif
            @foreach($hours as $hour)
              <option value="{{ $hour }}" @selected($at === $hour)>{{ $hour }}</option>
            @endforeach
          </select>
        </label>
        <label class="ph-field">
          <span>Kimə</span>
          <select name="user_id">
            <option value="">Hamıya</option>
            @foreach($staff as $person)
              <option value="{{ $person->id }}" @selected($task->user_id === $person->id)>{{ $person->name }}</option>
            @endforeach
          </select>
        </label>
        <label class="ph-field">
          <span>Vəziyyət</span>
          <select name="status">
            @foreach(\App\Models\Task::STATUSES as $key => $label)
              <option value="{{ $key }}" @selected($task->status === $key)>{{ $label }}</option>
            @endforeach
          </select>
        </label>
      @endif
      @php $pictures = $task->pictures(); @endphp
      @if($pictures)
        <div class="ph-field">
          <span>Şəkillər</span>
          <div class="ph-shots edit">
            @foreach($pictures as $shot)
              {{-- Ticked and saved — gone from the card and from the disk. --}}
              <label>
                <img src="{{ $shot['url'] }}" alt="">
                <span><input type="checkbox" name="remove[]" value="{{ $shot['path'] }}"> Sil</span>
              </label>
            @endforeach
          </div>
        </div>
      @endif
      <label class="ph-field">
        <span>Şəkil əlavə et</span>
        <input type="file" name="photos[]" accept="image/*" multiple>
      </label>
      <div class="ph-btns">
        <button class="ph-btn ph-btn-primary">Yadda saxla</button>
        <button type="button" class="ph-btn" data-close>Bağla</button>
      </div>
    </form>
    <form method="POST" action="{{ route('phone.tasks.destroy', $task) }}" data-once
          onsubmit="return confirm('Silinsin?');" style="margin-top:.5rem;">
      @csrf @method('DELETE')
      <button class="ph-btn ph-btn-sm ph-btn-danger">Sil</button>
    </form>
  </div>
</dialog>

<x-filament-panels::page>
  <style>
    .wa-wrap{ display:grid; gap:1rem; grid-template-columns:1fr; }
    @media (min-width:1024px){ .wa-wrap{ grid-template-columns:20rem 1fr; align-items:start; } }

    .wa-list{ border:1px solid rgba(128,128,128,.25); border-radius:.9rem; overflow:hidden;
      max-height:72vh; overflow-y:auto; background:rgba(128,128,128,.04); }
    .wa-row{ display:block; width:100%; text-align:start; padding:.7rem .85rem; cursor:pointer;
      border:0; background:transparent; border-bottom:1px solid rgba(128,128,128,.15); }
    .wa-row:hover{ background:rgba(128,128,128,.08); }
    .wa-row.on{ background:rgba(37,99,235,.1); }
    .wa-top{ display:flex; justify-content:space-between; gap:.5rem; align-items:baseline; }
    .wa-who{ font-weight:650; font-size:.9rem; }
    .wa-when{ font-size:.7rem; opacity:.6; white-space:nowrap; }
    .wa-last{ font-size:.8rem; opacity:.75; margin-top:.15rem; overflow:hidden;
      display:-webkit-box; -webkit-line-clamp:1; -webkit-box-orient:vertical; }
    .wa-new{ display:inline-block; min-width:1.2rem; padding:0 .35rem; border-radius:999px;
      background:#dc2626; color:#fff; font-size:.7rem; font-weight:700; text-align:center; }

    .wa-pane{ border:1px solid rgba(128,128,128,.25); border-radius:.9rem; display:flex;
      flex-direction:column; min-height:28rem; max-height:72vh; }
    .wa-head{ padding:.75rem 1rem; border-bottom:1px solid rgba(128,128,128,.2); }
    .wa-head b{ font-size:.95rem; }
    .wa-win{ font-size:.75rem; margin-top:.15rem; }
    .wa-win.open{ color:#16a34a; } .wa-win.shut{ color:#b45309; }

    .wa-log{ flex:1; overflow-y:auto; padding:1rem; display:flex; flex-direction:column; gap:.5rem; }
    .wa-msg{ max-width:min(32rem, 85%); padding:.5rem .7rem; border-radius:.9rem; font-size:.875rem;
      line-height:1.45; white-space:pre-wrap; overflow-wrap:anywhere; }
    .wa-msg.them{ align-self:flex-start; background:rgba(128,128,128,.12); border-bottom-left-radius:.25rem; }
    .wa-msg.us{ align-self:flex-end; background:rgba(22,163,74,.16); border-bottom-right-radius:.25rem; }
    .wa-msg.us.failed{ background:rgba(220,38,38,.14); outline:1px dashed rgba(220,38,38,.6); }
    .wa-msg img, .wa-msg video{ display:block; max-width:100%; border-radius:.5rem; margin-bottom:.3rem; }
    .wa-sig{ display:block; font-size:.68rem; opacity:.65; margin-top:.25rem; }

    .wa-say{ border-top:1px solid rgba(128,128,128,.2); padding:.7rem; display:flex; gap:.5rem; align-items:flex-end; }
    .wa-say textarea{ flex:1; resize:vertical; min-height:2.6rem; max-height:10rem; font-size:.875rem;
      border-radius:.6rem; border:1px solid rgba(128,128,128,.35); padding:.5rem .7rem; background:transparent; }
    .wa-shut{ padding:.8rem 1rem; font-size:.8rem; line-height:1.55; border-top:1px solid rgba(128,128,128,.2);
      background:rgba(180,83,9,.08); }
    .wa-empty{ padding:2.5rem 1rem; text-align:center; font-size:.875rem; opacity:.7; line-height:1.7; }
  </style>

  @if(! \App\Support\WhatsApp::enabled())
    <div class="wa-empty" style="border:1px solid rgba(128,128,128,.25); border-radius:.9rem;">
      <b>WhatsApp hələ qoşulmayıb.</b><br>
      Tənzimləmələr → <b>WhatsApp</b> bölməsində nömrənin nömrəsini (Phone number ID), açarı və
      iki parolu yazın. Orada hansı ünvanı Meta-ya verməli olduğunuz da yazılıb.
    </div>
  @else
    {{-- A quiet poll: a question nobody sees is a lost order. --}}
    <div class="wa-wrap" wire:poll.15s>
      <div class="wa-list">
        @forelse($this->chats() as $c)
          <button type="button" wire:click="open({{ $c->id }})"
                  class="wa-row {{ $this->openChat === $c->id ? 'on' : '' }}">
            <span class="wa-top">
              <span class="wa-who">{{ $c->name ?: $c->written() }}</span>
              <span class="wa-when">
                @if($c->unread > 0)<span class="wa-new">{{ $c->unread }}</span>@endif
                {{ $c->last_message_at?->diffForHumans(short: true) }}
              </span>
            </span>
            <span class="wa-last">{{ $c->messages()->latest('id')->first()?->summary() }}</span>
          </button>
        @empty
          <div class="wa-empty">Hələ heç kim yazmayıb.</div>
        @endforelse
      </div>

      <div class="wa-pane">
        @php($chat = $this->chat())
        @if(! $chat)
          <div class="wa-empty">Soldan bir söhbət seçin.</div>
        @else
          <div class="wa-head">
            <b>{{ $chat->name ?: $chat->written() }}</b>
            <span style="opacity:.6; font-size:.8rem;">· {{ $chat->written() }}</span>
            @if($chat->windowOpen())
              <div class="wa-win open">
                Cavab vermək olar — {{ intdiv($chat->windowLeft(), 60) }} saat
                {{ $chat->windowLeft() % 60 }} dəqiqə qalıb.
              </div>
            @else
              <div class="wa-win shut">24 saatlıq pəncərə bağlanıb.</div>
            @endif
          </div>

          <div class="wa-log">
            @foreach($this->thread() as $m)
              <div class="wa-msg {{ $m->mine() ? 'us' : 'them' }} {{ $m->status === 'failed' ? 'failed' : '' }}">
                @if($m->hasMedia())
                  @php($url = \Illuminate\Support\Facades\Storage::disk('public')->url($m->media_path))
                  @if(str_starts_with((string) $m->media_mime, 'image/'))
                    <img src="{{ $url }}" alt="">
                  @elseif(str_starts_with((string) $m->media_mime, 'video/'))
                    <video src="{{ $url }}" controls></video>
                  @else
                    <a href="{{ $url }}" target="_blank" rel="noopener">📎 {{ $m->media_name ?: 'fayl' }}</a>
                  @endif
                @endif
                {{ $m->body }}
                <span class="wa-sig">
                  {{ $m->sent_at?->format('d.m H:i') }}
                  @if($m->mine())
                    · {{ $m->author?->name ?? 'mağaza' }}
                    @if($m->status === 'failed') · <b>getmədi</b>
                    @elseif($m->status === 'read') · oxundu
                    @elseif($m->status === 'delivered') · çatdı
                    @endif
                  @endif
                </span>
              </div>
            @endforeach
          </div>

          @if($chat->windowOpen())
            <form class="wa-say" wire:submit.prevent="send">
              <textarea wire:model="draft" rows="1" placeholder="Cavabınızı yazın…"
                        wire:keydown.enter.prevent="send"></textarea>
              <x-filament::button type="submit" wire:loading.attr="disabled">Göndər</x-filament::button>
            </form>
          @else
            <div class="wa-shut">
              WhatsApp müştəri son dəfə yazandan 24 saat sonra sərbəst cavab verməyə icazə vermir.
              O yenidən yazan kimi pəncərə özü açılır. Təcili olsa — <b>{{ $chat->written() }}</b>
              nömrəsinə zəng edin.
            </div>
          @endif
        @endif
      </div>
    </div>
  @endif
</x-filament-panels::page>

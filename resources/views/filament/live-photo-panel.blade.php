{{-- Makes the camera's tracking data from the picture, in this browser (MindAR's compiler), and hands out the QR code. --}}
@php $live = $getRecord(); @endphp
@if($live)
{{-- Keyed on the picture and its data, so a saved change starts the panel afresh. --}}
<div wire:key="live-panel-{{ $live->id }}-{{ md5($live->target_image . '|' . $live->target_mind) }}"
     x-data="{
        busy: false, progress: 0, error: null, ready: @js(filled($live->target_mind)),
        link: @js($live->url()),
        async compile() {
          this.busy = true; this.progress = 0; this.error = null;
          try {
            if (! window.MINDAR) await import('https://cdn.jsdelivr.net/npm/mind-ar@1.2.5/dist/mindar-image.prod.js');
            if (! window.MINDAR) throw new Error('Kitabxana yüklənmədi — səhifəni yeniləyin.');
            const src = await new Promise((ok, bad) => { const i = new Image(); i.onload = () => ok(i); i.onerror = () => bad(new Error('Şəkil açılmadı.')); i.src = @js($live->imageUrl()) + '?v=' + Date.now(); });
            /* about 1000 px is all the camera needs, and much quicker to prepare */
            const k = Math.min(1, 1000 / Math.max(src.naturalWidth, src.naturalHeight));
            const c = document.createElement('canvas');
            c.width = Math.round(src.naturalWidth * k); c.height = Math.round(src.naturalHeight * k);
            c.getContext('2d').drawImage(src, 0, 0, c.width, c.height);
            const img = await new Promise(ok => { const i = new Image(); i.onload = () => ok(i); i.src = c.toDataURL('image/jpeg', 0.92); });
            const compiler = new window.MINDAR.IMAGE.Compiler();
            await compiler.compileImageTargets([img], p => this.progress = Math.round(p));
            const data = await compiler.exportData();
            const fd = new FormData();
            fd.append('mind', new Blob([data]), 'target.mind');
            const r = await fetch(@js(route('live.mind', $live)), { method: 'POST', body: fd, credentials: 'same-origin',
              headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' } });
            if (! r.ok) throw new Error('Saxlanmadı (' + r.status + ').');
            this.ready = true;
          } catch (e) { this.error = e.message || String(e); }
          this.busy = false;
        },
        qr() {
          const q = qrcode(0, 'M'); q.addData(this.link); q.make();
          return q;
        },
        drawQr(tries = 0) {
          if (! window.qrcode) {
            if (tries === 20) { const s = document.createElement('script'); s.src = 'https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js'; document.head.appendChild(s); }
            return setTimeout(() => this.drawQr(tries + 1), 150);
          }
          const q = this.qr(), n = q.getModuleCount(), cell = 24, pad = cell * 3, size = n * cell + pad * 2;
          const c = this.$refs.qr; c.width = c.height = size;
          const x = c.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, size, size); x.fillStyle = '#000';
          for (let r = 0; r < n; r++) for (let k = 0; k < n; k++) if (q.isDark(r, k)) x.fillRect(pad + k * cell, pad + r * cell, cell, cell);
          this.$refs.png.href = c.toDataURL('image/png');
          this.$refs.svg.href = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(q.createSvgTag({ cellSize: 10, margin: 3, scalable: true }));
        },
     }"
     x-init="drawQr()"
     style="display:grid; gap:1.25rem; grid-template-columns:repeat(auto-fit, minmax(16rem, 1fr)); align-items:start;">
  <script type="module" src="https://cdn.jsdelivr.net/npm/mind-ar@1.2.5/dist/mindar-image.prod.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>

  <div style="display:flex; flex-direction:column; gap:.6rem;">
    <div style="font-weight:600;">1. Kamera üçün hazırlıq</div>
    <p style="font-size:.85rem; opacity:.75; margin:0;">Şəkil kamera üçün hazırlanır (bu brauzerdə, 10–40 saniyə). Hazırlanarkən bu tabı açıq saxlayın — başqa taba keçsəniz, dayanır. Şəkli dəyişsəniz, yenidən hazırlayın.</p>
    <div x-show="ready && ! busy" style="color:#16a34a; font-weight:600;">✓ Hazırdır — QR kodu çap edə bilərsiniz</div>
    <div x-show="! ready && ! busy" style="color:#d97706; font-weight:600;">Hələ hazır deyil</div>
    <div x-show="busy" style="font-weight:600;">Hazırlanır… <span x-text="progress + '%'"></span></div>
    <div x-show="error" x-text="error" style="color:#dc2626; font-size:.85rem;"></div>
    <div><x-filament::button type="button" x-on:click="compile()" x-bind:disabled="busy" icon="heroicon-o-cpu-chip">
      <span x-text="ready ? 'Yenidən hazırla' : 'Hədəfi hazırla'"></span>
    </x-filament::button></div>
  </div>

  <div wire:ignore style="display:flex; flex-direction:column; gap:.6rem;">
    <div style="font-weight:600;">2. QR kod</div>
    <canvas x-ref="qr" style="width:10rem; height:10rem; image-rendering:pixelated; border-radius:.4rem; border:1px solid rgba(128,128,128,.3);"></canvas>
    <div style="display:flex; gap:.75rem; flex-wrap:wrap; font-size:.85rem;">
      <a x-ref="png" download="qr-{{ $live->code }}.png" style="text-decoration:underline;">PNG yüklə</a>
      <a x-ref="svg" download="qr-{{ $live->code }}.svg" style="text-decoration:underline;">SVG yüklə (çap üçün)</a>
    </div>
    <div style="font-size:.8rem; opacity:.75; word-break:break-all;">{{ $live->url() }}</div>
  </div>

  <div style="display:flex; flex-direction:column; gap:.6rem;">
    <div style="font-weight:600;">3. Yoxlayın</div>
    <p style="font-size:.85rem; opacity:.75; margin:0;">QR kodu telefonla oxudun, kameranı açın və telefonu şəklə tutun (ekrandakı şəklə də olar).</p>
    <div><x-filament::button tag="a" href="{{ $live->url() }}" target="_blank" color="gray" icon="heroicon-o-arrow-top-right-on-square">Səhifəni aç</x-filament::button></div>
    <div style="font-size:.8rem; opacity:.75;">Baxış: {{ $live->views }}</div>
  </div>
</div>
@endif

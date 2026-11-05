{{--
    The printed face, marked on the owner's own render by dragging its four
    corners onto it.

    It was a rectangle at first, which can only describe a box photographed
    dead square on — and a square-on render is useless here, because the
    drawn face covers the whole of it and the picture adds nothing. His
    boxes are rendered turned and tilted, and on those the face is a
    quadrilateral. So four points, and the same warper the page lays a
    company's logo onto a photograph of a café counter with: what is drawn
    here is drawn by the code that will draw it on the site.

    The render is read straight out of the form's own state, the way the
    wrapping preview does. A file chosen but not yet saved is still
    «livewire-file:…» there and has no address, so the frame asks for a save
    rather than drawing over nothing.

    One face serves both renders: the page lays the front and the back into
    the same four points, so the two have to be shot in the same frame.
--}}
<div
    x-data="{
        base: @js(rtrim(asset('storage'), '/')),
        root: @js(rtrim(asset(''), '/')),
        path: @js($getStatePath()),
        src: null,
        img: null,
        pts: [[0, 0], [100, 0], [100, 100], [0, 100]],
        grab: null,
        cache: {},
        card: null,
        cardFor: null,

        /* The render, whichever face he uploaded. A file chosen a moment
           ago has no address yet. */
        picture() {
            const d = this.$wire.data || {};
            const chosen = Object.values(d.front_image || {})[0]
                || Object.values(d.back_image || {})[0];
            if (typeof chosen !== 'string' || chosen.startsWith('livewire-file:')) {
                return null;
            }
            /* A render that travels with the site sits under public/images;
               one uploaded here sits on the hosting's own disk. */
            return chosen.startsWith('images/')
                ? this.root + '/' + chosen
                : this.base + '/' + chosen;
        },

        /* The corners as the form holds them — fractions there, per cent
           here. Not while one is being dragged: the mouse is the truth
           then, and pulling the old numbers back would fight it. */
        pull() {
            const src = this.picture();
            if (src !== this.src) {
                this.src = src;
                this.img = null;
                this.cache = {};
                if (src) {
                    const img = new Image();
                    img.onload = () => { this.img = img; this.repaint(); };
                    img.src = src;
                }
            }
            if (this.grab) return;

            const saved = (this.$wire.data || {})[this.path.split('.').pop()];
            if (Array.isArray(saved) && saved.length === 4) {
                const read = saved.map((p) => [
                    Math.min(100, Math.max(0, Number((p || [])[0]) * 100 || 0)),
                    Math.min(100, Math.max(0, Number((p || [])[1]) * 100 || 0)),
                ]);
                if (read.every((p) => isFinite(p[0]) && isFinite(p[1]))) {
                    this.pts = read;
                }
            }
            this.repaint();
        },

        /* Painting from inside x-effect would have the effect watch every
           reactive thing the painting reads - the four points among them.
           Moving a point would then re-run the effect, which pulls the
           points back out of the form, which undoes the move. Deferring it
           by a tick puts those reads outside what Alpine is tracking. */
        repaint() {
            /* The «already asked» flag is parked on the element rather than
               on this object: anything reactive that the effect writes, the
               effect also depends on, and it would set itself off again. */
            const el = this.$el;
            if (el._bfpSoon) return;
            el._bfpSoon = true;
            setTimeout(() => { el._bfpSoon = false; this.paint(); }, 0);
        },

        /* ---- the picture under the frame ---- */

        /* Livewire re-renders this panel whenever anything is saved, and the
           canvas that comes back is a new element at its default 300 x 150
           - the picture drawn into it squashed flat and the frame with it.
           So the size is checked before every paint rather than once when
           the picture loads. Writing width or height clears the canvas, so
           it is only written when it is actually wrong. */
        fit() {
            if (!this.img) return;
            const long = Math.max(this.img.naturalWidth, this.img.naturalHeight) || 1;
            const k = Math.min(1, 640 / long);
            const c = this.$refs.out;
            if (!c) return;
            const w = Math.max(2, Math.round(this.img.naturalWidth * k));
            const h = Math.max(2, Math.round(this.img.naturalHeight * k));
            if (c.width !== w) c.width = w;
            if (c.height !== h) c.height = h;
        },

        /* A stand-in for the company's own face, in this size's own shape,
           so a logo that would come out stretched looks stretched here. */
        face() {
            const d = this.$wire.data || {};
            const w = Number(d.width_mm) || 45;
            const h = Number(d.height_mm) || 90;
            if (this.card && this.cardFor === w + 'x' + h) return this.card;

            const c = document.createElement('canvas');
            c.width = Math.round(w * 6);
            c.height = Math.round(h * 6);
            const x = c.getContext('2d');
            x.fillStyle = '#1B3A6B';
            x.fillRect(0, 0, c.width, c.height);
            x.strokeStyle = 'rgba(255,255,255,.35)';
            x.lineWidth = Math.max(2, c.width / 90);
            /* A border and a cross: a face put on crooked shows it at once. */
            x.strokeRect(x.lineWidth, x.lineWidth, c.width - x.lineWidth * 2, c.height - x.lineWidth * 2);
            x.beginPath();
            x.moveTo(0, 0); x.lineTo(c.width, c.height);
            x.moveTo(c.width, 0); x.lineTo(0, c.height);
            x.globalAlpha = 0.25;
            x.stroke();
            x.globalAlpha = 1;
            x.fillStyle = '#fff';
            x.textAlign = 'center';
            x.textBaseline = 'middle';
            x.font = '700 ' + Math.round(c.width / 9) + 'px sans-serif';
            x.fillText('LOQONUZ', c.width / 2, c.height / 2);

            this.card = c;
            this.cardFor = w + 'x' + h;
            return c;
        },

        paint() {
            if (!this.img || !window.NefisScene) return;
            this.fit();
            const c = this.$refs.out;
            if (!c || !c.width) return;
            const ctx = c.getContext('2d');
            ctx.setTransform(1, 0, 0, 1, 0, 0);
            ctx.clearRect(0, 0, c.width, c.height);
            if (!this.ok()) {                       // a bow tie warps to nonsense
                ctx.drawImage(this.img, 0, 0, c.width, c.height);
                return;
            }
            window.NefisScene.drawScene(ctx, {
                w: c.width, h: c.height, bg: 'render',
                elements: [{
                    type: 'design', id: 'face', opacity: 100,
                    corners: this.pts.map((p) => [p[0] / 100 * c.width, p[1] / 100 * c.height]),
                }],
            }, this.face(), () => this.img, this.cache, {});
        },

        /* ---- dragging ---- */

        where(event) {
            const box = this.$refs.frame.getBoundingClientRect();
            if (!box.width || !box.height) return { x: 0, y: 0 };
            return {
                x: Math.min(100, Math.max(0, (event.clientX - box.left) / box.width * 100)),
                y: Math.min(100, Math.max(0, (event.clientY - box.top) / box.height * 100)),
            };
        },

        take(event, which) {
            event.preventDefault();
            event.stopPropagation();
            this.grab = {
                which: which,
                from: this.where(event),
                was: this.pts.map((p) => [p[0], p[1]]),
            };
            /* No setPointerCapture: the moving and the letting go are heard
               on the window, so a mouse dragged clean off the picture is
               still followed, and a finger is captured by the browser the
               moment it touches — which is why the picture sets
               touch-action: none, or the page would scroll under it. */
        },

        /* A press anywhere inside the frame itself carries all four. */
        inside(event) {
            const p = this.where(event);
            let hit = false;
            for (let i = 0, j = 3; i < 4; j = i++) {
                const a = this.pts[i], b = this.pts[j];
                if ((a[1] > p.y) !== (b[1] > p.y)
                    && p.x < (b[0] - a[0]) * (p.y - a[1]) / (b[1] - a[1]) + a[0]) {
                    hit = !hit;
                }
            }
            if (hit) this.take(event, 'all');
        },

        drag(event) {
            if (!this.grab) return;
            const now = this.where(event);
            const was = this.grab.was;

            if (this.grab.which === 'all') {
                let dx = now.x - this.grab.from.x;
                let dy = now.y - this.grab.from.y;
                /* Shifted together they must all stay on the picture, so the
                   one that would walk off the edge holds the rest back. */
                dx = Math.min(dx, 100 - Math.max(...was.map((p) => p[0])));
                dx = Math.max(dx, -Math.min(...was.map((p) => p[0])));
                dy = Math.min(dy, 100 - Math.max(...was.map((p) => p[1])));
                dy = Math.max(dy, -Math.min(...was.map((p) => p[1])));
                this.pts = was.map((p) => [p[0] + dx, p[1] + dy]);
            } else {
                const moved = was.map((p) => [p[0], p[1]]);
                moved[this.grab.which] = [now.x, now.y];
                this.pts = moved;
            }
            this.paint();
        },

        drop() {
            if (!this.grab) return;
            this.grab = null;
            this.write();
        },

        /* ---- the two ways out ---- */

        whole() {
            this.pts = [[0, 0], [100, 0], [100, 100], [0, 100]];
            this.write();
            this.paint();
        },

        square() {
            const xs = this.pts.map((p) => p[0]), ys = this.pts.map((p) => p[1]);
            const l = Math.min(...xs), r = Math.max(...xs);
            const t = Math.min(...ys), b = Math.max(...ys);
            this.pts = [[l, t], [r, t], [r, b], [l, b]];
            this.write();
            this.paint();
        },

        /* Do the four go round without crossing? A corner dragged past its
           neighbours makes a bow tie, and the page would paint the logo
           folded over itself rather than refuse. */
        ok() {
            let sign = 0;
            for (let i = 0; i < 4; i++) {
                const a = this.pts[i], b = this.pts[(i + 1) % 4], c = this.pts[(i + 2) % 4];
                const cross = (b[0] - a[0]) * (c[1] - b[1]) - (b[1] - a[1]) * (c[0] - b[0]);
                if (Math.abs(cross) < 1e-9) continue;
                const now = cross > 0 ? 1 : -1;
                if (sign === 0) sign = now;
                else if (sign !== now) return false;
            }
            return sign !== 0;
        },

        write() {
            this.$wire.set(this.path, this.pts.map((p) => [
                Math.round(p[0] * 10) / 1000,
                Math.round(p[1] * 10) / 1000,
            ]));
        },

        say(value) { return Math.round(value * 10) / 10 + '%'; },
        middle(axis) { return this.pts.reduce((sum, p) => sum + p[axis], 0) / 4; },
    }"
    x-init="const go = () => { pull(); if (! window.NefisScene) setTimeout(go, 80); }; go()"
    x-effect="$wire.data && [
        JSON.stringify($wire.data.front_image), JSON.stringify($wire.data.back_image),
        JSON.stringify($wire.data.face_quad), $wire.data.width_mm, $wire.data.height_mm
    ]; pull()"
    @pointermove.window="drag($event)"
    @pointerup.window="drop()"
    @pointercancel.window="drop()"
    class="bfp"
>
    <script src="{{ asset('js/scene-render.js') }}?v={{ \App\Support\Assets::version('js/scene-render.js') }}"></script>

    <style>
        .bfp{ display:flex; gap:1.5rem; align-items:flex-start; flex-wrap:wrap; }
        .bfp-stage{ flex:0 0 auto; width:min(100%, 22rem); }
        /* A chequer under the picture: these renders are transparent PNGs,
           and a white box on a white panel looks like nothing was uploaded. */
        .bfp-frame{ position:relative; user-select:none; touch-action:none; border-radius:.5rem;
            overflow:hidden; box-shadow:0 8px 24px rgba(0,0,0,.25); cursor:move;
            background-color:#f4f4f5;
            background-image:
                linear-gradient(45deg, rgba(0,0,0,.07) 25%, transparent 25%, transparent 75%, rgba(0,0,0,.07) 75%),
                linear-gradient(45deg, rgba(0,0,0,.07) 25%, transparent 25%, transparent 75%, rgba(0,0,0,.07) 75%);
            background-size:18px 18px; background-position:0 0, 9px 9px; }
        .bfp-frame canvas{ display:block; width:100%; height:auto; }
        .bfp-net{ position:absolute; inset:0; width:100%; height:100%; pointer-events:none; }
        .bfp-net polygon{ fill:rgba(245,158,11,.14); stroke:#f59e0b; stroke-width:2;
            vector-effect:non-scaling-stroke; }
        .bfp-net.is-bad polygon{ fill:rgba(220,38,38,.18); stroke:#dc2626; }
        .bfp-dot{ position:absolute; width:18px; height:18px; margin:-9px 0 0 -9px; cursor:grab;
            border-radius:999px; background:#f59e0b; border:2px solid #fff;
            box-shadow:0 1px 3px rgba(0,0,0,.45); }
        .bfp-tag{ position:absolute; transform:translate(-50%,-50%); pointer-events:none;
            font-size:.7rem; font-weight:700; letter-spacing:.03em; white-space:nowrap;
            color:#fff; background:rgba(0,0,0,.55); padding:.15rem .4rem; border-radius:.25rem; }
        .bfp-side{ flex:1 1 17rem; font-size:.85rem; line-height:1.6; }
        .bfp-nums{ display:flex; flex-wrap:wrap; gap:.4rem; margin:.7rem 0 .8rem; }
        .bfp-num{ border:1px solid rgba(128,128,128,.35); border-radius:.5rem; padding:.3rem .55rem;
            font-size:.75rem; white-space:nowrap; font-variant-numeric:tabular-nums; }
        .bfp-btns{ display:flex; gap:.5rem; flex-wrap:wrap; }
        .bfp-btn{ border:1px solid rgba(128,128,128,.45); border-radius:.5rem; background:transparent;
            padding:.35rem .7rem; font-size:.8rem; font-weight:600; cursor:pointer; color:inherit; }
        .bfp-bad{ color:#dc2626; font-weight:600; margin:.6rem 0 0; }
        .bfp-none{ font-size:.85rem; line-height:1.6; opacity:.8; margin:0; }
        [x-cloak]{ display:none !important; }
    </style>

    <template x-if="src">
        <div class="bfp-stage">
            <div class="bfp-frame" x-ref="frame" @pointerdown="inside($event)">
                <canvas x-ref="out"></canvas>
                <svg class="bfp-net" :class="ok() ? '' : 'is-bad'" viewBox="0 0 100 100" preserveAspectRatio="none">
                    <polygon :points="pts.map((p) => p[0] + ',' + p[1]).join(' ')"></polygon>
                </svg>
                <span class="bfp-tag" :style="`left:${middle(0)}%; top:${middle(1)}%`">Çap olunan üz</span>
                <template x-for="(p, i) in pts" :key="i">
                    <i class="bfp-dot" :style="`left:${p[0]}%; top:${p[1]}%`"
                       @pointerdown="take($event, i)"></i>
                </template>
            </div>
        </div>
    </template>

    <div class="bfp-side">
        <template x-if="src">
            <div>
                <p style="margin:0;">
                    Dörd nöqtəni qutunun <b>çap olunan üzünün</b> künclərinə qoyun — qutu əyri
                    dayansa da olar. Ortasından tutsanız, dördü birlikdə sürüşür.
                    Şəkildə göründüyü kimi müştərinin loqosu da elə oturacaq.
                </p>
                <div class="bfp-nums">
                    <template x-for="(p, i) in pts" :key="i">
                        <span class="bfp-num">
                            <b x-text="i + 1"></b>:
                            <span x-text="say(p[0])"></span> · <span x-text="say(p[1])"></span>
                        </span>
                    </template>
                </div>
                <div class="bfp-btns">
                    <button type="button" class="bfp-btn" @click="whole()">Bütün şəkil</button>
                    <button type="button" class="bfp-btn" @click="square()">Düzbucaqlı et</button>
                </div>
                <p class="bfp-bad" x-show="! ok()" x-cloak>
                    Çərçivə özünü kəsir — künclərdən biri o birilərinin o tayına keçib.
                    «Düzbucaqlı et» deyin və yenidən qoyun.
                </p>
                <p style="margin:.8rem 0 0; opacity:.75;">
                    Render düz öndən çəkilibsə və yalnız üzün özüdürsə — «Bütün şəkil» deyin.
                    Ön və arxa şəkillər eyni kadrda olmalıdır: səhifə ikisini də bu dörd nöqtəyə qoyur.
                    Sonra <b>Yadda saxla</b>.
                </p>
            </div>
        </template>
        <template x-if="! src">
            <p class="bfp-none">
                Əvvəlcə yuxarıda qutunun şəklini yükləyin və <b>Yadda saxla</b> deyin —
                sonra şəkil burada görünəcək və üzün dörd küncünü mausla göstərəcəksiniz.
            </p>
        </template>
    </div>
</div>

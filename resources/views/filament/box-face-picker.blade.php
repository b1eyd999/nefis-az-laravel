{{--
    The printed face, marked on the owner's own render by dragging a frame
    over it.

    The page draws the box's face — the colour the visitor picked, his logo,
    his line, the QR — and lays it inside the render at this rectangle. Four
    percentages describe it, and reading those off a picture by eye is the
    kind of work nobody finishes: the owner said so, so here they are dragged
    instead and the numbers are written for him.

    The render itself is read straight out of the form's own state, the way
    the wrapping preview does, so it follows the upload without this view
    knowing anything about Livewire's temporary files. One thing it cannot
    do: show a file that has been chosen but not yet saved. Filament leaves
    «livewire-file:…» in the state until then and there is no address to
    show, so the frame says to save first rather than drawing nothing.

    One rectangle serves both faces: the page lays the front and the back
    into the same place, so the two renders have to be shot in the same frame.
--}}
<div
    x-data="{
        base: @js(rtrim(asset('storage'), '/')),
        root: @js(rtrim(asset(''), '/')),
        src: null,
        l: 0, t: 0, r: 100, b: 100,
        grab: null,

        /* The render, whichever face the owner uploaded first. A file chosen
           a moment ago is still «livewire-file:…» and has no address yet. */
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

        /* The numbers as the form holds them. Not while a corner is being
           dragged: the frame is the truth then, and pulling the old numbers
           back under the mouse would fight it. */
        pull() {
            this.src = this.picture();
            if (this.grab) return;
            const d = this.$wire.data || {};
            this.l = this.clamp(d.face_left, 0);
            this.t = this.clamp(d.face_top, 0);
            this.r = this.clamp(d.face_right, 100);
            this.b = this.clamp(d.face_bottom, 100);
            if (this.r - this.l < 2 || this.b - this.t < 2) {
                this.l = 0; this.t = 0; this.r = 100; this.b = 100;
            }
        },

        clamp(value, fallback) {
            const n = Number(value);
            return isNaN(n) ? fallback : Math.min(100, Math.max(0, n));
        },

        /* Where the pointer is on the picture, as a percentage of it. */
        where(event) {
            const box = this.$refs.frame.getBoundingClientRect();
            if (! box.width || ! box.height) return { x: 0, y: 0 };
            return {
                x: Math.min(100, Math.max(0, (event.clientX - box.left) / box.width * 100)),
                y: Math.min(100, Math.max(0, (event.clientY - box.top) / box.height * 100)),
            };
        },

        take(event, edge) {
            event.preventDefault();
            event.stopPropagation();
            this.grab = { edge: edge, from: this.where(event), was: { l: this.l, t: this.t, r: this.r, b: this.b } };
            /* No setPointerCapture: the moving and the letting go are
               listened for on the window, so a mouse dragged clean off the
               picture is still followed, and a finger is captured by the
               browser itself the moment it touches - which is why the
               picture sets touch-action: none, or the page would scroll
               under it instead. */
        },

        drag(event) {
            if (! this.grab) return;
            const now = this.where(event);
            const was = this.grab.was;

            if (this.grab.edge === 'all') {
                const w = was.r - was.l, h = was.b - was.t;
                const left = Math.min(100 - w, Math.max(0, was.l + now.x - this.grab.from.x));
                const top = Math.min(100 - h, Math.max(0, was.t + now.y - this.grab.from.y));
                this.l = left; this.t = top; this.r = left + w; this.b = top + h;
                return;
            }

            /* No edge may pass its opposite. A right edge to the left of the
               left one is not a rectangle, and the page would paint the logo
               backwards rather than refuse — two per cent is the smallest
               face it will let him make. */
            if (this.grab.edge.indexOf('w') >= 0) this.l = Math.min(now.x, this.r - 2);
            if (this.grab.edge.indexOf('e') >= 0) this.r = Math.max(now.x, this.l + 2);
            if (this.grab.edge.indexOf('n') >= 0) this.t = Math.min(now.y, this.b - 2);
            if (this.grab.edge.indexOf('s') >= 0) this.b = Math.max(now.y, this.t + 2);
        },

        drop() {
            if (! this.grab) return;
            this.grab = null;
            this.write();
        },

        whole() {
            this.l = 0; this.t = 0; this.r = 100; this.b = 100;
            this.write();
        },

        /* Into the form's own four fields, so what is saved is what he sees
           and the numbers stay there to be typed over if he ever wants to.
           Three are set quietly and the fourth asks for the round trip, so
           one drag costs one request rather than four. */
        write() {
            this.$wire.set('data.face_left', this.round(this.l), false);
            this.$wire.set('data.face_top', this.round(this.t), false);
            this.$wire.set('data.face_right', this.round(this.r), false);
            this.$wire.set('data.face_bottom', this.round(this.b));
        },

        round(value) { return Math.round(value * 10) / 10; },
        say(value) { return this.round(value) + '%'; },
    }"
    x-init="pull()"
    x-effect="$wire.data && [
        JSON.stringify($wire.data.front_image), JSON.stringify($wire.data.back_image),
        $wire.data.face_left, $wire.data.face_top, $wire.data.face_right, $wire.data.face_bottom
    ]; pull()"
    @pointermove.window="drag($event)"
    @pointerup.window="drop()"
    @pointercancel.window="drop()"
    class="bfp"
>
    <style>
        .bfp{ display:flex; gap:1.5rem; align-items:flex-start; flex-wrap:wrap; }
        .bfp-stage{ flex:0 0 auto; max-width:min(100%, 22rem); }
        /* A chequer under the picture: these renders are transparent PNGs,
           and a white box on a white panel looks like nothing was uploaded. */
        .bfp-frame{ position:relative; user-select:none; touch-action:none; border-radius:.5rem;
            overflow:hidden; box-shadow:0 8px 24px rgba(0,0,0,.25);
            background-color:#f4f4f5;
            background-image:
                linear-gradient(45deg, rgba(0,0,0,.07) 25%, transparent 25%, transparent 75%, rgba(0,0,0,.07) 75%),
                linear-gradient(45deg, rgba(0,0,0,.07) 25%, transparent 25%, transparent 75%, rgba(0,0,0,.07) 75%);
            background-size:18px 18px; background-position:0 0, 9px 9px; }
        .bfp-frame img{ display:block; width:100%; height:auto; pointer-events:none; }
        .bfp-mask{ position:absolute; box-sizing:border-box; cursor:move;
            border:2px solid #f59e0b; background:rgba(245,158,11,.18); }
        .bfp-tag{ position:absolute; left:50%; top:50%; transform:translate(-50%,-50%);
            font-size:.7rem; font-weight:700; letter-spacing:.03em; white-space:nowrap;
            color:#fff; background:rgba(0,0,0,.55); padding:.15rem .4rem; border-radius:.25rem;
            pointer-events:none; }
        .bfp-dot{ position:absolute; width:16px; height:16px; margin:-8px 0 0 -8px;
            border-radius:999px; background:#f59e0b; border:2px solid #fff; box-shadow:0 1px 3px rgba(0,0,0,.4); }
        .bfp-dot.nw{ left:0; top:0; cursor:nwse-resize; }
        .bfp-dot.ne{ left:100%; top:0; cursor:nesw-resize; }
        .bfp-dot.sw{ left:0; top:100%; cursor:nesw-resize; }
        .bfp-dot.se{ left:100%; top:100%; cursor:nwse-resize; }
        .bfp-side{ flex:1 1 16rem; font-size:.85rem; line-height:1.6; }
        .bfp-nums{ display:flex; flex-wrap:wrap; gap:.4rem; margin:.6rem 0 .8rem; }
        .bfp-num{ border:1px solid rgba(128,128,128,.35); border-radius:.5rem; padding:.3rem .55rem;
            font-size:.75rem; white-space:nowrap; }
        .bfp-num b{ font-variant-numeric:tabular-nums; }
        .bfp-btn{ border:1px solid rgba(128,128,128,.45); border-radius:.5rem;
            padding:.35rem .7rem; font-size:.8rem; font-weight:600; cursor:pointer; background:transparent;
            color:inherit; }
        .bfp-none{ font-size:.85rem; line-height:1.6; opacity:.8; margin:0; }
    </style>

    <template x-if="src">
        <div class="bfp-stage">
            <div class="bfp-frame" x-ref="frame">
                <img :src="src" alt="">
                <div class="bfp-mask"
                     :style="`left:${l}%; top:${t}%; width:${r - l}%; height:${b - t}%`"
                     @pointerdown="take($event, 'all')">
                    <span class="bfp-tag">Çap olunan üz</span>
                    <i class="bfp-dot nw" @pointerdown="take($event, 'nw')"></i>
                    <i class="bfp-dot ne" @pointerdown="take($event, 'ne')"></i>
                    <i class="bfp-dot sw" @pointerdown="take($event, 'sw')"></i>
                    <i class="bfp-dot se" @pointerdown="take($event, 'se')"></i>
                </div>
            </div>
        </div>
    </template>

    <div class="bfp-side">
        <template x-if="src">
            <div>
                <p style="margin:0;">
                    Çərçivəni qutunun <b>çap olunan üzünün</b> üstünə qoyun: ortasından tutub sürüşdürün,
                    künclərindən tutub böyüdün. Müştərinin loqosu və QR kodu düz bura düşəcək.
                </p>
                <div class="bfp-nums">
                    <span class="bfp-num">Soldan <b x-text="say(l)"></b></span>
                    <span class="bfp-num">Yuxarıdan <b x-text="say(t)"></b></span>
                    <span class="bfp-num">Sağ kənarı <b x-text="say(r)"></b></span>
                    <span class="bfp-num">Alt kənarı <b x-text="say(b)"></b></span>
                </div>
                <button type="button" class="bfp-btn" @click="whole()">Bütün şəkil</button>
                <p style="margin:.8rem 0 0; opacity:.75;">
                    Render yalnız üzün özüdürsə — «Bütün şəkil» deyin, başqa heç nə lazım deyil.
                    Ön və arxa şəkillər eyni kadrda olmalıdır: səhifə ikisini də bu çərçivəyə qoyur.
                    Sonra <b>Yadda saxla</b>.
                </p>
            </div>
        </template>
        <template x-if="! src">
            <p class="bfp-none">
                Əvvəlcə yuxarıda qutunun şəklini yükləyin və <b>Yadda saxla</b> deyin —
                sonra şəkil burada görünəcək və çərçivəni mausla onun üstünə qoyacaqsınız.
            </p>
        </template>
    </div>
</div>

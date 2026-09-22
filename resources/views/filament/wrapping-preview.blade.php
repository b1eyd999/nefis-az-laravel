{{-- The box wrapped in this paper, redrawn as the ribbon, its colour or the pattern's size change. --}}
<div
    x-data="{
        base: @js(rtrim(asset('storage'), '/')),
        img: null,
        src: null,
        draw() {
            const d = $wire.data || {};
            const pat = Object.values(d.pattern || {})[0];
            const src = (typeof pat === 'string' && ! pat.startsWith('livewire-file:')) ? this.base + '/' + pat : null;
            const c = this.$refs.c, ctx = c.getContext('2d');
            const paint = () => window.NefisWrap && NefisWrap.draw(ctx, c.width, c.height, this.img,
                { ribbon: d.ribbon, color: d.ribbon_color, scale: parseFloat(d.pattern_scale) || 0.5 });
            if (src !== this.src) {
                this.src = src;
                this.img = null;
                if (src) { this.img = new Image(); this.img.onload = paint; this.img.src = src; }
            }
            paint();
        },
    }"
    x-init="const go = () => window.NefisWrap ? draw() : setTimeout(go, 60); go()"
    x-effect="$wire.data && [$wire.data.ribbon, $wire.data.ribbon_color, $wire.data.pattern_scale, JSON.stringify($wire.data.pattern)]; window.NefisWrap && draw()"
    style="display:flex; gap:1.25rem; align-items:flex-start; flex-wrap:wrap;"
>
    <script src="{{ asset('js/wrap-render.js') }}"></script>
    <canvas x-ref="c" width="969" height="1895" style="width:190px; height:auto; border-radius:.5rem; box-shadow:0 8px 24px rgba(0,0,0,.28);"></canvas>
    <p style="font-size:.85rem; opacity:.75; max-width:22rem; line-height:1.6; margin:0;">
        Müştərinin səhifəsində qutu mokaplarda belə bükülmüş görünür — səhnənin işığı və kölgəsi ilə.
        Yeni şəkil yüklədikdə naxış saxlanandan sonra burada görünəcək.
    </p>
</div>

{{-- Campo para dibujar una firma (PNG en data URI). Lo valida App\Support\FirmaManuscrita al guardar. --}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        wire:ignore
        x-data="{
            state: $wire.$entangle('{{ $getStatePath() }}'),
            ctx: null, dibujando: false, ultimo: null, trazos: 0,
            init() {
                this.$nextTick(() => this.ajustar());
            },
            ajustar() {
                const c = this.$refs.lienzo, r = c.getBoundingClientRect(), d = window.devicePixelRatio || 1;
                if (! r.width) { setTimeout(() => this.ajustar(), 200); return; }
                c.width = Math.round(r.width * d); c.height = Math.round(r.height * d);
                this.ctx = c.getContext('2d');
                this.ctx.setTransform(d, 0, 0, d, 0, 0);
                this.ctx.lineWidth = 2.6; this.ctx.lineCap = 'round'; this.ctx.lineJoin = 'round'; this.ctx.strokeStyle = '#0f172a';
            },
            punto(e) { const r = this.$refs.lienzo.getBoundingClientRect(); return { x: e.clientX - r.left, y: e.clientY - r.top }; },
            empezar(e) { if (! this.ctx) this.ajustar(); this.dibujando = true; this.ultimo = this.punto(e); this.$refs.lienzo.setPointerCapture(e.pointerId); },
            mover(e) {
                if (! this.dibujando) return;
                const p = this.punto(e);
                this.ctx.beginPath(); this.ctx.moveTo(this.ultimo.x, this.ultimo.y); this.ctx.lineTo(p.x, p.y); this.ctx.stroke();
                this.ultimo = p; this.trazos++;
            },
            terminar() {
                if (! this.dibujando) return;
                this.dibujando = false;
                if (this.trazos < 8) return;
                const c = this.$refs.lienzo, escala = Math.min(1, 900 / c.width), s = document.createElement('canvas');
                s.width = Math.round(c.width * escala); s.height = Math.round(c.height * escala);
                s.getContext('2d').drawImage(c, 0, 0, s.width, s.height);
                this.state = s.toDataURL('image/png');
            },
            borrar() { const c = this.$refs.lienzo; this.ctx.clearRect(0, 0, c.width, c.height); this.trazos = 0; this.state = null; },
        }"
    >
        <canvas
            x-ref="lienzo"
            x-on:pointerdown.prevent="empezar($event)"
            x-on:pointermove.prevent="mover($event)"
            x-on:pointerup="terminar()"
            x-on:pointerleave="terminar()"
            x-on:pointercancel="terminar()"
            style="width: 100%; max-width: 560px; height: 180px; border: 2px dashed rgb(203 213 225); border-radius: 12px; background: #fff; touch-action: none; display: block;"
            aria-label="Recuadro para dibujar la firma"
        ></canvas>
        <div style="display: flex; gap: 12px; align-items: center; margin-top: 6px; font-size: 13px">
            <button type="button" x-on:click="borrar()" style="color: rgb(194 65 12); font-weight: 600; text-decoration: underline">Borrar</button>
            <span x-show="state" style="color: rgb(21 128 61)">Firma lista para guardar.</span>
        </div>
    </div>
</x-dynamic-component>

{{--
    لوحة رسم التوقيع (App\Filament\Forms\SignaturePad): الحالة صورة PNG بترميز base64 تُحدَّث عند رفع
    القلم، وتُعرض عند التعديل. والأنماط مضمّنة، فلا تعتمد على أصناف تبنيها سمة اللوحة.
--}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        wire:ignore
        x-data="{
            state: $wire.$entangle(@js($getStatePath())),
            drawing: false,
            ctx: null,
            init() {
                const canvas = this.$refs.canvas;
                this.ctx = canvas.getContext('2d');
                this.ctx.lineWidth = 2.5;
                this.ctx.lineCap = 'round';
                this.ctx.strokeStyle = '#1C2B2A';

                if (this.state) {
                    const image = new Image();
                    image.onload = () => this.ctx.drawImage(image, 0, 0, canvas.width, canvas.height);
                    image.src = this.state;
                }
            },
            point(event) {
                const box = this.$refs.canvas.getBoundingClientRect();

                return {
                    x: (event.clientX - box.left) * this.$refs.canvas.width / box.width,
                    y: (event.clientY - box.top) * this.$refs.canvas.height / box.height,
                };
            },
            start(event) {
                this.drawing = true;
                const point = this.point(event);
                this.ctx.beginPath();
                this.ctx.moveTo(point.x, point.y);
            },
            move(event) {
                if (! this.drawing) return;
                const point = this.point(event);
                this.ctx.lineTo(point.x, point.y);
                this.ctx.stroke();
            },
            end() {
                if (! this.drawing) return;
                this.drawing = false;
                this.state = this.$refs.canvas.toDataURL('image/png');
            },
            clear() {
                this.ctx.clearRect(0, 0, this.$refs.canvas.width, this.$refs.canvas.height);
                this.state = null;
            },
        }"
    >
        <canvas
            x-ref="canvas"
            width="600"
            height="200"
            dir="ltr"
            style="display: block; width: 100%; max-width: 36rem; aspect-ratio: 3 / 1; touch-action: none; background: #fff; border: 1px solid #d1d5db; border-radius: 0.5rem; cursor: crosshair;"
            x-on:pointerdown.prevent="start($event)"
            x-on:pointermove.prevent="move($event)"
            x-on:pointerup="end()"
            x-on:pointerleave="end()"
        ></canvas>
        <button type="button" x-on:click="clear()" style="margin-top: 0.5rem; font-size: 0.875rem; text-decoration: underline;">
            {{ __('documents.fields.signature_clear') }}
        </button>
    </div>
</x-dynamic-component>

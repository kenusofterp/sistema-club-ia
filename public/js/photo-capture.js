/*
 * Foto con cámara (webcam, notebook o teléfono) o archivo, con recorte cuadrado: zoom y arrastre
 * para ubicarla. El resultado (JPG 600×600) se sube a la propiedad Livewire indicada.
 * Uso: <div x-data="photoCapture('photo')"> (ver resources/views/components/photo-capture.blade.php).
 * Archivo estático (no pasa por Vite) para que funcione sin compilar los assets.
 */
(() => {
    const VIEWPORT = 288; // lado del recuadro de recorte, en px de pantalla
    const OUTPUT = 600; // lado de la imagen final, en px
    const MAX_ZOOM = 4;

    const photoCapture = (model) => ({
        open: false,
        step: 'camera', // camera | crop
        error: null,
        uploading: false,
        stream: null,
        facing: 'user',
        canSwitch: false,
        cameraSupported: !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.isSecureContext),

        // Recorte
        src: null,
        natW: 0,
        natH: 0,
        zoom: 1,
        x: 0,
        y: 0,
        pointers: new Map(),
        pinchStart: null,

        get minScale() {
            return this.natW ? Math.max(VIEWPORT / this.natW, VIEWPORT / this.natH) : 1;
        },
        get scale() {
            return this.minScale * this.zoom;
        },
        get imageStyle() {
            return `width:${this.natW}px;height:${this.natH}px;transform:translate(${this.x}px, ${this.y}px) scale(${this.scale});transform-origin:0 0;max-width:none;`;
        },

        // ---- Cámara ----
        async startCamera() {
            this.error = null;
            this.open = true;
            this.step = 'camera';
            this.stopCamera();
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: this.facing, width: { ideal: 1280 }, height: { ideal: 1280 } },
                    audio: false,
                });
                this.$refs.video.srcObject = this.stream;
                await this.$refs.video.play();
                const devices = await navigator.mediaDevices.enumerateDevices();
                this.canSwitch = devices.filter((d) => d.kind === 'videoinput').length > 1;
            } catch (e) {
                this.error = e && e.name === 'NotAllowedError'
                    ? 'No se dio permiso para usar la cámara. Habilitalo en el navegador (candado junto a la dirección).'
                    : 'No se pudo abrir la cámara. Probá con «Elegir archivo».';
            }
        },

        switchCamera() {
            this.facing = this.facing === 'user' ? 'environment' : 'user';
            this.startCamera();
        },

        stopCamera() {
            if (this.stream) {
                this.stream.getTracks().forEach((t) => t.stop());
                this.stream = null;
            }
        },

        takePhoto() {
            const video = this.$refs.video;
            if (!video.videoWidth) return;
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const ctx = canvas.getContext('2d');
            // La cámara frontal se ve como espejo: la foto sale igual a lo que se vio.
            if (this.facing === 'user') {
                ctx.translate(canvas.width, 0);
                ctx.scale(-1, 1);
            }
            ctx.drawImage(video, 0, 0);
            this.stopCamera();
            this.loadImage(canvas.toDataURL('image/jpeg', 0.92));
        },

        // ---- Archivo ----
        pickFile(event) {
            const file = event.target.files && event.target.files[0];
            event.target.value = '';
            if (!file) return;
            if (!file.type.startsWith('image/')) {
                this.error = 'El archivo no es una imagen.';
                return;
            }
            this.error = null;
            this.open = true;
            this.stopCamera();
            this.loadImage(URL.createObjectURL(file));
        },

        // ---- Recorte ----
        loadImage(src) {
            const img = new Image();
            img.onload = () => {
                this.natW = img.naturalWidth;
                this.natH = img.naturalHeight;
                this.src = src;
                this.zoom = 1;
                this.x = (VIEWPORT - this.natW * this.minScale) / 2;
                this.y = (VIEWPORT - this.natH * this.minScale) / 2;
                this.step = 'crop';
            };
            img.onerror = () => (this.error = 'No se pudo leer la imagen.');
            img.src = src;
        },

        clamp() {
            const w = this.natW * this.scale;
            const h = this.natH * this.scale;
            this.x = Math.min(0, Math.max(VIEWPORT - w, this.x));
            this.y = Math.min(0, Math.max(VIEWPORT - h, this.y));
        },

        /** Cambia el zoom manteniendo fijo el punto (cx, cy) del recuadro (por defecto, el centro). */
        setZoom(value, cx = VIEWPORT / 2, cy = VIEWPORT / 2) {
            const old = this.scale;
            this.zoom = Math.min(MAX_ZOOM, Math.max(1, Number(value)));
            const ratio = this.scale / old;
            this.x = cx - (cx - this.x) * ratio;
            this.y = cy - (cy - this.y) * ratio;
            this.clamp();
        },

        onWheel(e) {
            const rect = this.$refs.viewport.getBoundingClientRect();
            this.setZoom(this.zoom * (e.deltaY < 0 ? 1.1 : 0.9), e.clientX - rect.left, e.clientY - rect.top);
        },

        onPointerDown(e) {
            this.$refs.viewport.setPointerCapture(e.pointerId);
            this.pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
            if (this.pointers.size === 2) {
                const [a, b] = [...this.pointers.values()];
                this.pinchStart = { dist: Math.hypot(a.x - b.x, a.y - b.y), zoom: this.zoom };
            }
        },

        onPointerMove(e) {
            const prev = this.pointers.get(e.pointerId);
            if (!prev) return;
            this.pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });

            if (this.pointers.size === 2 && this.pinchStart) {
                // Pellizcar para hacer zoom (teléfono).
                const [a, b] = [...this.pointers.values()];
                const rect = this.$refs.viewport.getBoundingClientRect();
                const dist = Math.hypot(a.x - b.x, a.y - b.y);
                this.setZoom(this.pinchStart.zoom * (dist / this.pinchStart.dist), (a.x + b.x) / 2 - rect.left, (a.y + b.y) / 2 - rect.top);
                return;
            }

            // Arrastrar para ubicar la foto.
            this.x += e.clientX - prev.x;
            this.y += e.clientY - prev.y;
            this.clamp();
        },

        onPointerUp(e) {
            this.pointers.delete(e.pointerId);
            if (this.pointers.size < 2) this.pinchStart = null;
        },

        retake() {
            this.src = null;
            if (this.cameraSupported) {
                this.startCamera();
            } else {
                this.close();
            }
        },

        close() {
            this.stopCamera();
            this.open = false;
            this.src = null;
            this.pointers.clear();
        },

        /** Recorta lo que se ve en el recuadro y lo sube a Livewire. */
        usePhoto() {
            const img = this.$refs.cropImage;
            const canvas = document.createElement('canvas');
            canvas.width = OUTPUT;
            canvas.height = OUTPUT;
            const ctx = canvas.getContext('2d');
            ctx.imageSmoothingQuality = 'high';
            const s = this.scale;
            ctx.drawImage(img, -this.x / s, -this.y / s, VIEWPORT / s, VIEWPORT / s, 0, 0, OUTPUT, OUTPUT);

            this.uploading = true;
            canvas.toBlob((blob) => {
                const file = new File([blob], 'foto.jpg', { type: 'image/jpeg' });
                this.$wire.upload(
                    model,
                    file,
                    () => { this.uploading = false; this.close(); },
                    () => { this.uploading = false; this.error = 'No se pudo subir la foto. Probá de nuevo.'; },
                );
            }, 'image/jpeg', 0.9);
        },

        destroy() {
            this.stopCamera();
        },
    });

    const register = () => window.Alpine.data('photoCapture', photoCapture);
    if (window.Alpine) {
        register();
    } else {
        document.addEventListener('alpine:init', register);
    }
})();

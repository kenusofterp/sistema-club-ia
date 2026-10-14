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

    /*
     * Foto de un comprobante (ticket, factura): cámara trasera por defecto y después un paso para
     * encuadrarla (zoom y arrastre) en un recuadro vertical 3:4. Al principio se ve el comprobante
     * entero; lo que queda fuera de la foto se completa en blanco. Se sube como JPG (hasta 1200×1600).
     * Uso: <div x-data="documentCapture('attachment')"> (ver components/document-capture.blade.php).
     */
    const DOC_VW = 300; // recuadro de encuadre en pantalla (px)
    const DOC_VH = 400;
    const DOC_OUT_W = 1200; // tamaño máximo de la imagen final (px)
    const DOC_CAPTURE_MAX = 2400; // lado mayor de la foto tomada, para tener detalle al hacer zoom
    const DOC_MAX_ZOOM = 5;

    const documentCapture = (model) => ({
        open: false,
        step: 'camera', // camera | crop
        error: null,
        uploading: false,
        stream: null,
        facing: 'environment',
        canSwitch: false,
        cameraSupported: !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.isSecureContext),

        // Encuadre
        src: null,
        natW: 0,
        natH: 0,
        zoom: 1,
        x: 0,
        y: 0,
        pointers: new Map(),
        pinchStart: null,

        /** Escala mínima: el comprobante entero entra en el recuadro. */
        get fitScale() {
            return this.natW ? Math.min(DOC_VW / this.natW, DOC_VH / this.natH) : 1;
        },
        get scale() {
            return this.fitScale * this.zoom;
        },
        get imageStyle() {
            return `width:${this.natW}px;height:${this.natH}px;transform:translate(${this.x}px, ${this.y}px) scale(${this.scale});transform-origin:0 0;max-width:none;`;
        },

        // ---- Cámara ----
        async startCamera() {
            this.error = null;
            this.src = null;
            this.step = 'camera';
            this.open = true;
            this.stopCamera();
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: this.facing, width: { ideal: 1920 }, height: { ideal: 1440 } },
                    audio: false,
                });
                this.$refs.docVideo.srcObject = this.stream;
                await this.$refs.docVideo.play();
                const devices = await navigator.mediaDevices.enumerateDevices();
                this.canSwitch = devices.filter((d) => d.kind === 'videoinput').length > 1;
            } catch (e) {
                this.error = e && e.name === 'NotAllowedError'
                    ? 'No se dio permiso para usar la cámara. Habilitalo en el navegador (candado junto a la dirección).'
                    : 'No se pudo abrir la cámara. Probá subiendo el archivo.';
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
            const video = this.$refs.docVideo;
            if (!video.videoWidth) return;
            const ratio = Math.min(1, DOC_CAPTURE_MAX / Math.max(video.videoWidth, video.videoHeight));
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(video.videoWidth * ratio);
            canvas.height = Math.round(video.videoHeight * ratio);
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
            this.stopCamera();
            this.loadImage(canvas.toDataURL('image/jpeg', 0.92));
        },

        // ---- Encuadre (zoom y arrastre) ----
        loadImage(src) {
            const img = new Image();
            img.onload = () => {
                this.natW = img.naturalWidth;
                this.natH = img.naturalHeight;
                this.src = src;
                this.zoom = 1;
                this.x = 0;
                this.y = 0;
                this.clamp();
                this.step = 'crop';
            };
            img.onerror = () => (this.error = 'No se pudo leer la foto.');
            img.src = src;
        },

        /** Si la foto es más chica que el recuadro, queda centrada; si es más grande, no deja huecos. */
        clamp() {
            const w = this.natW * this.scale;
            const h = this.natH * this.scale;
            this.x = w <= DOC_VW ? (DOC_VW - w) / 2 : Math.min(0, Math.max(DOC_VW - w, this.x));
            this.y = h <= DOC_VH ? (DOC_VH - h) / 2 : Math.min(0, Math.max(DOC_VH - h, this.y));
        },

        setZoom(value, cx = DOC_VW / 2, cy = DOC_VH / 2) {
            const old = this.scale;
            this.zoom = Math.min(DOC_MAX_ZOOM, Math.max(1, Number(value)));
            const ratio = this.scale / old;
            this.x = cx - (cx - this.x) * ratio;
            this.y = cy - (cy - this.y) * ratio;
            this.clamp();
        },

        onWheel(e) {
            const rect = this.$refs.docViewport.getBoundingClientRect();
            this.setZoom(this.zoom * (e.deltaY < 0 ? 1.1 : 0.9), e.clientX - rect.left, e.clientY - rect.top);
        },

        onPointerDown(e) {
            this.$refs.docViewport.setPointerCapture(e.pointerId);
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
                const [a, b] = [...this.pointers.values()];
                const rect = this.$refs.docViewport.getBoundingClientRect();
                const dist = Math.hypot(a.x - b.x, a.y - b.y);
                this.setZoom(this.pinchStart.zoom * (dist / this.pinchStart.dist), (a.x + b.x) / 2 - rect.left, (a.y + b.y) / 2 - rect.top);
                return;
            }

            this.x += e.clientX - prev.x;
            this.y += e.clientY - prev.y;
            this.clamp();
        },

        onPointerUp(e) {
            this.pointers.delete(e.pointerId);
            if (this.pointers.size < 2) this.pinchStart = null;
        },

        retake() {
            this.startCamera();
        },

        close() {
            this.stopCamera();
            this.open = false;
            this.src = null;
            this.pointers.clear();
        },

        /** Guarda lo que se ve en el recuadro, con el detalle de la foto original. */
        usePhoto() {
            // Ancho final: el detalle real que hay en el recuadro, sin pasar de 1200 px.
            const outW = Math.max(300, Math.min(DOC_OUT_W, Math.round(DOC_VW / this.scale)));
            const outH = Math.round(outW * DOC_VH / DOC_VW);
            const k = outW / DOC_VW;
            const canvas = document.createElement('canvas');
            canvas.width = outW;
            canvas.height = outH;
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, outW, outH);
            ctx.imageSmoothingQuality = 'high';
            ctx.setTransform(k * this.scale, 0, 0, k * this.scale, k * this.x, k * this.y);
            ctx.drawImage(this.$refs.docImage, 0, 0);

            this.uploading = true;
            canvas.toBlob((blob) => {
                const file = new File([blob], 'comprobante.jpg', { type: 'image/jpeg' });
                this.$wire.upload(
                    model,
                    file,
                    () => { this.uploading = false; this.close(); },
                    () => { this.uploading = false; this.error = 'No se pudo subir la foto. Probá de nuevo.'; },
                );
            }, 'image/jpeg', 0.85);
        },

        destroy() {
            this.stopCamera();
        },
    });

    const register = () => {
        window.Alpine.data('photoCapture', photoCapture);
        window.Alpine.data('documentCapture', documentCapture);
    };
    if (window.Alpine) {
        register();
    } else {
        document.addEventListener('alpine:init', register);
    }
})();

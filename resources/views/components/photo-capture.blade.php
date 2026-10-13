{{--
    Foto desde la cámara (webcam, notebook o teléfono) o desde un archivo, con recorte cuadrado:
    zoom (control, rueda del mouse o pellizco) y arrastre para ubicarla. Sube a la propiedad Livewire `model`.
    Lógica en public/js/photo-capture.js.
--}}
@props(['model', 'file' => null, 'current' => null, 'label' => 'Foto', 'help' => null])
@php
    $preview = $file && method_exists($file, 'temporaryUrl') ? rescue(fn () => $file->temporaryUrl(), null, false) : $current;
@endphp
<div x-data="photoCapture(@js($model))" x-on:keydown.escape.window="open && close()">
    <span class="form-label">{{ $label }}</span>
    <div class="flex items-start gap-4">
        <div class="aspect-square w-40 shrink-0 overflow-hidden rounded-lg border border-dashed border-slate-300 bg-slate-50">
            @if ($preview)
                <img src="{{ $preview }}" alt="" class="size-full object-cover">
            @else
                <div class="grid size-full place-items-center text-slate-400"><x-icon name="user" class="size-10" /></div>
            @endif
        </div>
        <div class="min-w-0 flex-1 space-y-2">
            <div class="flex flex-wrap gap-2">
                <button type="button" x-show="cameraSupported" x-on:click="startCamera()" class="btn-primary btn-sm"><x-icon name="photo" class="size-4" /> Sacar foto</button>
                {{-- Sin acceso a la cámara desde el navegador (sitio sin HTTPS): en el teléfono abre la cámara igual. --}}
                <label x-show="! cameraSupported" class="btn-primary btn-sm cursor-pointer">
                    <x-icon name="photo" class="size-4" /> Sacar foto
                    <input type="file" accept="image/*" capture="user" class="sr-only" x-on:change="pickFile($event)">
                </label>
                <label class="btn-secondary btn-sm cursor-pointer">
                    Elegir archivo
                    <input type="file" accept="image/*" class="sr-only" x-on:change="pickFile($event)">
                </label>
            </div>
            <div wire:loading wire:target="{{ $model }}" class="text-xs text-slate-500">Subiendo…</div>
            <p class="form-help">{{ $help ?? 'Después de sacarla podés acercarla y moverla para encuadrar la cara.' }}</p>
            <p x-show="error && ! open" x-text="error" class="form-error"></p>
            @error($model) <p class="form-error">{{ $message }}</p> @enderror
        </div>
    </div>

    {{-- Cámara y recorte --}}
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(15, 23, 42, 0.8);" role="dialog" aria-modal="true">
        <div class="w-full max-w-sm rounded-2xl bg-white p-4 shadow-xl">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="font-semibold text-slate-900" x-text="step === 'camera' ? 'Sacar foto' : 'Ubicá la foto'"></h3>
                <button type="button" x-on:click="close()" class="btn-ghost px-2" aria-label="Cerrar"><x-icon name="x" class="size-5" /></button>
            </div>

            <p x-show="error" x-text="error" class="mb-3 rounded-lg bg-red-50 p-3 text-sm text-red-700"></p>

            {{-- Paso 1: cámara --}}
            <div x-show="step === 'camera'">
                <div class="relative mx-auto overflow-hidden rounded-xl bg-black" style="width: 288px; height: 288px;">
                    <video x-ref="video" playsinline muted class="size-full object-cover" x-bind:style="facing === 'user' ? 'transform: scaleX(-1)' : ''"></video>
                    <div class="pointer-events-none absolute inset-6 rounded-full" style="border: 2px solid rgba(255,255,255,.6);"></div>
                </div>
                <div class="mt-4 flex gap-2">
                    <button type="button" x-show="canSwitch" x-on:click="switchCamera()" class="btn-secondary" title="Cambiar de cámara"><x-icon name="refresh" class="size-5" /></button>
                    <button type="button" x-on:click="takePhoto()" x-bind:disabled="! stream" class="btn-primary flex-1 py-3"><x-icon name="photo" class="size-5" /> Sacar</button>
                </div>
            </div>

            {{-- Paso 2: recorte (arrastrar, zoom con el control, la rueda o pellizcando) --}}
            <div x-show="step === 'crop'">
                <div x-ref="viewport"
                     class="relative mx-auto overflow-hidden rounded-xl bg-slate-200"
                     style="width: 288px; height: 288px; touch-action: none; cursor: move; user-select: none;"
                     x-on:pointerdown="onPointerDown($event)"
                     x-on:pointermove="onPointerMove($event)"
                     x-on:pointerup="onPointerUp($event)"
                     x-on:pointercancel="onPointerUp($event)"
                     x-on:wheel.prevent="onWheel($event)">
                    <img x-ref="cropImage" x-bind:src="src" x-bind:style="imageStyle" alt="" draggable="false" class="pointer-events-none absolute top-0 left-0">
                    <div class="pointer-events-none absolute inset-6 rounded-full" style="border: 2px solid rgba(255,255,255,.75); box-shadow: 0 0 0 999px rgba(15,23,42,.3);"></div>
                </div>
                <div class="mt-4 flex items-center gap-3">
                    <x-icon name="search" class="size-4 shrink-0 text-slate-400" />
                    <input type="range" min="1" max="4" step="0.01" x-bind:value="zoom" x-on:input="setZoom($event.target.value)" class="w-full" style="accent-color: var(--brand);" aria-label="Zoom">
                </div>
                <p class="mt-1 text-center text-xs text-slate-500">Arrastrá para moverla · acercá con el control, la rueda o pellizcando</p>
                <div class="mt-4 flex gap-2">
                    <button type="button" x-on:click="retake()" class="btn-secondary flex-1">Otra</button>
                    <button type="button" x-on:click="usePhoto()" x-bind:disabled="uploading" class="btn-primary flex-1">
                        <span x-show="! uploading">Usar esta foto</span>
                        <span x-show="uploading">Subiendo…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

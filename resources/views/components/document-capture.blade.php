{{--
    Comprobante: subir un archivo (foto o PDF) o sacarle una foto con la cámara (webcam o teléfono).
    Después de sacar la foto se encuadra con zoom y arrastre. Sube a la propiedad Livewire `model`.
    Lógica en public/js/photo-capture.js (documentCapture).
--}}
@props(['model', 'file' => null, 'current' => null, 'accept' => 'image/*,application/pdf'])
@php
    $preview = $file && method_exists($file, 'temporaryUrl') && str_starts_with((string) $file->getMimeType(), 'image/')
        ? rescue(fn () => $file->temporaryUrl(), null, false)
        : null;
@endphp
<div x-data="documentCapture(@js($model))">
    <div class="flex flex-wrap items-center gap-2">
        <button type="button" x-show="cameraSupported" x-on:click="startCamera()" class="btn-secondary btn-sm"><x-icon name="photo" class="size-4" /> Sacar foto</button>
        {{-- Sin acceso a la cámara desde el navegador (sitio sin HTTPS): en el teléfono abre la cámara igual. --}}
        <label x-show="! cameraSupported" class="btn-secondary btn-sm cursor-pointer">
            <x-icon name="photo" class="size-4" /> Sacar foto
            <input type="file" accept="image/*" capture="environment" wire:model="{{ $model }}" class="sr-only">
        </label>
        <label class="btn-secondary btn-sm cursor-pointer">
            <x-icon name="document" class="size-4" /> Subir archivo
            <input type="file" accept="{{ $accept }}" wire:model="{{ $model }}" class="sr-only">
        </label>
        <span wire:loading wire:target="{{ $model }}" class="text-xs text-slate-500">Subiendo…</span>
    </div>

    @if ($file)
        <div class="mt-2 flex items-center gap-3 text-sm">
            @if ($preview)
                <img src="{{ $preview }}" alt="" class="h-16 w-auto rounded border border-slate-200 object-cover">
            @endif
            <span class="text-emerald-700">Comprobante listo: {{ $file->getClientOriginalName() }}</span>
            <button type="button" wire:click="$set('{{ $model }}', null)" class="text-xs font-medium text-red-600 hover:underline">Quitar</button>
        </div>
    @elseif ($current)
        <a href="{{ $current }}" target="_blank" class="mt-2 inline-block text-sm font-medium text-brand-700">Ver el comprobante cargado</a>
    @endif

    <p x-show="error && ! open" x-text="error" class="form-error"></p>

    {{-- Cámara (se lleva al final de la página para que no quede recortada dentro de otro modal) --}}
    <template x-teleport="body">
    <div x-show="open" x-cloak class="fixed inset-0 flex items-center justify-center p-4" style="z-index: 60; background: rgba(15, 23, 42, 0.85);" role="dialog" aria-modal="true">
        <div class="w-full max-w-sm rounded-2xl bg-white p-4 shadow-xl">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="font-semibold text-slate-900" x-text="step === 'crop' ? 'Encuadrá el comprobante' : 'Foto del comprobante'"></h3>
                <button type="button" x-on:click="close()" class="btn-ghost px-2" aria-label="Cerrar"><x-icon name="x" class="size-5" /></button>
            </div>

            <p x-show="error" x-text="error" class="mb-3 rounded-lg bg-red-50 p-3 text-sm text-red-700"></p>

            {{-- Paso 1: cámara --}}
            <div x-show="step === 'camera'">
                <div class="mx-auto overflow-hidden rounded-xl bg-black" style="width: 300px; height: 400px;">
                    <video x-ref="docVideo" playsinline muted class="size-full object-contain"></video>
                </div>
                <div class="mt-4 flex gap-2">
                    <button type="button" x-show="canSwitch" x-on:click="switchCamera()" class="btn-secondary" title="Cambiar de cámara"><x-icon name="refresh" class="size-5" /></button>
                    <button type="button" x-on:click="takePhoto()" x-bind:disabled="! stream" class="btn-primary flex-1 py-3"><x-icon name="photo" class="size-5" /> Sacar</button>
                </div>
            </div>

            {{-- Paso 2: encuadre (arrastrar; zoom con el control, la rueda o pellizcando) --}}
            <div x-show="step === 'crop'">
                <div x-ref="docViewport"
                     class="relative mx-auto overflow-hidden rounded-xl"
                     style="width: 300px; height: 400px; background: #ffffff; outline: 1px solid #e2e8f0; touch-action: none; cursor: move; user-select: none;"
                     x-on:pointerdown="onPointerDown($event)"
                     x-on:pointermove="onPointerMove($event)"
                     x-on:pointerup="onPointerUp($event)"
                     x-on:pointercancel="onPointerUp($event)"
                     x-on:wheel.prevent="onWheel($event)">
                    <img x-ref="docImage" x-bind:src="src" x-bind:style="imageStyle" alt="" draggable="false" class="pointer-events-none absolute top-0 left-0">
                </div>
                <div class="mt-4 flex items-center gap-3">
                    <x-icon name="search" class="size-4 shrink-0 text-slate-400" />
                    <input type="range" min="1" max="5" step="0.01" x-bind:value="zoom" x-on:input="setZoom($event.target.value)" class="w-full" style="accent-color: var(--brand);" aria-label="Zoom">
                </div>
                <p class="mt-1 text-center text-xs text-slate-500">Arrastrá para moverlo · acercá con el control, la rueda o pellizcando. Se guarda lo que ves en el recuadro.</p>
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
    </template>
</div>

{{-- Contenedor de campo: etiqueta + control (slot) + error de validación + ayuda. --}}
@props(['label' => null, 'for' => null, 'error' => null, 'help' => null, 'required' => false])
<div {{ $attributes }}>
    @if ($label)
        <label @if ($for) for="{{ $for }}" @endif class="form-label">
            {{ $label }}@if ($required)<span class="text-red-500"> *</span>@endif
        </label>
    @endif
    {{ $slot }}
    @if ($error)
        @error($error)
            <p class="form-error">{{ $message }}</p>
        @enderror
    @endif
    @if ($help)
        <p class="form-help">{{ $help }}</p>
    @endif
</div>

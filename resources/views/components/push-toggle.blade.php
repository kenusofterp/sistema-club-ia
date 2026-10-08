{{--
    Activar / desactivar las notificaciones push en este teléfono. Lógica en resources/js/app.js (pushToggle).
    compact: solo se muestra mientras falte activarlas (para inicio/tablero).
--}}
@props(['compact' => false])
<div x-data="pushToggle" {{ $attributes->class(['text-sm']) }}
     @if ($compact) x-show="['default', 'ios-install', 'denied'].includes(state)" x-cloak @endif>
    <div class="flex items-start gap-3 rounded-xl bg-white p-4 ring-1 ring-slate-200">
        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-brand-50 text-brand-700"><x-icon name="megaphone" class="size-5" /></span>
        <div class="min-w-0 flex-1">
            <p class="font-medium text-slate-900">Notificaciones en el teléfono</p>
            <p class="mt-0.5 text-slate-500" x-show="state === 'default' || state === 'working'">Enterate al instante si no hay clase, cuando se acredita un pago y más.</p>
            <p class="mt-0.5 text-emerald-700" x-show="state === 'subscribed'" x-cloak>Activadas en este dispositivo.</p>
            <p class="mt-0.5 text-slate-500" x-show="state === 'ios-install'" x-cloak>En iPhone, primero instalá la app: tocá <strong>Compartir</strong> y luego <strong>Agregar a inicio</strong>. Después abrila desde el ícono y activá las notificaciones.</p>
            <p class="mt-0.5 text-amber-700" x-show="state === 'denied'" x-cloak>Las bloqueaste en este navegador. Habilitalas desde la configuración del sitio (candado junto a la dirección).</p>
            <p class="mt-0.5 text-slate-500" x-show="state === 'unsupported'" x-cloak>Este navegador no admite notificaciones.</p>
        </div>
        <div class="shrink-0">
            <button type="button" class="btn-primary btn-sm" x-show="state === 'default' || state === 'working'" x-on:click="enable" x-bind:disabled="state === 'working'">Activar</button>
            <button type="button" class="btn-ghost btn-sm" x-show="state === 'subscribed'" x-cloak x-on:click="disable">Desactivar</button>
        </div>
    </div>
</div>

<div>
    <h1 class="font-display text-3xl font-bold tracking-tight text-slate-900">Recuperar contraseña</h1>

    @if ($sent)
        <div class="mt-6 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">
            Si el correo está registrado, vas a recibir un enlace para crear una nueva contraseña. Revisá también la carpeta de spam.
        </div>
    @else
        <p class="mt-2 text-sm text-slate-500">Ingresá tu correo y te enviaremos un enlace para restablecerla.</p>
        <form wire:submit="sendLink" class="mt-8 space-y-5">
            <x-field label="Correo electrónico" for="email" error="email">
                <input id="email" type="email" wire:model="email" class="form-input" autocomplete="email" autofocus required>
            </x-field>
            <button type="submit" class="btn-primary w-full py-2.5" wire:loading.attr="disabled">Enviar enlace</button>
        </form>
    @endif

    <a href="{{ route('login') }}" wire:navigate class="mt-8 inline-flex items-center gap-2 text-sm font-medium text-brand-700"><x-icon name="arrow-left" class="size-4" /> Volver a ingresar</a>
</div>

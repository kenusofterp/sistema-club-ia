<div>
    <h1 class="font-display text-3xl font-bold tracking-tight text-slate-900">Ingresar</h1>
    <p class="mt-2 text-sm text-slate-500">Administración del club y portal de socios.</p>

    <form wire:submit="login" class="mt-8 space-y-5">
        <x-field label="Correo electrónico" for="email" error="email">
            <input id="email" type="email" wire:model="email" class="form-input" autocomplete="username" autofocus required>
        </x-field>

        <x-field for="password" error="password">
            <div class="mb-1 flex items-center justify-between">
                <label for="password" class="text-sm font-medium text-slate-700">Contraseña</label>
                <a href="{{ route('password.request') }}" wire:navigate class="text-sm font-medium text-brand-700 hover:underline">¿La olvidaste?</a>
            </div>
            <div x-data="{ show: false }" class="relative">
                <input id="password" :type="show ? 'text' : 'password'" wire:model="password" class="form-input pr-10" autocomplete="current-password" required>
                <button type="button" x-on:click="show = !show" class="absolute inset-y-0 right-0 px-3 text-slate-400 hover:text-slate-600" :aria-label="show ? 'Ocultar contraseña' : 'Mostrar contraseña'">
                    <x-icon name="eye" class="size-5" />
                </button>
            </div>
        </x-field>

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" wire:model="remember" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            Mantener la sesión iniciada
        </label>

        <button type="submit" class="btn-primary w-full py-2.5" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="login">Ingresar</span>
            <span wire:loading wire:target="login">Verificando…</span>
        </button>
    </form>

    @if (setting('site.join_enabled', true))
        <p class="mt-8 text-center text-sm text-slate-500">
            ¿Todavía no sos socio? <a href="{{ route('site.join') }}" class="font-semibold text-brand-700 hover:underline">Asociate</a>
        </p>
    @endif
</div>

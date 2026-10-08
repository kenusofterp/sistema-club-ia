<div>
    <h1 class="font-display text-3xl font-bold tracking-tight text-slate-900">Crear contraseña</h1>
    <p class="mt-2 text-sm text-slate-500">Mínimo 8 caracteres, con letras y números.</p>

    <form wire:submit="resetPassword" class="mt-8 space-y-5">
        <x-field label="Correo electrónico" for="email" error="email">
            <input id="email" type="email" wire:model="email" class="form-input" autocomplete="username" required>
        </x-field>
        <x-field label="Nueva contraseña" for="password" error="password">
            <input id="password" type="password" wire:model="password" class="form-input" autocomplete="new-password" required>
        </x-field>
        <x-field label="Repetir contraseña" for="password_confirmation">
            <input id="password_confirmation" type="password" wire:model="password_confirmation" class="form-input" autocomplete="new-password" required>
        </x-field>
        <button type="submit" class="btn-primary w-full py-2.5" wire:loading.attr="disabled">Guardar contraseña</button>
    </form>
</div>

<div>
    <x-page-header title="Mi perfil" />

    <div class="grid gap-6 lg:grid-cols-2">
        <form wire:submit="saveProfile" class="card grid gap-4 p-6">
            <h2 class="font-semibold text-slate-900">Datos personales</h2>
            <x-image-upload model="avatar" :file="$avatar" :current="auth()->user()->avatarUrl()" label="Foto" aspect="aspect-square" />
            <x-field label="Nombre" for="name" error="name" required><input id="name" wire:model="name" class="form-input"></x-field>
            <x-field label="Correo electrónico" for="email" error="email" required><input id="email" type="email" wire:model="email" class="form-input"></x-field>
            <x-field label="Teléfono" for="phone" error="phone"><input id="phone" wire:model="phone" class="form-input"></x-field>
            <div class="flex justify-end"><button type="submit" class="btn-primary">Guardar</button></div>
        </form>

        <form wire:submit="changePassword" class="card grid content-start gap-4 p-6">
            <h2 class="font-semibold text-slate-900">Cambiar contraseña</h2>
            <x-field label="Contraseña actual" for="current_password" error="current_password"><input id="current_password" type="password" wire:model="current_password" class="form-input" autocomplete="current-password"></x-field>
            <x-field label="Nueva contraseña" for="password" error="password"><input id="password" type="password" wire:model="password" class="form-input" autocomplete="new-password"></x-field>
            <x-field label="Repetir nueva contraseña" for="password_confirmation"><input id="password_confirmation" type="password" wire:model="password_confirmation" class="form-input" autocomplete="new-password"></x-field>
            <div class="flex justify-end"><button type="submit" class="btn-primary">Actualizar contraseña</button></div>
        </form>
    </div>
</div>

<div class="space-y-6">
    <h1 class="font-display text-2xl font-bold text-slate-900">Mis datos</h1>

    <div class="card p-5">
        <dl class="grid gap-4 text-sm sm:grid-cols-3">
            <div><dt class="text-xs text-slate-500">Nombre</dt><dd class="font-medium">{{ $member->fullName() }}</dd></div>
            <div><dt class="text-xs text-slate-500">Documento</dt><dd class="font-medium">{{ $member->document_type }} {{ $member->document_number }}</dd></div>
            <div><dt class="text-xs text-slate-500">Nacimiento</dt><dd class="font-medium">{{ $member->birth_date->format('d/m/Y') }}</dd></div>
            <div><dt class="text-xs text-slate-500">Categoría</dt><dd class="font-medium">{{ $member->category->name }}</dd></div>
            <div><dt class="text-xs text-slate-500">Correo</dt><dd class="font-medium break-all">{{ $member->email }}</dd></div>
            <div><dt class="text-xs text-slate-500">N° de socio</dt><dd class="font-medium">{{ $member->member_number }}</dd></div>
        </dl>
        <p class="mt-4 text-xs text-slate-500">Para modificar estos datos comunicate con la secretaría del club.</p>
    </div>

    <form wire:submit="save" class="card grid gap-4 p-5 sm:grid-cols-2">
        <h2 class="font-semibold text-slate-900 sm:col-span-2">Datos de contacto</h2>
        <div class="sm:col-span-2"><x-image-upload model="photo" :file="$photo" :current="$member->photoUrl()" label="Foto del carnet" aspect="aspect-square" /></div>
        <x-field label="Teléfono" for="phone" error="phone" required><input id="phone" wire:model="phone" class="form-input"></x-field>
        <x-field label="Dirección" for="address" error="address"><input id="address" wire:model="address" class="form-input"></x-field>
        <x-field label="Localidad" for="city" error="city"><input id="city" wire:model="city" class="form-input"></x-field>
        <x-field label="Contacto de emergencia" for="emergency_contact_name" error="emergency_contact_name"><input id="emergency_contact_name" wire:model="emergency_contact_name" class="form-input"></x-field>
        <x-field label="Teléfono de emergencia" for="emergency_contact_phone" error="emergency_contact_phone"><input id="emergency_contact_phone" wire:model="emergency_contact_phone" class="form-input"></x-field>
        <div class="flex justify-end sm:col-span-2"><button type="submit" class="btn-primary">Guardar</button></div>
    </form>

    <form wire:submit="changePassword" class="card grid gap-4 p-5 sm:grid-cols-3">
        <h2 class="font-semibold text-slate-900 sm:col-span-3">Cambiar contraseña</h2>
        <x-field label="Contraseña actual" for="current_password" error="current_password"><input id="current_password" type="password" wire:model="current_password" class="form-input" autocomplete="current-password"></x-field>
        <x-field label="Nueva contraseña" for="password" error="password"><input id="password" type="password" wire:model="password" class="form-input" autocomplete="new-password"></x-field>
        <x-field label="Repetir" for="password_confirmation"><input id="password_confirmation" type="password" wire:model="password_confirmation" class="form-input" autocomplete="new-password"></x-field>
        <div class="flex justify-end sm:col-span-3"><button type="submit" class="btn-primary">Actualizar contraseña</button></div>
    </form>
</div>

<div>
    <x-page-header title="Usuarios" :subtitle="'Personal con acceso a '.$currentOrganization?->name.'. Los roles se asignan por entidad.'">
        <x-slot:actions>
            <button type="button" wire:click="create" class="btn-primary"><x-icon name="user-plus" class="size-4" /> Agregar usuario</button>
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <div class="border-b border-slate-200 p-4">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar por nombre o correo…" class="form-input sm:max-w-md">
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Usuario</th><th>Roles en esta entidad</th><th class="hidden lg:table-cell">Otras entidades</th><th>Último ingreso</th><th>Estado</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($users as $user)
                        <tr wire:key="u-{{ $user->id }}">
                            <td>
                                <p class="font-medium text-slate-900">{{ $user->name }} @if ($user->is(auth()->user()))<span class="text-xs text-slate-400">(vos)</span>@endif</p>
                                <p class="text-xs text-slate-500">{{ $user->email }}</p>
                            </td>
                            <td>
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($user->roles as $role)
                                        <x-badge color="blue">{{ $role->name }}</x-badge>
                                    @endforeach
                                </div>
                            </td>
                            <td class="hidden text-xs text-slate-500 lg:table-cell">{{ $otherOrgs[$user->id] ?? '—' }}</td>
                            <td class="text-sm text-slate-500">{{ $user->last_login_at?->diffForHumans() ?? 'Nunca' }}</td>
                            <td><x-badge :color="$user->is_active ? 'green' : 'red'">{{ $user->is_active ? 'Activo' : 'Inactivo' }}</x-badge></td>
                            <td class="text-right whitespace-nowrap">
                                <button type="button" wire:click="sendReset({{ $user->id }})" wire:confirm="¿Enviar enlace para restablecer la contraseña?" class="btn-ghost btn-sm" title="Enviar enlace de contraseña"><x-icon name="key" class="size-4" /></button>
                                <button type="button" wire:click="edit({{ $user->id }})" class="btn-ghost btn-sm" title="Editar"><x-icon name="pencil" class="size-4" /></button>
                                @if (! $user->is(auth()->user()))
                                    <button type="button" wire:click="removeFromOrganization({{ $user->id }})" wire:confirm="¿Quitar a {{ $user->name }} de esta entidad? Conserva su acceso a las demás." class="btn-ghost btn-sm text-red-600" title="Quitar de esta entidad"><x-icon name="ban" class="size-4" /></button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state icon="user" title="Sin usuarios" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())<div class="border-t border-slate-200 px-4 py-3">{{ $users->links() }}</div>@endif
    </div>

    <x-modal wire:model="showForm" :title="$editingId ? 'Editar usuario' : 'Agregar usuario'">
        <form wire:submit="save" id="user-form" class="grid gap-4 sm:grid-cols-2">
            <x-field label="Nombre" for="name" error="name" required>
                <input id="name" wire:model="name" class="form-input">
            </x-field>
            <x-field label="Correo electrónico" for="email" error="email" required>
                <input id="email" type="email" wire:model="email" class="form-input">
            </x-field>
            <x-field label="Teléfono" for="phone" error="phone">
                <input id="phone" wire:model="phone" class="form-input">
            </x-field>
            <x-field :label="$editingId ? 'Nueva contraseña (opcional)' : 'Contraseña'" for="password" error="password">
                <input id="password" type="password" wire:model="password" class="form-input" autocomplete="new-password">
            </x-field>
            @unless ($editingId)
                <label class="flex items-center gap-2 text-sm text-slate-600 sm:col-span-2"><input type="checkbox" wire:model="sendInvite" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Si no se define contraseña, enviar un correo para que la cree</label>
            @endunless
            <div class="sm:col-span-2">
                <span class="form-label">Roles en {{ $currentOrganization?->name }}</span>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($availableRoles as $role)
                        <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <input type="checkbox" value="{{ $role->name }}" wire:model="roles" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> {{ $role->name }}
                        </label>
                    @endforeach
                </div>
                <p class="form-help">Para darle acceso a otra entidad, cambiá de entidad y asignale roles allí.</p>
            </div>
            @if ($availableFacilities->isNotEmpty())
                <div class="sm:col-span-2">
                    <span class="form-label">Sedes donde da clases (agenda de clases)</span>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ($availableFacilities as $facility)
                            <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                <input type="checkbox" value="{{ $facility->id }}" wire:model="facilityIds" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> {{ $facility->name }}
                            </label>
                        @endforeach
                    </div>
                    <p class="form-help">Solo para profesores. Las sedes de otras entidades se asignan desde cada entidad.</p>
                </div>
            @endif
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Usuario activo</label>
            @error('is_active') <p class="form-error sm:col-span-2">{{ $message }}</p> @enderror
        </form>
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="open = false">Cancelar</button>
            <button type="submit" form="user-form" class="btn-primary">Guardar</button>
        </x-slot:footer>
    </x-modal>
</div>

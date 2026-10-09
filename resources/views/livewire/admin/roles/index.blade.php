<div>
    <x-page-header title="Roles y permisos" subtitle="Los roles son comunes a todas las entidades. A cada usuario se le asignan roles por entidad desde Usuarios. El super administrador tiene acceso total sin necesitar roles." />

    <div class="grid gap-6 lg:grid-cols-4">
        <div class="space-y-4">
            <div class="card divide-y divide-slate-100">
                @foreach ($roles as $role)
                    <button type="button" wire:click="selectRole({{ $role->id }})" wire:key="r-{{ $role->id }}"
                            @class(['flex w-full items-center justify-between px-4 py-3 text-left text-sm', 'bg-brand-50 font-semibold text-brand-800' => $roleId === $role->id, 'hover:bg-slate-50' => $roleId !== $role->id])>
                        <span>{{ $role->name }}</span>
                        <span class="text-xs text-slate-400" title="Usuarios con este rol">{{ $usage[$role->id] ?? 0 }}</span>
                    </button>
                @endforeach
            </div>
            <form wire:submit="createRole" class="card space-y-2 p-4">
                <x-field label="Nuevo rol" for="newRole" error="newRole">
                    <input id="newRole" wire:model="newRole" class="form-input" placeholder="ej. coordinador">
                </x-field>
                <button type="submit" class="btn-secondary btn-sm w-full"><x-icon name="plus" class="size-4" /> Crear rol</button>
            </form>
        </div>

        <div class="lg:col-span-3">
            @if ($current)
                <div class="card">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-6 py-4">
                        <h2 class="font-semibold text-slate-900">Permisos de «{{ $current->name }}»</h2>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" wire:click="toggleAll(true)" class="btn-secondary btn-sm"><x-icon name="check" class="size-4" /> Seleccionar todo</button>
                            <button type="button" wire:click="toggleAll(false)" class="btn-ghost btn-sm">Quitar todo</button>
                            <button type="button" wire:click="deleteRole" wire:confirm="¿Eliminar el rol {{ $current->name }}?" class="btn-ghost btn-sm text-red-600"><x-icon name="trash" class="size-4" /> Eliminar</button>
                            <button type="button" wire:click="save" class="btn-primary btn-sm"><x-icon name="check" class="size-4" /> Guardar permisos</button>
                        </div>
                    </div>
                    <div class="grid gap-6 p-6 md:grid-cols-2">
                        @foreach ($grouped as $group => $perms)
                            @php($allInGroup = ! array_diff(array_keys($perms), $permissions))
                            <fieldset wire:key="g-{{ $loop->index }}">
                                <legend class="mb-2 flex w-full items-center justify-between gap-2">
                                    <span class="text-xs font-semibold tracking-wider text-slate-500 uppercase">{{ $group }}</span>
                                    <button type="button" wire:click="toggleAll({{ $allInGroup ? 'false' : 'true' }}, {{ $loop->index }})" class="text-xs font-medium text-brand-600 hover:underline">
                                        {{ $allInGroup ? 'Quitar todos' : 'Marcar todos' }}
                                    </button>
                                </legend>
                                <div class="space-y-2">
                                    @foreach ($perms as $perm => $label)
                                        <label class="flex items-start gap-2 text-sm">
                                            <input type="checkbox" value="{{ $perm }}" wire:model="permissions" class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                            <span>{{ $label }} <span class="block font-mono text-[11px] text-slate-400">{{ $perm }}</span></span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="card"><x-empty-state icon="key" title="Seleccioná un rol" /></div>
            @endif
        </div>
    </div>
</div>

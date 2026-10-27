<div>
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        @if ($editable)
            <div class="relative w-full max-w-md">
                <input wire:model.live.debounce.300ms="search" class="form-input" placeholder="Inscribir: buscar por nombre, documento o club" autocomplete="off" aria-label="Buscar participante">
                @if (mb_strlen(trim($search)) >= 2)
                    <ul class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg bg-white text-sm shadow-lg ring-1 ring-slate-200">
                        @foreach ($competitorResults as $competitor)
                            <li><button type="button" wire:click="pick('competidora', {{ $competitor->id }})" class="w-full px-3 py-2 text-left hover:bg-slate-50">
                                {{ $competitor->sortableName() }} <span class="text-xs text-slate-500">{{ $competitor->institution }}</span>
                            </button></li>
                        @endforeach
                        @foreach ($memberResults as $member)
                            <li><button type="button" wire:click="pick('socio', {{ $member->id }})" class="w-full px-3 py-2 text-left hover:bg-slate-50">
                                {{ $member->sortableName() }} <span class="text-xs text-brand-700">· socio/a del club</span>
                            </button></li>
                        @endforeach
                        <li class="border-t border-slate-100"><button type="button" wire:click="pick('nueva')" class="w-full px-3 py-2 text-left font-medium text-brand-700 hover:bg-slate-50">
                            <x-icon name="user-plus" class="inline size-4" /> Nueva participante «{{ $search }}»
                        </button></li>
                    </ul>
                @endif
            </div>
        @endif
        <select wire:model.live="filterLevel" class="form-input w-full sm:w-48" aria-label="Filtrar por nivel">
            <option value="">Todos los niveles</option>
            @foreach ($levels as $level)
                <option value="{{ $level->id }}">{{ $level->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th class="w-20">Dorsal</th><th>Participante</th><th>Club / institución</th><th>Nivel</th><th class="text-right">Puestos</th><th></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($entries as $entry)
                    <tr wire:key="entry-{{ $entry->id }}">
                        <td class="font-display text-lg font-bold text-slate-900 tabular-nums">{{ $entry->bib_number }}</td>
                        <td>
                            <span class="font-medium text-slate-900">{{ $entry->competitor->sortableName() }}</span>
                            @if ($entry->competitor->document_number)
                                <span class="block text-xs text-slate-500">DNI {{ $entry->competitor->document_number }}</span>
                            @endif
                        </td>
                        <td>{{ $entry->competitor->institution ?? '—' }}</td>
                        <td>{{ $entry->categoryLabel() }}</td>
                        <td class="text-right tabular-nums">{{ $entry->evaluations_count }}</td>
                        <td class="text-right whitespace-nowrap">
                            @if ($editable)
                                <button type="button" wire:click="edit({{ $entry->id }})" class="btn-ghost btn-sm" title="Editar"><x-icon name="pencil" class="size-4" /></button>
                                <button type="button" wire:click="withdraw({{ $entry->id }})" wire:confirm="¿Dar de baja a {{ $entry->competitor->fullName() }}? Se la quita de las colas." class="btn-ghost btn-sm text-red-600" title="Dar de baja"><x-icon name="trash" class="size-4" /></button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-empty-state icon="users" title="Sin participantes" description="Buscá arriba para inscribir: participantes cargadas antes, socias del club o nuevas." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-modal wire:model="showForm" :title="$editingEntryId ? 'Editar inscripción' : 'Inscribir participante'" max-width="max-w-xl">
        <form wire:submit="save" id="entry-form" class="grid gap-4 sm:grid-cols-2">
            <x-field label="Nombre" for="en-first" error="first_name" required>
                <input id="en-first" wire:model="first_name" class="form-input">
            </x-field>
            <x-field label="Apellido" for="en-last" error="last_name" required>
                <input id="en-last" wire:model="last_name" class="form-input">
            </x-field>
            <x-field label="DNI" for="en-doc" error="document_number" help="Opcional">
                <input id="en-doc" wire:model="document_number" class="form-input" inputmode="numeric">
            </x-field>
            <x-field label="Club / institución" for="en-inst" error="institution">
                <input id="en-inst" wire:model="institution" class="form-input">
            </x-field>
            <x-field label="Nivel" for="en-level" error="level_id" required>
                <select id="en-level" wire:model.live="level_id" class="form-input">
                    @foreach ($levels as $level)
                        <option value="{{ $level->id }}">{{ $level->name }}</option>
                    @endforeach
                </select>
            </x-field>
            @if ($groups->isNotEmpty())
                <x-field label="Grupo" for="en-group" error="group_id" required>
                    <select id="en-group" wire:model="group_id" class="form-input">
                        <option value="">Elegí…</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}">{{ $group->name }}</option>
                        @endforeach
                    </select>
                </x-field>
            @endif
            <x-field label="Dorsal" for="en-bib" error="bib" :help="$editingEntryId ? null : 'Vacío: el siguiente número libre.'">
                <input id="en-bib" wire:model="bib" class="form-input" maxlength="10">
            </x-field>
        </form>
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="open = false">Cancelar</button>
            <button type="submit" form="entry-form" class="btn-primary">{{ $editingEntryId ? 'Guardar' : 'Inscribir' }}</button>
        </x-slot:footer>
    </x-modal>
</div>

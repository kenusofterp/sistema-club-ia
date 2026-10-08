<div>
    <x-page-header title="Socios" subtitle="Padrón de socios del club">
        <x-slot:actions>
            @can('reportes.ver')
                <a href="{{ route('admin.export', 'socios') }}" class="btn-secondary"><x-icon name="download" class="size-4" /> Exportar</a>
            @endcan
            @can('socios.crear')
                <a href="{{ route('admin.members.create') }}" wire:navigate class="btn-primary"><x-icon name="user-plus" class="size-4" /> Nuevo socio</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <div class="flex flex-col gap-3 border-b border-slate-200 p-4 lg:flex-row lg:items-center">
            <div class="relative flex-1">
                <x-icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4 text-slate-400" />
                <input type="search" wire:model.live.debounce.400ms="search" placeholder="Buscar por nombre, documento, N° de socio o email…" class="form-input pl-9">
            </div>
            <select wire:model.live="status" class="form-input lg:w-52">
                <option value="">Todos los estados</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <select wire:model.live="category" class="form-input lg:w-44">
                <option value="">Todas las categorías</option>
                @foreach ($categories as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
            <label class="flex items-center gap-2 text-sm whitespace-nowrap text-slate-600">
                <input type="checkbox" wire:model.live="withDebt" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Con deuda vencida
            </label>
            @if ($search || $status || $category || $withDebt)
                <button type="button" wire:click="clearFilters" class="btn-ghost btn-sm">Limpiar</button>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Socio</th>
                        <th>N°</th>
                        <th class="hidden md:table-cell">Documento</th>
                        <th class="hidden lg:table-cell">Categoría</th>
                        <th>Estado</th>
                        <th class="hidden sm:table-cell">Cuotas</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($members as $member)
                        <tr wire:key="m-{{ $member->id }}">
                            <td>
                                <div class="flex items-center gap-3">
                                    @if ($member->photoUrl())
                                        <img src="{{ $member->photoUrl() }}" alt="" class="size-9 rounded-full object-cover">
                                    @else
                                        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700">{{ $member->initials() }}</span>
                                    @endif
                                    <div class="min-w-0">
                                        <a href="{{ route('admin.members.show', $member) }}" wire:navigate class="block truncate font-medium text-slate-900 hover:text-brand-700">{{ $member->sortableName() }}</a>
                                        <p class="truncate text-xs text-slate-500">{{ $member->email ?? $member->phone }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="tabular-nums">{{ $member->member_number ?? '—' }}</td>
                            <td class="hidden md:table-cell">{{ $member->document_type }} {{ $member->document_number }}</td>
                            <td class="hidden lg:table-cell">{{ $member->category->name }}</td>
                            <td><x-badge :status="$member->status" /></td>
                            <td class="hidden sm:table-cell">
                                @if ($member->overdue_count)
                                    <x-badge color="red">{{ $member->overdue_count }} vencida(s)</x-badge>
                                @else
                                    <x-badge color="green">Al día</x-badge>
                                @endif
                            </td>
                            <td class="text-right">
                                <a href="{{ route('admin.members.show', $member) }}" wire:navigate class="btn-ghost btn-sm">Ver <x-icon name="chevron-right" class="size-4" /></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state icon="users" title="No se encontraron socios" description="Probá con otros filtros o registrá un nuevo socio." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($members->hasPages())
            <div class="border-t border-slate-200 px-4 py-3">{{ $members->links() }}</div>
        @endif
    </div>
</div>

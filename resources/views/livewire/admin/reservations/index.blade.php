<div>
    <x-page-header title="Reservas" subtitle="Agenda diaria de instalaciones">
        <x-slot:actions>
            @can('reservas.gestionar')
                <button type="button" wire:click="openForm" class="btn-primary"><x-icon name="plus" class="size-4" /> Nueva reserva</button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card mb-6 flex flex-wrap items-center gap-3 p-4">
        <button type="button" wire:click="shiftDate(-1)" class="btn-secondary px-2.5" aria-label="Día anterior"><x-icon name="chevron-left" class="size-4" /></button>
        <input type="date" wire:model.live="date" class="form-input w-auto">
        <button type="button" wire:click="shiftDate(1)" class="btn-secondary px-2.5" aria-label="Día siguiente"><x-icon name="chevron-right" class="size-4" /></button>
        <button type="button" wire:click="$set('date', '{{ today()->toDateString() }}')" class="btn-ghost btn-sm">Hoy</button>
        <p class="ml-auto font-medium text-slate-700">{{ ucfirst($day->translatedFormat('l j \d\e F')) }}</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-2 2xl:grid-cols-3">
        @forelse ($facilities as $facility)
            @php($booked = $reservations->get($facility->id, collect()))
            <div class="card" wire:key="agenda-{{ $facility->id }}">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
                    <h2 class="font-semibold text-slate-900">{{ $facility->name }}</h2>
                    <span class="text-xs text-slate-500">{{ $booked->count() }} reservas</span>
                </div>
                <ul class="divide-y divide-slate-100">
                    @foreach ($facility->slots() as $slot)
                        @php($res = $booked->first(fn ($r) => substr($r->start_time, 0, 5) <= $slot['start'] && substr($r->end_time, 0, 5) > $slot['start']))
                        <li class="flex items-center gap-3 px-5 py-2 text-sm">
                            <span class="w-24 shrink-0 text-slate-500 tabular-nums">{{ $slot['start'] }} – {{ $slot['end'] }}</span>
                            @if ($res)
                                <div class="flex flex-1 items-center justify-between gap-2 rounded-lg bg-brand-50 px-3 py-1.5 ring-1 ring-brand-200">
                                    <span class="truncate font-medium text-brand-800">{{ $res->member->fullName() }}</span>
                                    @if (substr($res->start_time, 0, 5) === $slot['start'])
                                        @can('reservas.gestionar')
                                            <button type="button" wire:click="cancel({{ $res->id }})" wire:confirm="¿Cancelar la reserva de {{ $res->member->fullName() }}?" class="text-xs font-medium text-red-600 hover:underline">Cancelar</button>
                                        @endcan
                                    @endif
                                </div>
                            @else
                                @can('reservas.gestionar')
                                    <button type="button" wire:click="openForm({{ $facility->id }}, '{{ $slot['start'] }}')" class="flex-1 rounded-lg border border-dashed border-slate-200 px-3 py-1.5 text-left text-slate-400 hover:border-brand-300 hover:text-brand-700">Libre</button>
                                @else
                                    <span class="flex-1 text-slate-400">Libre</span>
                                @endcan
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <div class="card lg:col-span-2"><x-empty-state icon="building" title="No hay instalaciones reservables" description="Configurá instalaciones con reservas habilitadas." /></div>
        @endforelse
    </div>

    <x-modal wire:model="showForm" title="Nueva reserva" max-width="max-w-lg">
        <div class="grid gap-4">
            <x-field label="Socio" for="memberSearch" error="memberId" required>
                <div class="relative">
                    <input id="memberSearch" wire:model.live.debounce.300ms="memberSearch" class="form-input" placeholder="Buscar socio activo…" autocomplete="off">
                    @if ($memberResults->isNotEmpty())
                        <ul class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg bg-white shadow-lg ring-1 ring-slate-200">
                            @foreach ($memberResults as $result)
                                <li><button type="button" wire:click="selectMember({{ $result->id }})" class="w-full px-3 py-2 text-left text-sm hover:bg-slate-50">{{ $result->sortableName() }} <span class="text-slate-400">· N° {{ $result->member_number }}</span></button></li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </x-field>
            <x-field label="Instalación" for="facilityId" error="facilityId" required>
                <select id="facilityId" wire:model.live="facilityId" class="form-input">
                    <option value="">Seleccionar…</option>
                    @foreach ($facilities as $facility)
                        <option value="{{ $facility->id }}">{{ $facility->name }}</option>
                    @endforeach
                </select>
            </x-field>
            <div class="grid grid-cols-3 gap-3">
                <x-field label="Fecha" for="date-f" error="date">
                    <input id="date-f" type="date" wire:model.live="date" class="form-input">
                </x-field>
                <x-field label="Horario" for="startTime" error="startTime" required>
                    <select id="startTime" wire:model="startTime" class="form-input">
                        <option value="">—</option>
                        @foreach ($formSlots as $slot)
                            <option value="{{ $slot['start'] }}">{{ $slot['start'] }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Turnos" for="slots" error="slotCount">
                    <input id="slots" type="number" min="1" max="{{ $formFacility?->max_slots_per_booking ?? 1 }}" wire:model="slotCount" class="form-input">
                </x-field>
            </div>
            @if ($formFacility && (float) $formFacility->hourly_rate > 0)
                <p class="text-xs text-slate-500">Tarifa {{ money($formFacility->hourly_rate) }}/hora. Se generará el cargo en la cuenta del socio.</p>
            @endif
            <x-field label="Observaciones" for="notes" error="notes">
                <input id="notes" wire:model="notes" class="form-input">
            </x-field>
        </div>
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="open = false">Cancelar</button>
            <button type="button" wire:click="book" class="btn-primary">Confirmar reserva</button>
        </x-slot:footer>
    </x-modal>
</div>

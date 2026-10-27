<div @if ($competition->isInProgress()) wire:poll.5s.visible @endif>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-500">Arrastrá para cambiar el orden de pasada. Cuando los 3 jueces confirman, el puesto pasa solo a la siguiente.</p>
        @if ($editable && $unassignedCount > 0)
            <button type="button" wire:click="assignPending" wire:confirm="¿Asignar automáticamente a las participantes que no tienen puesto, repartiéndolas entre los puestos de cada ejercicio?" class="btn-secondary">
                <x-icon name="sparkles" class="size-4" /> Asignar automáticamente
            </button>
        @endif
    </div>

    <div class="grid gap-4 lg:grid-cols-2 2xl:grid-cols-3">
        @forelse ($stations as $station)
            @php
                $published = $station->evaluations->where('status', \App\Enums\EvaluationStatus::Published);
                $open = $station->evaluations->where('status', '!=', \App\Enums\EvaluationStatus::Published);
                $current = $station->evaluations->firstWhere('status', \App\Enums\EvaluationStatus::Pending);
                $siblings = $stations->where('competition_exercise_id', $station->competition_exercise_id)->where('id', '!=', $station->id);
            @endphp
            <div class="card flex flex-col" wire:key="queue-{{ $station->id }}">
                <div class="border-b border-slate-200 p-4">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="font-semibold text-slate-900">{{ $station->name }}</h3>
                        <span class="text-xs text-slate-500">{{ $published->count() }}/{{ $station->evaluations->count() }} publicadas</span>
                    </div>
                    <p class="text-xs text-slate-500">{{ $station->competitionExercise->name() }}@if ($station->level) · {{ $station->level->name }}@endif @if ($station->group) · {{ $station->group->name }}@endif</p>
                </div>

                <ol class="flex-1 divide-y divide-slate-100 text-sm" @if ($editable) wire:sort="sortQueue" wire:sort:group-id="{{ $station->id }}" @endif>
                    @foreach ($open as $evaluation)
                        @php $isCurrent = $current?->id === $evaluation->id; @endphp
                        <li @if ($editable && $evaluation->status === \App\Enums\EvaluationStatus::Pending && $evaluation->sheets->isEmpty()) wire:sort:item="{{ $evaluation->id }}" @endif
                            wire:key="ev-{{ $evaluation->id }}"
                            @class(['flex items-center gap-3 px-4 py-2.5', 'bg-brand-50' => $isCurrent, 'opacity-60' => $evaluation->status === \App\Enums\EvaluationStatus::Absent, 'cursor-grab' => $editable])>
                            <span class="w-8 font-display font-bold text-slate-900 tabular-nums">{{ $evaluation->entry->bib_number }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium text-slate-800">{{ $evaluation->entry->competitor->sortableName() }}</span>
                                <span class="block truncate text-xs text-slate-500">
                                    @if ($isCurrent)
                                        <span class="font-semibold text-brand-700">Evaluando · {{ $evaluation->sheets->count() }}/3 jueces</span>
                                    @elseif ($evaluation->status === \App\Enums\EvaluationStatus::Absent)
                                        No se presentó
                                    @else
                                        {{ $evaluation->entry->competitor->institution }}
                                    @endif
                                </span>
                            </span>
                            @if ($editable)
                                <div class="flex items-center" wire:sort:ignore>
                                    @if ($evaluation->status === \App\Enums\EvaluationStatus::Absent)
                                        <button type="button" wire:click="restore({{ $evaluation->id }})" class="btn-ghost btn-sm" title="Volver a la cola"><x-icon name="refresh" class="size-4" /></button>
                                    @elseif ($evaluation->sheets->isEmpty())
                                        <div x-data="{ menu: false }" class="relative">
                                            <button type="button" x-on:click="menu = !menu" class="btn-ghost btn-sm" title="Acciones"><x-icon name="chevron-down" class="size-4" /></button>
                                            <div x-show="menu" x-cloak x-on:click.outside="menu = false" class="absolute right-0 z-20 mt-1 w-52 overflow-hidden rounded-lg bg-white text-left shadow-lg ring-1 ring-slate-200">
                                                <button type="button" wire:click="toEnd({{ $evaluation->id }})" class="block w-full px-3 py-2 text-left hover:bg-slate-50">Mandar al final</button>
                                                <button type="button" wire:click="absent({{ $evaluation->id }})" wire:confirm="¿Marcar a {{ $evaluation->entry->competitor->fullName() }} como ausente? No rankea en este ejercicio." class="block w-full px-3 py-2 text-left hover:bg-slate-50">No se presentó</button>
                                                @foreach ($siblings as $sibling)
                                                    <button type="button" wire:click="move({{ $evaluation->id }}, {{ $sibling->id }})" class="block w-full px-3 py-2 text-left hover:bg-slate-50">Pasar a {{ $sibling->name }}</button>
                                                @endforeach
                                                <button type="button" wire:click="unassign({{ $evaluation->id }})" class="block w-full px-3 py-2 text-left text-red-600 hover:bg-red-50">Quitar de la cola</button>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ol>

                @if ($published->isNotEmpty())
                    <details class="border-t border-slate-100 text-sm">
                        <summary class="cursor-pointer px-4 py-2 text-xs font-medium text-slate-500">Ya pasaron ({{ $published->count() }})</summary>
                        <ul class="divide-y divide-slate-100">
                            @foreach ($published->sortByDesc('published_at') as $evaluation)
                                <li class="flex items-center gap-3 px-4 py-2" wire:key="pub-{{ $evaluation->id }}">
                                    <span class="w-8 font-display font-bold text-slate-500 tabular-nums">{{ $evaluation->entry->bib_number }}</span>
                                    <span class="flex-1 truncate text-slate-700">{{ $evaluation->entry->competitor->sortableName() }}</span>
                                    <span class="font-display font-bold text-slate-900 tabular-nums">{{ number_format((float) $evaluation->score, 3, ',', '.') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif

                @if ($editable && $assignable[$station->id]->isNotEmpty())
                    <form wire:submit="assign({{ $station->id }})" class="flex gap-2 border-t border-slate-100 p-3">
                        <select wire:model="toAssign.{{ $station->id }}" class="form-input py-1.5 text-sm" aria-label="Participante para {{ $station->name }}">
                            <option value="">Agregar participante…</option>
                            @foreach ($assignable[$station->id] as $entry)
                                <option value="{{ $entry->id }}">{{ $entry->bib_number }} · {{ $entry->competitor->sortableName() }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn-secondary btn-sm"><x-icon name="plus" class="size-4" /></button>
                    </form>
                @endif
            </div>
        @empty
            <div class="card lg:col-span-2 2xl:col-span-3">
                <x-empty-state icon="list" title="Sin puestos" description="Creá los puestos en la pestaña «Puestos y jueces»." />
            </div>
        @endforelse
    </div>
</div>

<div>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-500">Cada puesto evalúa un ejercicio con un tribunal de 3 jueces. Para agilizar, puede haber varios puestos del mismo ejercicio.</p>
        @if ($editable)
            <button type="button" wire:click="create" class="btn-primary"><x-icon name="plus" class="size-4" /> Nuevo puesto</button>
        @endif
    </div>

    @if ($judgeCandidates->isEmpty())
        <div class="mb-4 rounded-lg bg-amber-50 p-4 text-sm text-amber-800">
            No hay usuarios con el rol <strong>Juez</strong> en esta entidad. Crealos en <a href="{{ route('admin.users') }}" wire:navigate class="font-medium underline">Usuarios</a> con el rol «Juez» (o «Juez principal»).
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        @forelse ($stations as $station)
            <div class="card p-5" wire:key="station-{{ $station->id }}">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="font-semibold text-slate-900">{{ $station->name }}</h3>
                        <p class="text-sm text-slate-500">
                            {{ $station->competitionExercise->name() }}
                            @if ($station->level) · {{ $station->level->name }}@endif
                            @if ($station->group) · {{ $station->group->name }}@endif
                            · {{ $station->evaluations_count }} en cola
                        </p>
                    </div>
                    <div class="flex items-center gap-1">
                        @if ($station->judges_count === \App\Models\CompetitionStation::JUDGES)
                            <x-badge color="green">Tribunal completo</x-badge>
                        @else
                            <x-badge color="yellow">Faltan {{ \App\Models\CompetitionStation::JUDGES - $station->judges_count }} jueces</x-badge>
                        @endif
                        @if ($editable)
                            <button type="button" wire:click="edit({{ $station->id }})" class="btn-ghost btn-sm" title="Editar"><x-icon name="pencil" class="size-4" /></button>
                            <button type="button" wire:click="delete({{ $station->id }})" wire:confirm="¿Eliminar el puesto «{{ $station->name }}»?" class="btn-ghost btn-sm text-red-600" title="Eliminar"><x-icon name="trash" class="size-4" /></button>
                        @endif
                    </div>
                </div>

                <form wire:submit="savePanel({{ $station->id }})" class="mt-4 space-y-2">
                    @foreach (range(1, \App\Models\CompetitionStation::JUDGES) as $seat)
                        <div class="flex items-center gap-3">
                            <span class="w-16 text-xs font-semibold tracking-wide text-slate-500 uppercase">Juez {{ $seat }}</span>
                            <select wire:model="panels.{{ $station->id }}.{{ $seat }}" class="form-input py-1.5 text-sm" @disabled(! $editable) aria-label="Juez {{ $seat }} de {{ $station->name }}">
                                <option value="">— Sin asignar —</option>
                                @foreach ($judgeCandidates as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                    @if ($editable)
                        <div class="flex justify-end pt-1">
                            <button type="submit" class="btn-secondary btn-sm">Guardar tribunal</button>
                        </div>
                    @endif
                </form>
            </div>
        @empty
            <div class="card lg:col-span-2">
                <x-empty-state icon="clipboard" title="Sin puestos" description="Creá un puesto por cada mesa de jueces (ej.: Salto 1, Salto 2, Suelo 1)." />
            </div>
        @endforelse
    </div>

    <x-modal wire:model="showForm" :title="$editingId ? 'Editar puesto' : 'Nuevo puesto'" max-width="max-w-lg">
        <form wire:submit="save" id="station-form" class="space-y-4">
            <x-field label="Nombre" for="st-name" error="name" required>
                <input id="st-name" wire:model="name" class="form-input" placeholder="Ej.: Salto 1">
            </x-field>
            <x-field label="Ejercicio" for="st-exercise" error="competition_exercise_id" required>
                <select id="st-exercise" wire:model="competition_exercise_id" class="form-input">
                    <option value="">Elegí…</option>
                    @foreach ($exercises as $exercise)
                        <option value="{{ $exercise->id }}">{{ $exercise->name() }}</option>
                    @endforeach
                </select>
            </x-field>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="Solo para el nivel" for="st-level" error="competition_level_id" help="Opcional">
                    <select id="st-level" wire:model.live="competition_level_id" class="form-input">
                        <option value="">Todos</option>
                        @foreach ($levels as $level)
                            <option value="{{ $level->id }}">{{ $level->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                @if ($groups->isNotEmpty())
                    <x-field label="Grupo" for="st-group" error="competition_group_id">
                        <select id="st-group" wire:model="competition_group_id" class="form-input">
                            <option value="">Todos</option>
                            @foreach ($groups as $group)
                                <option value="{{ $group->id }}">{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </x-field>
                @endif
            </div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="open = false">Cancelar</button>
            <button type="submit" form="station-form" class="btn-primary">Guardar</button>
        </x-slot:footer>
    </x-modal>
</div>

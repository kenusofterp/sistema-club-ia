<div>
    <x-page-header :title="$competition ? 'Editar competencia' : 'Nueva competencia'">
        <x-slot:breadcrumb><a href="{{ route('admin.competitions.index') }}" wire:navigate class="hover:text-brand-700">Competencias</a> /</x-slot:breadcrumb>
    </x-page-header>

    <form wire:submit="save" class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <div class="card grid gap-4 p-5 sm:grid-cols-2">
                <x-field label="Nombre" for="c-name" error="name" required class="sm:col-span-2">
                    <input id="c-name" wire:model="name" class="form-input" placeholder="Ej.: Torneo Apertura 2026">
                </x-field>
                <x-field label="Fecha" for="c-date" error="date" required>
                    <x-date-input id="c-date" wire:model="date" />
                </x-field>
                <x-field label="Lugar" for="c-location" error="location">
                    <input id="c-location" wire:model="location" class="form-input">
                </x-field>
                @if ($tournaments->isNotEmpty())
                    <x-field label="Vincular a un torneo (opcional)" for="c-tournament" error="tournament_id" help="Si el torneo también se cobra desde «Torneos»." class="sm:col-span-2">
                        <select id="c-tournament" wire:model="tournament_id" class="form-input">
                            <option value="">— Sin vincular —</option>
                            @foreach ($tournaments as $tournament)
                                <option value="{{ $tournament->id }}">{{ $tournament->name }}</option>
                            @endforeach
                        </select>
                    </x-field>
                @endif
            </div>

            <div class="card p-5">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <div>
                        <h2 class="font-semibold text-slate-900">Niveles y grupos</h2>
                        <p class="text-sm text-slate-500">Los rankings son por nivel o, si el nivel tiene grupos, por grupo.</p>
                    </div>
                    <button type="button" wire:click="addLevel" class="btn-secondary btn-sm"><x-icon name="plus" class="size-4" /> Nivel</button>
                </div>
                @error('levels') <p class="form-error">{{ $message }}</p> @enderror
                <div class="space-y-3">
                    @foreach ($levels as $l => $level)
                        <div class="rounded-xl border border-slate-200 p-3" wire:key="level-{{ $l }}">
                            <div class="flex items-start gap-2">
                                <x-field error="levels.{{ $l }}.name" class="flex-1">
                                    <input wire:model="levels.{{ $l }}.name" class="form-input font-medium" aria-label="Nombre del nivel">
                                </x-field>
                                <button type="button" wire:click="addGroup({{ $l }})" class="btn-ghost btn-sm whitespace-nowrap"><x-icon name="plus" class="size-4" /> Grupo</button>
                                <button type="button" wire:click="removeLevel({{ $l }})" class="btn-ghost btn-sm text-red-600" title="Quitar nivel" @disabled(count($levels) === 1)><x-icon name="trash" class="size-4" /></button>
                            </div>
                            @if ($level['groups'])
                                <div class="mt-2 flex flex-wrap gap-2 pl-4">
                                    @foreach ($level['groups'] as $g => $group)
                                        <div class="flex items-center gap-1" wire:key="group-{{ $l }}-{{ $g }}">
                                            <input wire:model="levels.{{ $l }}.groups.{{ $g }}.name" class="form-input w-32 py-1 text-sm" aria-label="Nombre del grupo">
                                            <button type="button" wire:click="removeGroup({{ $l }}, {{ $g }})" class="btn-ghost btn-sm text-red-600" title="Quitar grupo"><x-icon name="x" class="size-4" /></button>
                                        </div>
                                    @endforeach
                                </div>
                                @error('levels.'.$l.'.groups.*.name') <p class="form-error pl-4">{{ $message }}</p> @enderror
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="card p-5">
                <h2 class="mb-1 font-semibold text-slate-900">Ejercicios</h2>
                <p class="mb-3 text-sm text-slate-500">Al iniciar la competencia se congela la plantilla de criterios de cada uno.</p>
                @error('exerciseIds') <p class="form-error mb-2">{{ $message }}</p> @enderror
                <div class="space-y-2">
                    @forelse ($exercises as $exercise)
                        <label class="flex items-center gap-3 rounded-lg border border-slate-200 p-3 text-sm hover:bg-slate-50" wire:key="ex-{{ $exercise->id }}">
                            <input type="checkbox" value="{{ $exercise->id }}" wire:model="exerciseIds" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                            <span class="flex-1 font-medium text-slate-800">{{ $exercise->name }}</span>
                            <span class="text-xs text-slate-500">{{ $exercise->criteria_count }} criterios</span>
                        </label>
                    @empty
                        <p class="text-sm text-slate-500">No hay ejercicios. <a href="{{ route('admin.exercises') }}" wire:navigate class="font-medium text-brand-700 hover:underline">Crealos acá</a>.</p>
                    @endforelse
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <a href="{{ $competition ? route('admin.competitions.show', $competition) : route('admin.competitions.index') }}" wire:navigate class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-primary">Guardar</button>
            </div>
        </div>
    </form>
</div>

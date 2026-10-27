<div>
    <x-page-header title="Ejercicios y criterios" subtitle="Rutinas que se evalúan en las competencias y qué mira cada juez. Se reutilizan entre competencias.">
        <x-slot:actions>
            <button type="button" wire:click="create" class="btn-primary"><x-icon name="plus" class="size-4" /> Nuevo ejercicio</button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($exercises as $exercise)
            <div class="card p-5" wire:key="exercise-{{ $exercise->id }}">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-slate-900">{{ $exercise->name }}</h2>
                        @if ($exercise->description)
                            <p class="text-sm text-slate-500">{{ $exercise->description }}</p>
                        @endif
                    </div>
                    @unless ($exercise->is_active)
                        <x-badge color="gray">Inactivo</x-badge>
                    @endunless
                </div>
                <ul class="mt-4 space-y-1.5 text-sm">
                    @foreach ($exercise->criteria as $criterion)
                        <li class="flex items-center justify-between gap-2">
                            <span class="text-slate-700">{{ $criterion->name }}</span>
                            <span class="flex items-center gap-2 text-xs text-slate-500">
                                @if ($criterion->type->value !== 'si_no')
                                    {{ rtrim(rtrim($criterion->min, '0'), '.') }}–{{ rtrim(rtrim($criterion->max, '0'), '.') }}
                                @endif
                                @if ((float) $criterion->weight !== 1.0) · ×{{ rtrim(rtrim($criterion->weight, '0'), '.') }}@endif
                                <x-badge :status="$criterion->type" />
                            </span>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-4 flex justify-end gap-1 border-t border-slate-100 pt-3">
                    <button type="button" wire:click="edit({{ $exercise->id }})" class="btn-ghost btn-sm"><x-icon name="pencil" class="size-4" /> Editar</button>
                    <button type="button" wire:click="delete({{ $exercise->id }})" wire:confirm="¿Eliminar el ejercicio «{{ $exercise->name }}»?" class="btn-ghost btn-sm text-red-600"><x-icon name="trash" class="size-4" /></button>
                </div>
            </div>
        @empty
            <div class="card md:col-span-2 xl:col-span-3">
                <x-empty-state icon="list" title="Todavía no hay ejercicios" description="Creá Salto, Suelo, Viga… con los criterios que evalúa cada juez." />
            </div>
        @endforelse
    </div>

    <x-modal wire:model="showForm" :title="$editingId ? 'Editar ejercicio' : 'Nuevo ejercicio'" max-width="max-w-4xl">
        <form wire:submit="save" id="exercise-form" class="space-y-5">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="Nombre" for="ex-name" error="name" required>
                    <input id="ex-name" wire:model="name" class="form-input" placeholder="Ej.: Salto">
                </x-field>
                <x-field label="Descripción" for="ex-description" error="description">
                    <input id="ex-description" wire:model="description" class="form-input">
                </x-field>
            </div>

            <div>
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-slate-900">Criterios de evaluación</h3>
                    <div class="flex flex-wrap gap-1">
                        <button type="button" wire:click="addCriterion('puntaje')" class="btn-secondary btn-sm"><x-icon name="plus" class="size-4" /> Puntaje</button>
                        <button type="button" wire:click="addCriterion('deduccion')" class="btn-secondary btn-sm"><x-icon name="plus" class="size-4" /> Deducción</button>
                        <button type="button" wire:click="addCriterion('si_no')" class="btn-secondary btn-sm"><x-icon name="plus" class="size-4" /> Sí / No</button>
                    </div>
                </div>
                @error('criteria') <p class="form-error">{{ $message }}</p> @enderror

                <div class="space-y-3">
                    @foreach ($criteria as $i => $criterion)
                        <div class="rounded-xl border border-slate-200 p-3" wire:key="criterion-{{ $i }}">
                            <div class="grid gap-3 sm:grid-cols-12">
                                <x-field label="Criterio" error="criteria.{{ $i }}.name" class="sm:col-span-4">
                                    <input wire:model="criteria.{{ $i }}.name" class="form-input" placeholder="Ej.: Ejecución">
                                </x-field>
                                <x-field label="Tipo" error="criteria.{{ $i }}.type" class="sm:col-span-2">
                                    <select wire:model.live="criteria.{{ $i }}.type" class="form-input">
                                        @foreach ($types as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </x-field>
                                @if ($criterion['type'] !== 'si_no')
                                    <x-field label="Mínimo" error="criteria.{{ $i }}.min" class="sm:col-span-1">
                                        <input type="number" step="any" wire:model="criteria.{{ $i }}.min" class="form-input">
                                    </x-field>
                                    <x-field label="Máximo" error="criteria.{{ $i }}.max" class="sm:col-span-1">
                                        <input type="number" step="any" wire:model="criteria.{{ $i }}.max" class="form-input">
                                    </x-field>
                                    <x-field label="Decimales" error="criteria.{{ $i }}.decimals" class="sm:col-span-1">
                                        <select wire:model="criteria.{{ $i }}.decimals" class="form-input">
                                            @foreach ([0, 1, 2, 3] as $d)
                                                <option value="{{ $d }}">{{ $d }}</option>
                                            @endforeach
                                        </select>
                                    </x-field>
                                @else
                                    <p class="self-end pb-2 text-xs text-slate-500 sm:col-span-3">Si el juez marca «Sí», suma el peso.</p>
                                @endif
                                <x-field label="Peso" error="criteria.{{ $i }}.weight" class="sm:col-span-1">
                                    <input type="number" step="any" min="0" wire:model="criteria.{{ $i }}.weight" class="form-input">
                                </x-field>
                                <div class="flex items-end justify-end gap-1 sm:col-span-2">
                                    <button type="button" wire:click="moveCriterion({{ $i }}, -1)" class="btn-ghost btn-sm" @disabled($loop->first) title="Subir"><x-icon name="arrow-up" class="size-4" /></button>
                                    <button type="button" wire:click="moveCriterion({{ $i }}, 1)" class="btn-ghost btn-sm" @disabled($loop->last) title="Bajar"><x-icon name="chevron-down" class="size-4" /></button>
                                    <button type="button" wire:click="removeCriterion({{ $i }})" class="btn-ghost btn-sm text-red-600" title="Quitar"><x-icon name="trash" class="size-4" /></button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <p class="form-help mt-2">Total de cada juez = puntajes × peso − deducciones × peso + peso de cada «Sí». El puntaje del ejercicio es el promedio de los 3 jueces.</p>
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" wire:model="is_active" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Activo (se puede elegir en competencias nuevas)
            </label>
        </form>
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="open = false">Cancelar</button>
            <button type="submit" form="exercise-form" class="btn-primary">Guardar</button>
        </x-slot:footer>
    </x-modal>
</div>

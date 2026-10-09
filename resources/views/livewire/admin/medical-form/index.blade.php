<div>
    <x-page-header title="Ficha médica" subtitle="Armá las preguntas que se completan en la ficha médica de cada socio">
        <x-slot:actions>
            <button type="button" wire:click="create" class="btn-primary"><x-icon name="plus" class="size-4" /> Agregar campo</button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-5">
        <div class="xl:col-span-3">
            <div class="card overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr><th class="w-16">Orden</th><th>Pregunta</th><th>Tipo</th><th>Estado</th><th></th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($fields as $field)
                            <tr wire:key="mff-{{ $field->id }}" @class(['opacity-60' => ! $field->is_active])>
                                <td class="whitespace-nowrap">
                                    <button type="button" wire:click="move({{ $field->id }}, -1)" class="btn-ghost btn-sm" @disabled($loop->first) aria-label="Subir"><x-icon name="chevron-down" class="size-4 rotate-180" /></button>
                                    <button type="button" wire:click="move({{ $field->id }}, 1)" class="btn-ghost btn-sm" @disabled($loop->last) aria-label="Bajar"><x-icon name="chevron-down" class="size-4" /></button>
                                </td>
                                <td>
                                    <p class="font-medium text-slate-900">{{ $field->label }}@if ($field->is_required)<span class="text-red-500"> *</span>@endif</p>
                                    <p class="text-xs text-slate-500">
                                        {{ $field->section ?? 'Sin sección' }}
                                        @if ($field->options) · {{ implode(', ', $field->options) }} @endif
                                    </p>
                                </td>
                                <td class="whitespace-nowrap text-sm">{{ $field->type->label() }}</td>
                                <td><x-badge :color="$field->is_active ? 'green' : 'gray'">{{ $field->is_active ? 'Se pide' : 'Desactivado' }}</x-badge></td>
                                <td class="text-right whitespace-nowrap">
                                    <button type="button" wire:click="edit({{ $field->id }})" class="btn-ghost btn-sm" aria-label="Editar"><x-icon name="pencil" class="size-4" /></button>
                                    <button type="button" wire:click="toggle({{ $field->id }})" class="btn-ghost btn-sm" title="{{ $field->is_active ? 'Desactivar' : 'Activar' }}" aria-label="{{ $field->is_active ? 'Desactivar' : 'Activar' }}"><x-icon :name="$field->is_active ? 'ban' : 'check'" class="size-4" /></button>
                                    <button type="button" wire:click="delete({{ $field->id }})" wire:confirm="¿Eliminar el campo de la ficha?" class="btn-ghost btn-sm text-red-600" aria-label="Eliminar"><x-icon name="trash" class="size-4" /></button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-empty-state icon="heart" title="La ficha todavía no tiene campos" description="Agregá preguntas como grupo sanguíneo, alergias, medicación o apto físico." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="form-help mt-3">Si un campo ya tiene respuestas cargadas no se puede eliminar ni cambiar de tipo: desactivalo y deja de pedirse, pero lo cargado se sigue viendo en la ficha del socio.</p>
        </div>

        <div class="xl:col-span-2">
            <div class="card p-6">
                <h2 class="mb-1 font-semibold text-slate-900">Vista previa</h2>
                <p class="mb-5 text-sm text-slate-500">Así se ve la ficha al dar de alta o editar un socio.</p>
                @if ($activeFields->isEmpty())
                    <p class="text-sm text-slate-500">Sin campos para mostrar.</p>
                @else
                    <x-medical-fields :fields="$activeFields" model="preview" />
                @endif
            </div>
        </div>
    </div>

    <x-modal wire:model="showForm" :title="$editingId ? 'Editar campo' : 'Nuevo campo'">
        <form wire:submit="save" id="medical-field-form" class="grid gap-4 sm:grid-cols-2">
            <x-field label="Pregunta" for="label" error="label" required class="sm:col-span-2">
                <input id="label" wire:model="label" class="form-input" placeholder="Ej.: ¿Tiene alergias?">
            </x-field>
            <x-field label="Tipo de respuesta" for="type" error="type" required>
                <select id="type" wire:model.live="type" class="form-input">
                    @foreach (\App\Enums\MedicalFieldType::options() as $value => $typeLabel)
                        <option value="{{ $value }}">{{ $typeLabel }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field label="Sección" for="section" error="section" help="Agrupa campos bajo un título (opcional).">
                <input id="section" wire:model="section" class="form-input" list="medical-sections" placeholder="Ej.: Antecedentes">
                <datalist id="medical-sections">
                    @foreach ($sections as $s)
                        <option value="{{ $s }}"></option>
                    @endforeach
                </datalist>
            </x-field>
            @if (\App\Enums\MedicalFieldType::from($type)->hasOptions())
                <x-field label="Opciones" for="optionsText" error="optionsText" required help="Una por renglón." class="sm:col-span-2">
                    <textarea id="optionsText" wire:model="optionsText" rows="4" class="form-input" placeholder="0+&#10;0-&#10;A+&#10;A-"></textarea>
                </x-field>
            @endif
            <x-field label="Ayuda" for="help" error="help" help="Texto chico debajo del campo (opcional)." class="sm:col-span-2">
                <input id="help" wire:model="help" class="form-input">
            </x-field>
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" wire:model="is_required" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Obligatorio
            </label>
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" wire:model="is_active" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Se pide en la ficha
            </label>
        </form>
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="open = false">Cancelar</button>
            <button type="submit" form="medical-field-form" class="btn-primary">Guardar</button>
        </x-slot:footer>
    </x-modal>
</div>

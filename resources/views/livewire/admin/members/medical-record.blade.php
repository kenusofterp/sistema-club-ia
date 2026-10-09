<div class="card">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-3">
        <div>
            <h3 class="font-semibold text-slate-900">Ficha médica</h3>
            @if ($record)
                <p class="text-xs text-slate-500">Actualizada el {{ $record->updated_at->format('d/m/Y H:i') }}@if ($record->editor) por {{ $record->editor->name }}@endif</p>
            @endif
        </div>
        <div class="flex items-center gap-2">
            @if ($fields->isNotEmpty())
                @if (! $record)
                    <x-badge color="yellow">Sin completar</x-badge>
                @elseif ($missing->isNotEmpty())
                    <x-badge color="yellow">Incompleta</x-badge>
                @else
                    <x-badge color="green">Completa</x-badge>
                @endif
            @endif
            @if (! $editing && $fields->isNotEmpty())
                @can('fichas_medicas.editar')
                    <button type="button" wire:click="edit" class="btn-secondary btn-sm"><x-icon name="pencil" class="size-4" /> {{ $record ? 'Modificar' : 'Completar' }}</button>
                @endcan
            @endif
        </div>
    </div>

    <div class="p-5">
        @if ($fields->isEmpty() && $retired->isEmpty())
            <x-empty-state icon="heart" title="La ficha médica no tiene campos" description="Armala desde Socios › Ficha médica." />
        @elseif ($editing)
            <form wire:submit="save" class="space-y-6">
                <x-medical-fields :fields="$fields" model="medical" />
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="cancel" class="btn-secondary">Cancelar</button>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled"><x-icon name="check" class="size-4" /> Guardar ficha</button>
                </div>
            </form>
        @else
            @if ($missing->isNotEmpty())
                <p class="mb-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">Faltan datos obligatorios: {{ $missing->pluck('label')->implode(', ') }}.</p>
            @endif
            @foreach ($fields->groupBy(fn ($field) => (string) $field->section) as $section => $sectionFields)
                @if ($section !== '')
                    <h4 class="mt-4 mb-2 text-sm font-semibold tracking-wide text-slate-500 uppercase first:mt-0">{{ $section }}</h4>
                @endif
                <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    @foreach ($sectionFields as $field)
                        <div wire:key="mr-{{ $field->id }}">
                            <dt class="text-xs text-slate-500">{{ $field->label }}</dt>
                            <dd class="font-medium whitespace-pre-line text-slate-800">{{ $field->display($record?->answer($field)) ?? '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endforeach
            @if ($retired->isNotEmpty())
                <h4 class="mt-6 mb-2 text-sm font-semibold tracking-wide text-slate-400 uppercase">Datos de campos que ya no se piden</h4>
                <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    @foreach ($retired as $field)
                        <div wire:key="mr-old-{{ $field->id }}">
                            <dt class="text-xs text-slate-500">{{ $field->label }}</dt>
                            <dd class="font-medium whitespace-pre-line text-slate-600">{{ $field->display($record?->answer($field)) ?? '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        @endif
    </div>
</div>

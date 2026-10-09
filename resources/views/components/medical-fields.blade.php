{{--
    Campos de la ficha médica: <x-medical-fields :fields="$fields" model="medical" />
    Los valores se enlazan a la propiedad Livewire "{model}.{id de campo}".
--}}
@props(['fields', 'model' => 'medical'])
@php($sections = $fields->groupBy(fn ($field) => (string) $field->section))
<div class="space-y-6">
    @foreach ($sections as $section => $sectionFields)
        <div>
            @if ($section !== '')
                <h3 class="mb-3 text-sm font-semibold tracking-wide text-slate-500 uppercase">{{ $section }}</h3>
            @endif
            <div class="grid gap-5 md:grid-cols-2">
                @foreach ($sectionFields as $field)
                    @php($key = "{$model}.{$field->id}")
                    @php($id = "{$model}-{$field->id}")
                    <x-field :label="$field->label" :for="$id" :error="$key" :help="$field->help" :required="$field->is_required"
                             :class="in_array($field->type, [\App\Enums\MedicalFieldType::Textarea, \App\Enums\MedicalFieldType::Checkboxes], true) ? 'md:col-span-2' : ''"
                             wire:key="mf-{{ $model }}-{{ $field->id }}">
                        @switch($field->type)
                            @case(\App\Enums\MedicalFieldType::Textarea)
                                <textarea id="{{ $id }}" wire:model="{{ $key }}" rows="3" class="form-input"></textarea>
                                @break
                            @case(\App\Enums\MedicalFieldType::Number)
                                <input id="{{ $id }}" type="number" step="any" wire:model="{{ $key }}" class="form-input">
                                @break
                            @case(\App\Enums\MedicalFieldType::Date)
                                <input id="{{ $id }}" type="date" wire:model="{{ $key }}" class="form-input">
                                @break
                            @case(\App\Enums\MedicalFieldType::YesNo)
                                <div class="flex gap-5 pt-1">
                                    <label class="flex items-center gap-2 text-sm text-slate-700"><input type="radio" value="si" wire:model="{{ $key }}" class="border-slate-300 text-brand-600 focus:ring-brand-500"> Sí</label>
                                    <label class="flex items-center gap-2 text-sm text-slate-700"><input type="radio" value="no" wire:model="{{ $key }}" class="border-slate-300 text-brand-600 focus:ring-brand-500"> No</label>
                                </div>
                                @break
                            @case(\App\Enums\MedicalFieldType::Select)
                                <select id="{{ $id }}" wire:model="{{ $key }}" class="form-input">
                                    <option value="">Seleccionar…</option>
                                    @foreach ($field->options ?? [] as $option)
                                        <option value="{{ $option }}">{{ $option }}</option>
                                    @endforeach
                                </select>
                                @break
                            @case(\App\Enums\MedicalFieldType::Checkboxes)
                                <div class="flex flex-wrap gap-x-5 gap-y-2 pt-1">
                                    @foreach ($field->options ?? [] as $option)
                                        <label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" value="{{ $option }}" wire:model="{{ $key }}" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> {{ $option }}</label>
                                    @endforeach
                                </div>
                                @break
                            @default
                                <input id="{{ $id }}" wire:model="{{ $key }}" class="form-input" maxlength="255">
                        @endswitch
                    </x-field>
                @endforeach
            </div>
        </div>
    @endforeach
</div>

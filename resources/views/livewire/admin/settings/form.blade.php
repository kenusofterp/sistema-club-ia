<div>
    <x-page-header :title="$pageTitle" subtitle="Los cambios se reflejan inmediatamente en el sitio y el sistema" />

    <form wire:submit="save">
        @if ($grouped->count() > 1)
            <div class="mb-4 flex gap-1 overflow-x-auto border-b border-slate-200">
                @foreach ($grouped->keys() as $group)
                    <button type="button" wire:click="$set('tab', '{{ $group }}')"
                            @class(['-mb-px border-b-2 px-4 py-2.5 text-sm font-medium whitespace-nowrap', 'border-brand-600 text-brand-700' => $tab === $group, 'border-transparent text-slate-500 hover:text-slate-800' => $tab !== $group])>
                        {{ $groupLabels[$group] ?? $group }}
                    </button>
                @endforeach
            </div>
        @endif

        @foreach ($grouped as $group => $settings)
            <div @class(['card p-6', 'hidden' => $tab !== $group])>
                <div class="grid gap-6 md:grid-cols-2">
                    @foreach ($settings as $setting)
                        @php($model = 'values.'.$setting->id)
                        <div @class(['md:col-span-2' => in_array($setting->type, ['text', 'image'])]) wire:key="set-{{ $setting->id }}">
                            @switch($setting->type)
                                @case('boolean')
                                    <label class="flex items-start gap-3 rounded-lg border border-slate-200 p-4">
                                        <input type="checkbox" wire:model="{{ $model }}" class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                        <span>
                                            <span class="block text-sm font-medium text-slate-800">{{ $setting->label }}</span>
                                            @if ($setting->help)<span class="form-help block">{{ $setting->help }}</span>@endif
                                        </span>
                                    </label>
                                    @break
                                @case('image')
                                    <x-image-upload :model="'uploads.'.$setting->id" :file="$uploads[$setting->id] ?? null" :current="storage_url($setting->value)"
                                                    :label="$setting->label" :help="$setting->help" aspect="aspect-square" :removable="'removeImage('.$setting->id.')'" />
                                    @break
                                @case('color')
                                    <x-field :label="$setting->label" :for="'s'.$setting->id" :error="$model" :help="$setting->help">
                                        <div class="flex gap-2">
                                            <input type="color" wire:model.live="{{ $model }}" class="h-10 w-14 cursor-pointer rounded-lg border border-slate-300 p-1">
                                            <input id="s{{ $setting->id }}" wire:model.live.debounce.500ms="{{ $model }}" class="form-input font-mono">
                                        </div>
                                    </x-field>
                                    @break
                                @case('select')
                                    <x-field :label="$setting->label" :for="'s'.$setting->id" :error="$model" :help="$setting->help">
                                        <select id="s{{ $setting->id }}" wire:model="{{ $model }}" class="form-input">
                                            @foreach (\App\Support\SettingsCatalog::options($setting->key) as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </x-field>
                                    @break
                                @case('text')
                                    <x-field :label="$setting->label" :for="'s'.$setting->id" :error="$model" :help="$setting->help">
                                        <textarea id="s{{ $setting->id }}" wire:model="{{ $model }}" rows="3" class="form-input"></textarea>
                                    </x-field>
                                    @break
                                @default
                                    <x-field :label="$setting->label" :for="'s'.$setting->id" :error="$model" :help="$setting->help">
                                        <input id="s{{ $setting->id }}" @if (in_array($setting->type, ['integer', 'decimal'])) type="number" step="{{ $setting->type === 'decimal' ? '0.01' : '1' }}" min="0" @endif wire:model="{{ $model }}" class="form-input">
                                    </x-field>
                            @endswitch
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div class="mt-6 flex justify-end">
            <button type="submit" class="btn-primary" wire:loading.attr="disabled"><x-icon name="check" class="size-4" /> Guardar cambios</button>
        </div>
    </form>
</div>

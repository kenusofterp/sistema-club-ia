<div>
    <x-page-header title="Secciones del sitio" subtitle="Bloques de la página de inicio, en el orden en que se muestran. Las que tienen etiqueta de menú aparecen en la navegación.">
        <x-slot:actions>
            <a href="{{ $currentOrganization?->url() ?? route('home') }}" target="_blank" class="btn-secondary"><x-icon name="external" class="size-4" /> Ver sitio</a>
            <button type="button" wire:click="create" class="btn-primary"><x-icon name="plus" class="size-4" /> Nueva sección</button>
        </x-slot:actions>
    </x-page-header>

    <div class="card divide-y divide-slate-100">
        @foreach ($sections as $section)
            <div class="flex flex-wrap items-center gap-4 px-5 py-4" wire:key="sec-{{ $section->id }}">
                <div class="flex flex-col">
                    <button type="button" wire:click="move({{ $section->id }}, -1)" class="text-slate-400 hover:text-slate-700 disabled:opacity-30" @disabled($loop->first) aria-label="Subir">▲</button>
                    <button type="button" wire:click="move({{ $section->id }}, 1)" class="text-slate-400 hover:text-slate-700 disabled:opacity-30" @disabled($loop->last) aria-label="Bajar">▼</button>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="font-medium text-slate-900">{{ $section->title ?: $section->key }}</p>
                        <x-badge color="blue">{{ $section->typeLabel() }}</x-badge>
                        @if ($section->menu_label)<x-badge color="brand">Menú: {{ $section->menu_label }}</x-badge>@endif
                    </div>
                    <p class="text-xs text-slate-500">#{{ $section->key }} {{ $section->subtitle ? '· '.$section->subtitle : '' }}</p>
                </div>
                <div class="flex gap-1">
                    <button type="button" wire:click="toggle({{ $section->id }})" @class(['btn-sm btn', 'bg-emerald-50 text-emerald-700' => $section->is_active, 'bg-slate-100 text-slate-500' => ! $section->is_active])>{{ $section->is_active ? 'Visible' : 'Oculta' }}</button>
                    <button type="button" wire:click="edit({{ $section->id }})" class="btn-ghost btn-sm"><x-icon name="pencil" class="size-4" /></button>
                    <button type="button" wire:click="delete({{ $section->id }})" wire:confirm="¿Eliminar la sección?" class="btn-ghost btn-sm text-red-600"><x-icon name="trash" class="size-4" /></button>
                </div>
            </div>
        @endforeach
    </div>

    <x-modal wire:model="showForm" :title="$editingId ? 'Editar sección' : 'Nueva sección'" max-width="max-w-3xl">
        <form wire:submit="save" id="section-form" class="grid gap-4 sm:grid-cols-2">
            <x-field label="Tipo de sección" for="type" error="type" required>
                <select id="type" wire:model.live="type" class="form-input">
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field label="Identificador (ancla)" for="key" error="key" help="Se usa como /#identificador. Se genera del título si se deja vacío.">
                <input id="key" wire:model="key" class="form-input">
            </x-field>
            <x-field label="Título" for="title" error="title">
                <input id="title" wire:model="title" class="form-input">
            </x-field>
            <x-field label="Etiqueta en el menú" for="menu_label" error="menu_label" help="Vacío = no aparece en el menú.">
                <input id="menu_label" wire:model="menu_label" class="form-input">
            </x-field>
            <x-field label="Subtítulo" for="subtitle" error="subtitle" class="sm:col-span-2">
                <input id="subtitle" wire:model="subtitle" class="form-input">
            </x-field>
            @if (in_array($type, ['about', 'text', 'cta']))
                <x-field :label="$type === 'cta' ? 'Texto del botón' : 'Contenido'" for="content" error="content" class="sm:col-span-2" :help="$type === 'cta' ? null : 'Separá párrafos con una línea en blanco.'">
                    @if ($type === 'cta')
                        <input id="content" wire:model="content" class="form-input">
                    @else
                        <textarea id="content" wire:model="content" rows="6" class="form-input"></textarea>
                    @endif
                </x-field>
            @endif
            @if (in_array($type, \App\Models\SiteSection::TYPES_WITH_ITEMS))
                <div class="sm:col-span-2">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="form-label mb-0">{{ $type === 'stats' ? 'Cifras' : 'Tarjetas' }}</span>
                        <button type="button" wire:click="addItem" class="btn-secondary btn-sm"><x-icon name="plus" class="size-4" /> Agregar</button>
                    </div>
                    <div class="space-y-2">
                        @foreach ($items as $i => $item)
                            <div class="grid gap-2 rounded-lg bg-slate-50 p-3 sm:grid-cols-[auto_1fr_2fr_auto]" wire:key="item-{{ $i }}">
                                @if ($type === 'stats')
                                    <input wire:model="items.{{ $i }}.value" class="form-input sm:w-28" placeholder="Valor (ej. 75)">
                                    <input wire:model="items.{{ $i }}.title" class="form-input sm:col-span-2" placeholder="Descripción">
                                @else
                                    <select wire:model="items.{{ $i }}.icon" class="form-input sm:w-36">
                                        @foreach (['trophy', 'users', 'building', 'device', 'calendar', 'shield', 'sparkles', 'clock', 'map-pin', 'id-card', 'banknotes', 'megaphone'] as $icon)
                                            <option value="{{ $icon }}">{{ $icon }}</option>
                                        @endforeach
                                    </select>
                                    <input wire:model="items.{{ $i }}.title" class="form-input" placeholder="Título">
                                    <input wire:model="items.{{ $i }}.text" class="form-input" placeholder="Texto">
                                @endif
                                <button type="button" wire:click="removeItem({{ $i }})" class="btn-ghost text-red-600"><x-icon name="trash" class="size-4" /></button>
                                @error("items.$i.title") <p class="form-error sm:col-span-4">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
            @if (in_array($type, ['about', 'stats', 'cta', 'text']))
                <div class="sm:col-span-2">
                    <x-image-upload model="image" :file="$image" :current="$currentImage" :removable="$editingId ? 'removeImage' : null" />
                </div>
            @endif
            @if (in_array($type, ['activities', 'facilities', 'news', 'contact']))
                <p class="rounded-lg bg-sky-50 p-3 text-sm text-sky-800 sm:col-span-2">El contenido de esta sección se toma automáticamente de {{ ['activities' => 'Actividades', 'facilities' => 'Instalaciones', 'news' => 'Noticias', 'contact' => 'la configuración de contacto'][$type] }}.</p>
            @endif
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Visible</label>
        </form>
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="open = false">Cancelar</button>
            <button type="submit" form="section-form" class="btn-primary">Guardar</button>
        </x-slot:footer>
    </x-modal>
</div>

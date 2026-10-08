<div>
    <x-page-header title="Portada (hero)" subtitle="Diapositivas principales de la página de inicio. Si hay más de una, rotan automáticamente.">
        <x-slot:actions>
            <a href="{{ $currentOrganization?->url() ?? route('home') }}" target="_blank" class="btn-secondary"><x-icon name="external" class="size-4" /> Ver portada</a>
            <button type="button" wire:click="create" class="btn-primary"><x-icon name="plus" class="size-4" /> Nueva diapositiva</button>
        </x-slot:actions>
    </x-page-header>

    <div class="space-y-4">
        @forelse ($slides as $slide)
            <div class="card flex flex-col overflow-hidden sm:flex-row" wire:key="slide-{{ $slide->id }}">
                <div class="relative aspect-video shrink-0 bg-brand-900 sm:w-72">
                    @if ($slide->imageUrl())
                        <img src="{{ $slide->imageUrl() }}" alt="" class="size-full object-cover">
                    @else
                        <div class="size-full bg-gradient-to-br from-brand-700 to-brand-950"></div>
                    @endif
                    <div class="absolute inset-0 bg-slate-950" style="opacity: {{ $slide->overlay_opacity / 100 }}"></div>
                    <p class="absolute inset-x-4 bottom-4 line-clamp-2 font-display text-lg font-bold text-white">{{ $slide->title }}</p>
                </div>
                <div class="flex flex-1 flex-col justify-between gap-3 p-5">
                    <div>
                        <div class="flex items-center gap-2">
                            <p class="font-semibold text-slate-900">{{ $slide->title }}</p>
                            <x-badge :color="$slide->is_active ? 'green' : 'gray'">{{ $slide->is_active ? 'Visible' : 'Oculta' }}</x-badge>
                        </div>
                        <p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ $slide->subtitle }}</p>
                        @if ($slide->button_text)
                            <p class="mt-2 text-xs text-slate-500">Botón: «{{ $slide->button_text }}» → {{ $slide->button_url }}</p>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-1">
                        <button type="button" wire:click="move({{ $slide->id }}, -1)" class="btn-ghost btn-sm" title="Subir" @disabled($loop->first)>↑</button>
                        <button type="button" wire:click="move({{ $slide->id }}, 1)" class="btn-ghost btn-sm" title="Bajar" @disabled($loop->last)>↓</button>
                        <button type="button" wire:click="toggle({{ $slide->id }})" class="btn-ghost btn-sm">{{ $slide->is_active ? 'Ocultar' : 'Mostrar' }}</button>
                        <button type="button" wire:click="edit({{ $slide->id }})" class="btn-ghost btn-sm"><x-icon name="pencil" class="size-4" /> Editar</button>
                        <button type="button" wire:click="delete({{ $slide->id }})" wire:confirm="¿Eliminar la diapositiva?" class="btn-ghost btn-sm text-red-600"><x-icon name="trash" class="size-4" /></button>
                    </div>
                </div>
            </div>
        @empty
            <div class="card"><x-empty-state icon="photo" title="Sin diapositivas" description="Agregá al menos una para mostrar el hero en la portada." /></div>
        @endforelse
    </div>

    <x-modal wire:model="showForm" :title="$editingId ? 'Editar diapositiva' : 'Nueva diapositiva'" max-width="max-w-3xl">
        <form wire:submit="save" id="slide-form" class="grid gap-4 sm:grid-cols-2">
            <x-field label="Título" for="title" error="title" required class="sm:col-span-2">
                <input id="title" wire:model="title" class="form-input">
            </x-field>
            <x-field label="Subtítulo" for="subtitle" error="subtitle" class="sm:col-span-2">
                <textarea id="subtitle" wire:model="subtitle" rows="2" class="form-input"></textarea>
            </x-field>
            <x-field label="Texto del botón principal" for="button_text" error="button_text">
                <input id="button_text" wire:model="button_text" class="form-input" placeholder="Asociate">
            </x-field>
            <x-field label="Enlace del botón principal" for="button_url" error="button_url">
                <input id="button_url" wire:model="button_url" class="form-input" placeholder="/asociate">
            </x-field>
            <x-field label="Texto del botón secundario" for="secondary_button_text" error="secondary_button_text">
                <input id="secondary_button_text" wire:model="secondary_button_text" class="form-input">
            </x-field>
            <x-field label="Enlace del botón secundario" for="secondary_button_url" error="secondary_button_url">
                <input id="secondary_button_url" wire:model="secondary_button_url" class="form-input" placeholder="/#actividades">
            </x-field>
            <x-field label="Oscurecimiento de la imagen ({{ $overlay_opacity }}%)" for="overlay_opacity" error="overlay_opacity" class="sm:col-span-2" help="Más oscuro mejora la lectura del texto sobre fotos claras.">
                <input id="overlay_opacity" type="range" min="0" max="90" step="5" wire:model.live="overlay_opacity" class="w-full accent-brand-600">
            </x-field>
            <div class="sm:col-span-2">
                <x-image-upload model="image" :file="$image" :current="$currentImage" label="Imagen de fondo" help="Recomendado 1920×1080 px, máximo 6 MB. Sin imagen se usa un fondo con el color institucional." />
            </div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Visible</label>
        </form>
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="open = false">Cancelar</button>
            <button type="submit" form="slide-form" class="btn-primary">Guardar</button>
        </x-slot:footer>
    </x-modal>
</div>

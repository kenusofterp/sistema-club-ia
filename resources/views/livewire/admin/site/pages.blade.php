<div>
    <x-page-header title="Páginas" subtitle="Páginas institucionales: estatuto, reglamento, historia, autoridades…">
        <x-slot:actions>
            <button type="button" wire:click="create" class="btn-primary"><x-icon name="plus" class="size-4" /> Nueva página</button>
        </x-slot:actions>
    </x-page-header>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>Página</th><th>En el menú</th><th>Estado</th><th></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($pages as $page)
                    <tr wire:key="page-{{ $page->id }}">
                        <td><p class="font-medium text-slate-900">{{ $page->title }}</p><p class="text-xs text-slate-500">/p/{{ $page->slug }}</p></td>
                        <td>{{ $page->show_in_menu ? 'Sí (orden '.$page->menu_order.')' : 'No' }}</td>
                        <td><x-badge :color="$page->is_published ? 'green' : 'gray'">{{ $page->is_published ? 'Publicada' : 'Borrador' }}</x-badge></td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('site.page', $page) }}" target="_blank" class="btn-ghost btn-sm"><x-icon name="external" class="size-4" /></a>
                            <button type="button" wire:click="edit({{ $page->id }})" class="btn-ghost btn-sm"><x-icon name="pencil" class="size-4" /></button>
                            <button type="button" wire:click="delete({{ $page->id }})" wire:confirm="¿Eliminar la página?" class="btn-ghost btn-sm text-red-600"><x-icon name="trash" class="size-4" /></button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4"><x-empty-state icon="document" title="Sin páginas" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-modal wire:model="showForm" :title="$editingId ? 'Editar página' : 'Nueva página'" max-width="max-w-3xl">
        <form wire:submit="save" id="page-form" class="grid gap-4 sm:grid-cols-2">
            <x-field label="Título" for="title" error="title" required>
                <input id="title" wire:model="title" class="form-input">
            </x-field>
            <x-field label="URL" for="slug" error="slug" help="/p/{{ $slug ?: 'se-genera-del-titulo' }}">
                <input id="slug" wire:model="slug" class="form-input">
            </x-field>
            <x-field label="Contenido" for="body" error="body" required class="sm:col-span-2" help="Separá párrafos con una línea en blanco.">
                <textarea id="body" wire:model="body" rows="12" class="form-input"></textarea>
            </x-field>
            <x-field label="Descripción para buscadores" for="meta_description" error="meta_description" class="sm:col-span-2">
                <input id="meta_description" wire:model="meta_description" class="form-input">
            </x-field>
            <div class="flex flex-wrap items-center gap-4 sm:col-span-2">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_published" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Publicada</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="show_in_menu" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Mostrar en el menú</label>
                <label class="flex items-center gap-2 text-sm">Orden <input type="number" min="0" wire:model="menu_order" class="form-input w-20"></label>
            </div>
            <div class="sm:col-span-2">
                <x-image-upload model="image" :file="$image" :current="$currentImage" label="Imagen de cabecera" />
            </div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="open = false">Cancelar</button>
            <button type="submit" form="page-form" class="btn-primary">Guardar</button>
        </x-slot:footer>
    </x-modal>
</div>

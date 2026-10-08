<div>
    <x-page-header title="Categorías de socios" subtitle="Definen la cuota social mensual y el derecho de ingreso">
        <x-slot:actions>
            <button type="button" wire:click="create" class="btn-primary"><x-icon name="plus" class="size-4" /> Nueva categoría</button>
        </x-slot:actions>
    </x-page-header>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr><th>Categoría</th><th>Edades</th><th class="text-right">Cuota mensual</th><th class="text-right">Ingreso</th><th class="text-right">Socios activos</th><th>Estado</th><th></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($categories as $category)
                    <tr wire:key="cat-{{ $category->id }}">
                        <td>
                            <p class="font-medium text-slate-900">{{ $category->name }}</p>
                            <p class="text-xs text-slate-500">{{ $category->description }}</p>
                        </td>
                        <td>{{ $category->ageRangeLabel() }}</td>
                        <td class="text-right tabular-nums">{{ money($category->monthly_fee) }}</td>
                        <td class="text-right tabular-nums">{{ money($category->admission_fee) }}</td>
                        <td class="text-right tabular-nums">{{ $category->members_count }}</td>
                        <td><x-badge :color="$category->is_active ? 'green' : 'gray'">{{ $category->is_active ? 'Activa' : 'Inactiva' }}</x-badge></td>
                        <td class="text-right whitespace-nowrap">
                            <button type="button" wire:click="edit({{ $category->id }})" class="btn-ghost btn-sm"><x-icon name="pencil" class="size-4" /></button>
                            <button type="button" wire:click="delete({{ $category->id }})" wire:confirm="¿Eliminar la categoría?" class="btn-ghost btn-sm text-red-600"><x-icon name="trash" class="size-4" /></button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty-state icon="tag" title="No hay categorías" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-modal wire:model="showForm" :title="$editingId ? 'Editar categoría' : 'Nueva categoría'">
        <form wire:submit="save" id="category-form" class="grid gap-4 sm:grid-cols-2">
            <x-field label="Nombre" for="name" error="name" required class="sm:col-span-2">
                <input id="name" wire:model="name" class="form-input">
            </x-field>
            <x-field label="Descripción" for="description" error="description" class="sm:col-span-2">
                <input id="description" wire:model="description" class="form-input">
            </x-field>
            <x-field label="Cuota mensual" for="monthly_fee" error="monthly_fee" required>
                <input id="monthly_fee" type="number" step="0.01" min="0" wire:model="monthly_fee" class="form-input">
            </x-field>
            <x-field label="Derecho de ingreso" for="admission_fee" error="admission_fee" required>
                <input id="admission_fee" type="number" step="0.01" min="0" wire:model="admission_fee" class="form-input">
            </x-field>
            <x-field label="Edad mínima" for="min_age" error="min_age">
                <input id="min_age" type="number" min="0" wire:model="min_age" class="form-input">
            </x-field>
            <x-field label="Edad máxima" for="max_age" error="max_age">
                <input id="max_age" type="number" min="0" wire:model="max_age" class="form-input">
            </x-field>
            <x-field label="Orden" for="sort_order" error="sort_order">
                <input id="sort_order" type="number" min="0" wire:model="sort_order" class="form-input">
            </x-field>
            <label class="flex items-center gap-2 self-end pb-2 text-sm text-slate-700">
                <input type="checkbox" wire:model="is_active" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Activa
            </label>
        </form>
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="open = false">Cancelar</button>
            <button type="submit" form="category-form" class="btn-primary">Guardar</button>
        </x-slot:footer>
    </x-modal>
</div>

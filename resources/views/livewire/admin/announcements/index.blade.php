<div>
    <x-page-header title="Avisos a socios" subtitle="Comunicados que se muestran en el portal del socio">
        <x-slot:actions>
            <button type="button" wire:click="create" class="btn-primary"><x-icon name="plus" class="size-4" /> Nuevo aviso</button>
        </x-slot:actions>
    </x-page-header>

    <div class="card divide-y divide-slate-100">
        @forelse ($announcements as $a)
            @php($live = $a->published_at && $a->published_at->isPast() && (! $a->expires_at || $a->expires_at->isFuture()))
            <div class="flex items-start justify-between gap-4 px-5 py-4" wire:key="an-{{ $a->id }}">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="font-medium text-slate-900">{{ $a->title }}</p>
                        <x-badge :color="$live ? 'green' : 'gray'">{{ $live ? 'Vigente' : ($a->published_at?->isFuture() ? 'Programado' : 'No vigente') }}</x-badge>
                        <x-badge :color="['info' => 'blue', 'success' => 'green', 'warning' => 'yellow'][$a->level] ?? 'gray'">{{ \App\Models\Announcement::LEVELS[$a->level] ?? $a->level }}</x-badge>
                    </div>
                    <p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ $a->body }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ $a->published_at?->format('d/m/Y H:i') ?? 'Sin publicar' }}@if ($a->expires_at) → {{ $a->expires_at->format('d/m/Y H:i') }}@endif</p>
                </div>
                <div class="flex shrink-0 gap-1">
                    <button type="button" wire:click="edit({{ $a->id }})" class="btn-ghost btn-sm"><x-icon name="pencil" class="size-4" /></button>
                    <button type="button" wire:click="delete({{ $a->id }})" wire:confirm="¿Eliminar el aviso?" class="btn-ghost btn-sm text-red-600"><x-icon name="trash" class="size-4" /></button>
                </div>
            </div>
        @empty
            <x-empty-state icon="megaphone" title="No hay avisos" />
        @endforelse
    </div>

    <x-modal wire:model="showForm" :title="$editingId ? 'Editar aviso' : 'Nuevo aviso'">
        <form wire:submit="save" id="ann-form" class="grid gap-4 sm:grid-cols-2">
            <x-field label="Título" for="title" error="title" required class="sm:col-span-2">
                <input id="title" wire:model="title" class="form-input">
            </x-field>
            <x-field label="Texto" for="body" error="body" required class="sm:col-span-2">
                <textarea id="body" wire:model="body" rows="5" class="form-input"></textarea>
            </x-field>
            <x-field label="Tipo" for="level" error="level">
                <select id="level" wire:model="level" class="form-input">
                    @foreach (\App\Models\Announcement::LEVELS as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </x-field>
            <div></div>
            <x-field label="Publicar desde" for="published_at" error="published_at" help="Vacío = borrador.">
                <input id="published_at" type="datetime-local" wire:model="published_at" class="form-input">
            </x-field>
            <x-field label="Vence" for="expires_at" error="expires_at">
                <input id="expires_at" type="datetime-local" wire:model="expires_at" class="form-input">
            </x-field>
        </form>
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="open = false">Cancelar</button>
            <button type="submit" form="ann-form" class="btn-primary">Guardar</button>
        </x-slot:footer>
    </x-modal>
</div>

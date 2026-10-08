<div>
    <x-page-header title="Entidades" subtitle="Clubes y gimnasios administrados. Cada uno tiene sus socios, cuotas, pagos, configuración y sitio web propios.">
        <x-slot:actions>
            <button type="button" wire:click="create" class="btn-primary"><x-icon name="plus" class="size-4" /> Nueva entidad</button>
        </x-slot:actions>
    </x-page-header>

    @if ($organizations->isEmpty())
        <div class="card">
            <x-empty-state icon="building" title="Bienvenido/a: todavía no hay ninguna entidad"
                           description="El primer paso es crear el club o gimnasio que vas a administrar. Se genera con su configuración, sitio web y datos base, y después podés editar todo.">
                <div class="mt-4 flex flex-wrap justify-center gap-2">
                    <button type="button" wire:click="create" class="btn-primary"><x-icon name="plus" class="size-4" /> Crear la primera entidad</button>
                    <a href="{{ route('admin.help') }}" wire:navigate class="btn-secondary"><x-icon name="help" class="size-4" /> Ver el manual de uso</a>
                </div>
            </x-empty-state>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-2 2xl:grid-cols-3">
        @foreach ($organizations as $org)
            <div @class(['card flex flex-col', 'opacity-60' => ! $org->is_active || $org->trashed(), 'ring-2 ring-brand-500' => $org->id === $currentOrganization?->id]) wire:key="org-{{ $org->id }}">
                <div class="flex items-start justify-between gap-3 p-5">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-display text-lg font-semibold text-slate-900">{{ $org->name }}</h3>
                            @if ($org->is_default)<x-badge color="brand">Principal</x-badge>@endif
                            @unless ($org->is_active)<x-badge color="gray">Inactiva</x-badge>@endunless
                        </div>
                        <p class="text-sm text-slate-500">{{ $org->typeLabel() }} · <a href="{{ $org->url() }}" target="_blank" class="text-brand-700 hover:underline">{{ parse_url($org->url(), PHP_URL_HOST) }}</a></p>
                    </div>
                    <x-icon :name="$org->type === 'gimnasio' ? 'trophy' : 'building'" class="size-7 shrink-0 text-brand-500" />
                </div>
                <dl class="grid grid-cols-2 gap-px border-y border-slate-100 bg-slate-100 text-sm sm:grid-cols-4">
                    <div class="bg-white px-4 py-3"><dt class="text-xs text-slate-500">Socios activos</dt><dd class="font-semibold tabular-nums">{{ number_format($members[$org->id] ?? 0, 0, ',', '.') }}</dd></div>
                    <div class="bg-white px-4 py-3"><dt class="text-xs text-slate-500">Cobrado (mes)</dt><dd class="font-semibold tabular-nums">{{ money($income[$org->id] ?? 0) }}</dd></div>
                    <div class="bg-white px-4 py-3"><dt class="text-xs text-slate-500">Deuda vencida</dt><dd class="font-semibold text-red-600 tabular-nums">{{ money($debt[$org->id] ?? 0) }}</dd></div>
                    <div class="bg-white px-4 py-3"><dt class="text-xs text-slate-500">Planes vigentes</dt><dd class="font-semibold tabular-nums">{{ $org->usesGym() ? ($plans[$org->id] ?? 0) : '—' }}</dd></div>
                </dl>
                <div class="mt-auto flex flex-wrap justify-end gap-1 px-3 py-2">
                    <a href="{{ $org->url() }}" target="_blank" class="btn-ghost btn-sm"><x-icon name="external" class="size-4" /> Sitio</a>
                    <button type="button" wire:click="edit({{ $org->id }})" class="btn-ghost btn-sm"><x-icon name="pencil" class="size-4" /> Editar</button>
                    @if ($org->is_active && $org->id !== $currentOrganization?->id)
                        <button type="button" wire:click="manage({{ $org->id }})" class="btn-primary btn-sm">Administrar <x-icon name="arrow-right" class="size-4" /></button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <x-modal wire:model="showForm" :title="$editingId ? 'Editar entidad' : 'Nueva entidad'" max-width="max-w-lg">
        <form wire:submit="save" id="org-form" class="grid gap-4">
            <x-field label="Nombre" for="name" error="name" required>
                <input id="name" wire:model.live.blur="name" class="form-input" placeholder="Ej.: Club Atlético del Sur">
            </x-field>
            <x-field label="Tipo" for="type" error="type" required help="Define los módulos: cuota social y actividades (club), planes y membresías (gimnasio) o ambos.">
                <select id="type" wire:model="type" class="form-input">
                    @foreach (\App\Models\Organization::TYPES as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field label="Identificador" for="slug" error="slug" required help="Se usa como subdominio: {{ $slug ?: 'identificador' }}.{{ parse_url(config('app.url'), PHP_URL_HOST) }}">
                <input id="slug" wire:model="slug" class="form-input">
            </x-field>
            <x-field label="Dominio propio" for="domain" error="domain" help="Opcional (ej.: miclub.com). Debe apuntar a este servidor.">
                <input id="domain" wire:model="domain" class="form-input" placeholder="miclub.com">
            </x-field>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Activa</label>
            @error('is_active') <p class="form-error">{{ $message }}</p> @enderror
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_default" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Principal (se muestra en el dominio de la aplicación)</label>
            @unless ($editingId)
                <p class="rounded-lg bg-sky-50 p-3 text-xs text-sky-800">Se creará con su configuración, sitio web inicial y datos base (categorías y actividades para clubes; clases y planes para gimnasios). Todo es editable.</p>
            @endunless
        </form>
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="open = false">Cancelar</button>
            <button type="submit" form="org-form" class="btn-primary" wire:loading.attr="disabled" wire:target="save">Guardar</button>
        </x-slot:footer>
    </x-modal>
</div>

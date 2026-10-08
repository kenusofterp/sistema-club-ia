<div>
    <x-page-header title="Instalaciones" subtitle="Espacios del club, horarios y tarifas de reserva">
        <x-slot:actions>
            <button type="button" wire:click="create" class="btn-primary"><x-icon name="plus" class="size-4" /> Nueva instalación</button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($facilities as $facility)
            <div class="card overflow-hidden" wire:key="fac-{{ $facility->id }}">
                <div class="aspect-[16/7] bg-brand-100">
                    @if ($facility->imageUrl())
                        <img src="{{ $facility->imageUrl() }}" alt="" class="size-full object-cover">
                    @else
                        <div class="grid size-full place-items-center text-brand-400"><x-icon name="building" class="size-10" /></div>
                    @endif
                </div>
                <div class="p-5">
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="font-semibold text-slate-900">{{ $facility->name }}</h3>
                        <x-badge :color="$facility->is_active ? 'green' : 'gray'">{{ $facility->is_active ? 'Activa' : 'Inactiva' }}</x-badge>
                    </div>
                    <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
                        <div><dt class="text-xs text-slate-500">Horario</dt><dd>{{ substr($facility->opens_at, 0, 5) }} a {{ substr($facility->closes_at, 0, 5) }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Turno</dt><dd>{{ $facility->slot_minutes }} min</dd></div>
                        <div><dt class="text-xs text-slate-500">Tarifa por hora</dt><dd>{{ money($facility->hourly_rate) }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Reservas</dt><dd>{{ $facility->is_bookable ? 'Habilitadas' : 'No' }}</dd></div>
                    </dl>
                </div>
                <div class="flex justify-end gap-1 border-t border-slate-100 px-3 py-2">
                    <button type="button" wire:click="edit({{ $facility->id }})" class="btn-ghost btn-sm"><x-icon name="pencil" class="size-4" /> Editar</button>
                    <button type="button" wire:click="delete({{ $facility->id }})" wire:confirm="¿Eliminar la instalación?" class="btn-ghost btn-sm text-red-600"><x-icon name="trash" class="size-4" /></button>
                </div>
            </div>
        @empty
            <div class="card md:col-span-2 xl:col-span-3"><x-empty-state icon="building" title="No hay instalaciones" /></div>
        @endforelse
    </div>

    <x-modal wire:model="showForm" :title="$editingId ? 'Editar instalación' : 'Nueva instalación'">
        <form wire:submit="save" id="facility-form" class="grid gap-4 sm:grid-cols-2">
            <x-field label="Nombre" for="name" error="name" required class="sm:col-span-2">
                <input id="name" wire:model="name" class="form-input">
            </x-field>
            <x-field label="Descripción" for="description" error="description" class="sm:col-span-2">
                <textarea id="description" wire:model="description" rows="2" class="form-input"></textarea>
            </x-field>
            <x-field label="Abre" for="opens_at" error="opens_at" required>
                <input id="opens_at" type="time" wire:model="opens_at" class="form-input">
            </x-field>
            <x-field label="Cierra" for="closes_at" error="closes_at" required>
                <input id="closes_at" type="time" wire:model="closes_at" class="form-input">
            </x-field>
            <x-field label="Duración del turno" for="slot_minutes" error="slot_minutes" required>
                <select id="slot_minutes" wire:model="slot_minutes" class="form-input">
                    @foreach ([30, 45, 60, 90, 120, 180, 240] as $m)
                        <option value="{{ $m }}">{{ $m }} minutos</option>
                    @endforeach
                </select>
            </x-field>
            <x-field label="Turnos máximos por reserva" for="max_slots_per_booking" error="max_slots_per_booking" required>
                <input id="max_slots_per_booking" type="number" min="1" max="12" wire:model="max_slots_per_booking" class="form-input">
            </x-field>
            <x-field label="Tarifa por hora" for="hourly_rate" error="hourly_rate" required help="0 = sin cargo.">
                <input id="hourly_rate" type="number" step="0.01" min="0" wire:model="hourly_rate" class="form-input">
            </x-field>
            <x-field label="Capacidad (personas)" for="capacity" error="capacity">
                <input id="capacity" type="number" min="1" wire:model="capacity" class="form-input">
            </x-field>
            <div class="flex flex-wrap gap-4 sm:col-span-2">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Activa</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_bookable" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Admite reservas</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_public" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Visible en la web</label>
            </div>
            <div class="sm:col-span-2">
                <x-image-upload model="image" :file="$image" :current="$currentImage" />
            </div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="open = false">Cancelar</button>
            <button type="submit" form="facility-form" class="btn-primary">Guardar</button>
        </x-slot:footer>
    </x-modal>
</div>

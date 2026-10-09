<div>
    <x-page-header :title="$tournament ? 'Editar torneo' : 'Nuevo torneo'">
        <x-slot:breadcrumb><a href="{{ route('admin.tournaments.index') }}" wire:navigate class="hover:text-brand-700">Torneos</a> /</x-slot:breadcrumb>
    </x-page-header>

    <form wire:submit="save" class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <div class="card grid gap-5 p-6 md:grid-cols-2">
                <x-field label="Nombre" for="name" error="name" required class="md:col-span-2">
                    <input id="name" wire:model="name" class="form-input" placeholder="ej. Torneo de primavera">
                </x-field>
                <x-field label="Descripción" for="description" error="description" class="md:col-span-2" help="Lugar, horarios, qué llevar… Lo ven los alumnos en el portal.">
                    <textarea id="description" wire:model="description" rows="3" class="form-input"></textarea>
                </x-field>
            </div>

            <div class="card p-6">
                <div class="mb-4 flex items-center justify-between gap-2">
                    <div>
                        <h2 class="font-semibold text-slate-900">Días</h2>
                        <p class="text-sm text-slate-500">Cada día puede tener uno o varios {{ mb_strtolower(activity_label(true)) }}.</p>
                    </div>
                    <button type="button" wire:click="addDay" class="btn-secondary btn-sm"><x-icon name="plus" class="size-4" /> Agregar día</button>
                </div>
                @error('days') <p class="form-error mb-2">{{ $message }}</p> @enderror
                <div class="space-y-3">
                    @foreach ($days as $i => $day)
                        <div class="rounded-lg bg-slate-50 p-4" wire:key="day-{{ $i }}">
                            <div class="grid gap-3 sm:grid-cols-[12rem_1fr_auto]">
                                <x-field label="Fecha" for="days-{{ $i }}-date" :error="'days.'.$i.'.date'" required>
                                    <input id="days-{{ $i }}-date" type="date" wire:model="days.{{ $i }}.date" class="form-input">
                                </x-field>
                                <x-field label="Nota (opcional)" for="days-{{ $i }}-notes" :error="'days.'.$i.'.notes'">
                                    <input id="days-{{ $i }}-notes" wire:model="days.{{ $i }}.notes" class="form-input" placeholder="ej. 9 a 13 h, cancha 2">
                                </x-field>
                                @if (count($days) > 1)
                                    <button type="button" wire:click="removeDay({{ $i }})" class="btn-ghost self-end text-red-600" aria-label="Quitar día"><x-icon name="trash" class="size-4" /></button>
                                @endif
                            </div>
                            <p class="form-label mt-3">{{ activity_label(true) }} que juegan este día</p>
                            <div class="grid gap-1 sm:grid-cols-2 lg:grid-cols-3">
                                @forelse ($activities as $activity)
                                    <label class="flex items-center gap-2 text-sm" wire:key="day-{{ $i }}-a-{{ $activity->id }}">
                                        <input type="checkbox" value="{{ $activity->id }}" wire:model="days.{{ $i }}.activity_ids" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                        {{ $activity->name }}
                                    </label>
                                @empty
                                    <p class="text-sm text-slate-500">No tenés {{ mb_strtolower(activity_label(true)) }} activos.</p>
                                @endforelse
                            </div>
                            @error("days.$i.activity_ids") <p class="form-error mt-1">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="card grid gap-5 p-6">
                <x-field label="Costo" for="price" error="price" required help="Lo paga cada participante. Las becas no se aplican a los torneos.">
                    <input id="price" type="number" step="0.01" min="0" wire:model="price" class="form-input">
                </x-field>
                <x-field label="Fecha máxima de pago" for="payment_due_date" error="payment_due_date" required>
                    <input id="payment_due_date" type="date" wire:model="payment_due_date" class="form-input">
                </x-field>
                <fieldset class="space-y-2">
                    <legend class="form-label">¿Quiénes participan?</legend>
                    <label class="flex items-start gap-2 text-sm">
                        <input type="radio" wire:model="access" value="libre" class="mt-0.5 border-slate-300 text-brand-600 focus:ring-brand-500">
                        <span><strong>Libre</strong><span class="block text-slate-500">Se pueden anotar desde el portal los alumnos de los {{ mb_strtolower(activity_label(true)) }} elegidos. Vos también podés anotarlos.</span></span>
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <input type="radio" wire:model="access" value="elegidos" class="mt-0.5 border-slate-300 text-brand-600 focus:ring-brand-500">
                        <span><strong>Solo los que yo elija</strong><span class="block text-slate-500">Solo vos anotás a los participantes.</span></span>
                    </label>
                </fieldset>
                @if ($tournament)
                    <p class="text-xs text-slate-500">Si cambiás el costo o la fecha máxima, se actualizan los cargos de quienes todavía no pagaron nada.</p>
                @endif
            </div>
            <div class="flex gap-2">
                <a href="{{ $tournament ? route('admin.tournaments.show', $tournament) : route('admin.tournaments.index') }}" wire:navigate class="btn-secondary flex-1">Cancelar</a>
                <button type="submit" class="btn-primary flex-1"><x-icon name="check" class="size-4" /> Guardar</button>
            </div>
        </div>
    </form>
</div>

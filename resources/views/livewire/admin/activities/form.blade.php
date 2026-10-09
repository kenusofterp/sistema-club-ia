<div>
    <x-page-header :title="$activity ? 'Editar actividad' : 'Nueva actividad'">
        <x-slot:breadcrumb><a href="{{ route('admin.activities.index') }}" wire:navigate class="hover:text-brand-700">Actividades</a> /</x-slot:breadcrumb>
    </x-page-header>

    <form wire:submit="save" class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <div class="card grid gap-5 p-6 md:grid-cols-2">
                <x-field label="Nombre" for="name" error="name" required>
                    <input id="name" wire:model.live.blur="name" class="form-input">
                </x-field>
                <x-field label="URL (slug)" for="slug" error="slug" required help="Dirección en la web: /actividades/{{ $slug ?: '...' }}">
                    <input id="slug" wire:model="slug" class="form-input">
                </x-field>
                <x-field label="Resumen" for="summary" error="summary" class="md:col-span-2">
                    <input id="summary" wire:model="summary" class="form-input" maxlength="300">
                </x-field>
                <x-field label="Descripción" for="description" error="description" class="md:col-span-2">
                    <textarea id="description" wire:model="description" rows="6" class="form-input"></textarea>
                </x-field>
            </div>

            <div class="card p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="font-semibold text-slate-900">Horarios</h2>
                    <button type="button" wire:click="addSchedule" class="btn-secondary btn-sm"><x-icon name="plus" class="size-4" /> Agregar horario</button>
                </div>
                <div class="space-y-3">
                    @forelse ($schedules as $i => $schedule)
                        <div class="grid grid-cols-2 gap-3 rounded-lg bg-slate-50 p-3 sm:grid-cols-[1fr_auto_auto_1fr_auto]" wire:key="sch-{{ $i }}">
                            <select wire:model="schedules.{{ $i }}.day_of_week" class="form-input" aria-label="Día">
                                @foreach (\App\Models\ActivitySchedule::DAYS as $day => $label)
                                    <option value="{{ $day }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <input type="time" wire:model="schedules.{{ $i }}.start_time" class="form-input" aria-label="Inicio">
                            <input type="time" wire:model="schedules.{{ $i }}.end_time" class="form-input" aria-label="Fin">
                            @if ($facilities->isNotEmpty())
                                <select wire:model="schedules.{{ $i }}.facility_id" class="form-input" aria-label="Sede">
                                    <option value="">Lugar / sede…</option>
                                    @foreach ($facilities as $fid => $fname)
                                        <option value="{{ $fid }}">{{ $fname }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input wire:model="schedules.{{ $i }}.location" class="form-input" placeholder="Lugar" aria-label="Lugar">
                            @endif
                            <button type="button" wire:click="removeSchedule({{ $i }})" class="btn-ghost text-red-600"><x-icon name="trash" class="size-4" /></button>
                            @error("schedules.$i.end_time") <p class="form-error col-span-full">{{ $message }}</p> @enderror
                            @error("schedules.$i.start_time") <p class="form-error col-span-full">{{ $message }}</p> @enderror
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Sin horarios cargados.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="card grid gap-5 p-6">
                <x-field label="Cuota mensual" for="monthly_fee" error="monthly_fee" required>
                    <input id="monthly_fee" type="number" step="0.01" min="0" wire:model="monthly_fee" class="form-input">
                </x-field>
                <x-field label="Inscripción anual" for="enrollment_fee" error="enrollment_fee" help="Se cobra una vez por año al inscribirse (0 = sin costo). Para la reinscripción de un año nuevo usá «Cobrar inscripción» en el listado.">
                    <input id="enrollment_fee" type="number" step="0.01" min="0" wire:model="enrollment_fee" class="form-input">
                </x-field>
                <x-field label="Cupo máximo" for="capacity" error="capacity" help="Vacío = sin límite.">
                    <input id="capacity" type="number" min="1" wire:model="capacity" class="form-input">
                </x-field>
                <div class="grid grid-cols-2 gap-3">
                    <x-field label="Edad mínima" for="min_age" error="min_age">
                        <input id="min_age" type="number" min="0" wire:model="min_age" class="form-input">
                    </x-field>
                    <x-field label="Edad máxima" for="max_age" error="max_age">
                        <input id="max_age" type="number" min="0" wire:model="max_age" class="form-input">
                    </x-field>
                </div>
                <x-field label="Profesor/a responsable" for="instructor_id" error="instructor_id">
                    <select id="instructor_id" wire:model="instructor_id" class="form-input">
                        <option value="">Sin asignar</option>
                        @foreach ($instructors as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </x-field>
                @if ($instructors->count() > 1)
                    <div>
                        <span class="form-label">Otros profesores a cargo</span>
                        <div class="max-h-48 space-y-1 overflow-y-auto rounded-lg border border-slate-200 p-2">
                            @foreach ($instructors as $id => $name)
                                @continue((int) $id === (int) $instructor_id)
                                <label class="flex items-center gap-2 rounded px-1 py-1 text-sm text-slate-700 hover:bg-slate-50">
                                    <input type="checkbox" value="{{ $id }}" wire:model="instructorIds" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> {{ $name }}
                                </label>
                            @endforeach
                        </div>
                        <p class="form-help">Todos pueden tomar asistencia, suspender la clase y cobrar a sus alumnos.</p>
                    </div>
                @endif
                <label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" wire:model="is_active" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Activa (admite inscripciones)</label>
                <label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" wire:model="is_public" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Visible en la página web</label>
                <label class="flex items-start gap-2 text-sm text-slate-700">
                    <input type="checkbox" wire:model="allows_enrollment" class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    <span>Admite inscripción mensual <span class="form-help block">Desmarcar para clases de gimnasio a las que solo se accede con un plan.</span></span>
                </label>
            </div>
            <div class="card p-6">
                <x-image-upload model="image" :file="$image" :current="$activity?->imageUrl()" label="Imagen" />
            </div>
            <div class="flex justify-end gap-2">
                <a href="{{ route('admin.activities.index') }}" wire:navigate class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-primary" wire:loading.attr="disabled"><x-icon name="check" class="size-4" /> Guardar</button>
            </div>
        </div>
    </form>
</div>

<div>
    <x-page-header :title="activity_label(true)" subtitle="Grupos, horarios, profesores, cupos y cuotas">
        <x-slot:actions>
            @can('actividades.gestionar')
                <button type="button" wire:click="openIncrease" class="btn-secondary"><x-icon name="arrow-up" class="size-4" /> Aumentar cuotas</button>
                <a href="{{ route('admin.activities.create') }}" wire:navigate class="btn-primary"><x-icon name="plus" class="size-4" /> Agregar {{ mb_strtolower(activity_label()) }}</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 max-w-md">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar…" class="form-input">
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($activities as $activity)
            <div class="card flex flex-col overflow-hidden" wire:key="act-{{ $activity->id }}">
                <div class="flex gap-4 p-5">
                    <div class="size-16 shrink-0 overflow-hidden rounded-xl bg-brand-100">
                        @if ($activity->imageUrl())
                            <img src="{{ $activity->imageUrl() }}" alt="" class="size-full object-cover">
                        @else
                            <div class="grid size-full place-items-center text-brand-500"><x-icon name="trophy" class="size-7" /></div>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="font-semibold text-slate-900">{{ $activity->name }}</h3>
                            <x-badge :color="$activity->is_active ? 'green' : 'gray'">{{ $activity->is_active ? 'Activa' : 'Inactiva' }}</x-badge>
                        </div>
                        <p class="text-sm text-slate-500">{{ money($activity->monthly_fee) }}/mes @if ($activity->instructor) · {{ $activity->instructor->name }} @endif</p>
                        @if ((float) $activity->enrollment_fee > 0)
                            <p class="text-xs text-slate-500">Inscripción anual {{ money($activity->enrollment_fee) }}</p>
                        @endif
                        <p class="mt-1 text-xs text-slate-500">
                            {{ $activity->active_enrollments_count }} inscriptos{{ $activity->capacity ? ' de '.$activity->capacity : '' }}
                            @unless ($activity->is_public) · <span class="text-amber-700">No visible en la web</span> @endunless
                        </p>
                        @if ($activity->capacity)
                            <div class="mt-2 h-1.5 rounded-full bg-slate-100">
                                <div class="h-1.5 rounded-full {{ $activity->active_enrollments_count >= $activity->capacity ? 'bg-red-500' : 'bg-brand-500' }}" style="width: {{ min(100, $activity->active_enrollments_count / $activity->capacity * 100) }}%"></div>
                            </div>
                        @endif
                    </div>
                </div>
                @if ($activity->schedules->isNotEmpty())
                    <ul class="mx-5 mb-4 space-y-1 rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
                        @foreach ($activity->schedules as $schedule)
                            <li class="flex justify-between"><span class="font-medium">{{ $schedule->dayName() }}</span><span>{{ $schedule->timeRange() }} {{ $schedule->location ? '· '.$schedule->location : '' }}</span></li>
                        @endforeach
                    </ul>
                @endif
                <div class="mt-auto flex justify-end gap-1 border-t border-slate-100 px-3 py-2">
                    <a href="{{ route('admin.enrollments', ['actividad' => $activity->id]) }}" wire:navigate class="btn-ghost btn-sm"><x-icon name="users" class="size-4" /> Inscriptos</a>
                    @if ((float) $activity->enrollment_fee > 0)
                        @can('inscripciones.gestionar')
                            <button type="button" wire:click="chargeRegistration({{ $activity->id }})" wire:confirm="¿Cobrar la inscripción {{ $currentYear }} ({{ money($activity->enrollment_fee) }}) a todos los inscriptos de {{ $activity->name }}? A quien ya la tiene no se le cobra de nuevo." class="btn-ghost btn-sm" title="Reinscripción del año"><x-icon name="banknotes" class="size-4" /> Cobrar inscripción {{ $currentYear }}</button>
                        @endcan
                    @endif
                    @can('actividades.gestionar')
                        <a href="{{ route('admin.activities.edit', $activity->id) }}" wire:navigate class="btn-ghost btn-sm"><x-icon name="pencil" class="size-4" /> Editar</a>
                        <button type="button" wire:click="delete({{ $activity->id }})" wire:confirm="¿Eliminar la actividad?" class="btn-ghost btn-sm text-red-600"><x-icon name="trash" class="size-4" /></button>
                    @endcan
                </div>
            </div>
        @empty
            <div class="card md:col-span-2 xl:col-span-3"><x-empty-state icon="trophy" :title="'No hay '.mb_strtolower(activity_label(true))" /></div>
        @endforelse
    </div>

    @can('actividades.gestionar')
        <x-modal wire:model="showIncrease" title="Aumentar cuotas" max-width="max-w-xl">
            <div class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="Tipo de aumento" for="increaseMode" error="increaseMode">
                        <select id="increaseMode" wire:model.live="increaseMode" class="form-input">
                            <option value="porcentaje">Porcentaje (%)</option>
                            <option value="monto">Monto fijo ($)</option>
                        </select>
                    </x-field>
                    <x-field :label="$increaseMode === 'porcentaje' ? 'Porcentaje' : 'Monto a sumar'" for="increaseValue" error="increaseValue">
                        <input id="increaseValue" type="number" step="0.01" wire:model.live.debounce.400ms="increaseValue" class="form-input" placeholder="{{ $increaseMode === 'porcentaje' ? 'ej. 20' : 'ej. 10000' }}">
                    </x-field>
                </div>

                <fieldset>
                    <legend class="form-label">{{ activity_label(true) }} a los que se aplica</legend>
                    @error('increaseIds') <p class="form-error">{{ $message }}</p> @enderror
                    <div class="grid max-h-48 gap-1 overflow-y-auto sm:grid-cols-2">
                        @foreach ($activities as $item)
                            <label class="flex items-center gap-2 text-sm" wire:key="inc-{{ $item->id }}">
                                <input type="checkbox" value="{{ $item->id }}" wire:model.live="increaseIds" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                {{ $item->name }} @unless ($item->is_active) <span class="text-xs text-slate-400">(inactiva)</span> @endunless
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                @if ($preview)
                    <div class="rounded-lg bg-slate-50 p-3 text-sm">
                        <p class="mb-2 font-medium text-slate-700">Vista previa</p>
                        <ul class="space-y-1">
                            @foreach ($preview['activities'] as $row)
                                <li class="flex justify-between gap-2"><span>{{ $row['name'] }}</span><span class="tabular-nums text-slate-500">{{ money($row['from']) }} → <strong class="text-slate-900">{{ money($row['to']) }}</strong></span></li>
                            @endforeach
                        </ul>
                        @if ($preview['customFees'] > 0)
                            <p class="mt-2 text-xs text-slate-500">También se ajustan {{ $preview['customFees'] }} cuotas individuales fijas con el mismo criterio.</p>
                        @endif
                    </div>
                @endif
                <p class="text-xs text-slate-500">Rige desde la próxima generación de cuotas; las ya generadas no cambian. Las becas en % se recalculan solas sobre la nueva cuota.</p>
            </div>
            <x-slot:footer>
                <button type="button" class="btn-secondary" x-on:click="open = false">Cancelar</button>
                <button type="button" wire:click="applyIncrease" wire:confirm="¿Aplicar el aumento?" class="btn-primary">Aplicar aumento</button>
            </x-slot:footer>
        </x-modal>
    @endcan
</div>

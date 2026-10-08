<div>
    <x-page-header :title="activity_label(true)" subtitle="Grupos, horarios, profesores, cupos y cuotas">
        <x-slot:actions>
            @can('actividades.gestionar')
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
</div>

<div class="space-y-6">
    <div>
        <h1 class="font-display text-2xl font-bold text-slate-900">Actividades</h1>
        <p class="text-sm text-slate-500">Inscribite o date de baja. La cuota de la actividad se suma a tu cuenta mensual.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        @foreach ($activities as $activity)
            @php($enrollmentId = $mine[$activity->id] ?? null)
            @php($spots = $activity->availableSpots())
            <div class="card flex flex-col p-5" wire:key="pa-{{ $activity->id }}">
                <div class="flex items-start justify-between gap-2">
                    <h2 class="font-semibold text-slate-900">{{ $activity->name }}</h2>
                    @if ($enrollmentId)
                        <x-badge color="green">Inscripto</x-badge>
                    @elseif ($spots !== null)
                        <x-badge :color="$spots > 0 ? 'blue' : 'red'">{{ $spots > 0 ? $spots.' cupos' : 'Sin cupo' }}</x-badge>
                    @endif
                </div>
                @if ($activity->summary)<p class="mt-1 text-sm text-slate-600">{{ $activity->summary }}</p>@endif
                <ul class="mt-3 space-y-1 text-xs text-slate-500">
                    @foreach ($activity->schedules as $schedule)
                        <li class="flex items-center gap-1.5"><x-icon name="clock" class="size-3.5" /> {{ $schedule->dayName() }} {{ $schedule->timeRange() }}{{ $schedule->location ? ' · '.$schedule->location : '' }}</li>
                    @endforeach
                </ul>
                <div class="mt-auto flex items-center justify-between gap-2 pt-4">
                    <p class="font-display font-bold text-slate-900">{{ ! $activity->allows_enrollment ? 'Con plan' : ((float) $activity->monthly_fee > 0 ? money($activity->monthly_fee).' /mes' : 'Sin cargo') }}</p>
                    @if (! $activity->allows_enrollment)
                        <a href="{{ uses_gym() ? route('portal.plans') : '#' }}" wire:navigate class="text-right text-xs text-slate-500">
                            Incluida en {{ $activity->plans->where('is_active', true)->pluck('name')->implode(', ') ?: 'planes del gimnasio' }}
                        </a>
                    @elseif ($enrollmentId)
                        <button type="button" wire:click="unenroll({{ $enrollmentId }})" wire:confirm="¿Darte de baja de {{ $activity->name }}?" class="btn-secondary btn-sm">Darme de baja</button>
                    @elseif ($member->isActive() && ($spots === null || $spots > 0) && $activity->acceptsAge($member->age()))
                        <button type="button" wire:click="enroll({{ $activity->id }})" wire:confirm="¿Confirmás la inscripción a {{ $activity->name }}{{ (float) $activity->monthly_fee > 0 ? ' por '.money($activity->monthly_fee).' mensuales' : '' }}?" class="btn-primary btn-sm">Inscribirme</button>
                    @elseif (! $activity->acceptsAge($member->age()))
                        <span class="text-xs text-slate-400">No disponible para tu edad</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>

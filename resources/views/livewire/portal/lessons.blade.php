<div class="space-y-6">
    <h1 class="font-display text-2xl font-bold text-slate-900">Mis clases</h1>

    @if ($packs->isNotEmpty())
        <section class="grid gap-3 sm:grid-cols-2">
            @foreach ($packs as $pack)
                @php($remaining = $pack->visitsRemaining())
                <div class="card p-4" wire:key="pack-{{ $pack->id }}">
                    <p class="font-medium text-slate-900">{{ $pack->plan->name }}</p>
                    <p class="text-sm text-slate-500">{{ $pack->plan->instructor?->name }} · vence {{ $pack->end_date->format('d/m/Y') }}</p>
                    <p class="mt-2 text-sm text-slate-700">
                        @if ($remaining === null)
                            Clases ilimitadas
                        @else
                            Te quedan <strong @class(['text-red-600' => $remaining === 0])>{{ $remaining }}</strong> de {{ $pack->plan->visit_limit }} clases {{ \App\Models\Plan::VISIT_PERIODS[$pack->plan->visit_period] ?? '' }}
                        @endif
                    </p>
                </div>
            @endforeach
        </section>
    @endif

    <section>
        <h2 class="mb-3 font-semibold text-slate-900">Próximas clases</h2>
        <div class="space-y-2">
            @forelse ($upcoming as $lesson)
                <div class="card flex items-center justify-between gap-3 p-4" wire:key="up-{{ $lesson->id }}">
                    <div class="min-w-0">
                        <p class="font-medium text-slate-800">{{ ucfirst($lesson->date->translatedFormat('l j \d\e F')) }} · {{ $lesson->timeRange() }}</p>
                        <p class="truncate text-sm text-slate-500">{{ $lesson->facility->name }} · {{ $lesson->instructor->name }}</p>
                    </div>
                    <x-icon name="calendar" class="size-5 shrink-0 text-brand-600" />
                </div>
            @empty
                <div class="card p-5 text-sm text-slate-500">No tenés clases programadas.</div>
            @endforelse
        </div>
    </section>

    @if ($history->isNotEmpty())
        <section>
            <h2 class="mb-3 font-semibold text-slate-900">Historial</h2>
            <div class="card overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Fecha</th><th>Horario</th><th>Profesor</th><th>Clase</th><th>Asistencia</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($history as $lesson)
                            <tr>
                                <td>{{ $lesson->date->format('d/m/Y') }}</td>
                                <td>{{ $lesson->timeRange() }}</td>
                                <td>{{ $lesson->instructor->name }}</td>
                                <td><x-badge :status="$lesson->status" /></td>
                                <td><x-badge :status="\App\Enums\AttendanceStatus::from($lesson->pivot->attendance)" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>

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
        <div class="space-y-3">
            @forelse ($upcoming as $lesson)
                @php($cancelled = $lesson->status === \App\Enums\LessonStatus::Cancelled)
                @php($noticed = $lesson->pivot->attendance === \App\Enums\AttendanceStatus::Notified->value)
                <div wire:key="up-{{ $lesson->id }}" @class([
                    'card p-4',
                    'ring-1 ring-red-200 bg-red-50/40' => $cancelled,
                    'ring-1 ring-sky-200' => $noticed && ! $cancelled,
                ])>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p @class(['font-medium text-slate-800', 'line-through' => $cancelled])>{{ $lesson->activity?->name ?? 'Clase' }}</p>
                            <p class="text-sm text-slate-500">{{ ucfirst($lesson->date->translatedFormat('l j \d\e F')) }} · {{ $lesson->timeRange() }}</p>
                            <p class="truncate text-sm text-slate-500">{{ collect([$lesson->placeName(), $lesson->instructor->name])->filter()->implode(' · ') }}</p>
                        </div>
                        <x-icon name="calendar" class="size-5 shrink-0 text-brand-600" />
                    </div>

                    @if ($cancelled)
                        <p class="mt-3 rounded-lg bg-red-100 px-3 py-2 text-sm font-medium text-red-800">No hay clase{{ $lesson->cancel_reason ? ': '.$lesson->cancel_reason : '' }}</p>
                    @elseif ($noticed)
                        <div class="mt-3 flex items-center justify-between gap-3 rounded-lg bg-sky-50 px-3 py-2 text-sm text-sky-800">
                            <span>Avisaste que no vas{{ $lesson->pivot->notice_reason ? ' ('.$lesson->pivot->notice_reason.')' : '' }}.</span>
                            @unless ($lesson->startsAt()->isPast())
                                <button type="button" wire:click="withdrawAbsence({{ $lesson->id }})" class="shrink-0 font-semibold underline">Al final voy</button>
                            @endunless
                        </div>
                    @elseif ($canNotify && ! $lesson->startsAt()->isPast())
                        <button type="button" wire:click="askAbsence({{ $lesson->id }})" class="btn-secondary mt-3 w-full sm:w-auto">No voy a esta clase</button>
                    @endif
                </div>
            @empty
                <div class="card p-5 text-sm text-slate-500">No tenés clases programadas.</div>
            @endforelse
        </div>
    </section>

    @if ($history->isNotEmpty())
        <section>
            <h2 class="mb-3 font-semibold text-slate-900">Historial</h2>
            <div class="card divide-y divide-slate-100">
                @foreach ($history as $lesson)
                    <div class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-slate-800">{{ $lesson->activity?->name ?? $lesson->instructor->name }}</p>
                            <p class="text-slate-500">{{ $lesson->date->format('d/m/Y') }} · {{ $lesson->timeRange() }}</p>
                        </div>
                        <x-badge :status="\App\Enums\AttendanceStatus::from($lesson->pivot->attendance)" />
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Aviso de ausencia --}}
    <div x-data="{ open: @entangle('noticeLessonId').live }" x-show="open" x-cloak class="fixed inset-0 z-50">
        <div class="absolute inset-0 bg-slate-900/50" x-on:click="open = null"></div>
        <form wire:submit="confirmAbsence" class="absolute inset-x-0 bottom-0 rounded-t-2xl bg-white p-5 pb-[calc(1.25rem+env(safe-area-inset-bottom))] shadow-xl sm:inset-auto sm:top-1/2 sm:left-1/2 sm:w-full sm:max-w-md sm:-translate-x-1/2 sm:-translate-y-1/2 sm:rounded-2xl">
            <h3 class="text-base font-semibold text-slate-900">Avisar que no voy</h3>
            <p class="mt-1 text-sm text-slate-500">Le avisamos a tu profesor. Podés deshacerlo hasta que empiece la clase.</p>
            <label for="reason" class="form-label mt-4">Motivo (opcional)</label>
            <input id="reason" wire:model="reason" class="form-input" placeholder="Ej.: estoy enferma, tengo un examen…" maxlength="200">
            @error('reason')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            <div class="mt-5 grid grid-cols-2 gap-2">
                <button type="button" class="btn-secondary" x-on:click="open = null">Cancelar</button>
                <button type="submit" class="btn-primary">Avisar</button>
            </div>
        </form>
    </div>
</div>

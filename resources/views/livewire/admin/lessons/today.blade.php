<div class="mx-auto max-w-2xl">
    {{-- Encabezado con navegación por día --}}
    <div class="mb-4 flex items-center justify-between gap-2">
        <button type="button" wire:click="shift(-1)" class="btn-secondary px-2.5" aria-label="Día anterior"><x-icon name="chevron-left" class="size-5" /></button>
        <div class="min-w-0 text-center">
            <h1 class="font-display text-xl font-bold text-slate-900">{{ $day->isToday() ? 'Hoy' : ($day->isTomorrow() ? 'Mañana' : ($day->isYesterday() ? 'Ayer' : ucfirst($day->translatedFormat('l')))) }}</h1>
            <p class="text-sm text-slate-500">{{ $day->translatedFormat('j \d\e F') }}</p>
        </div>
        <button type="button" wire:click="shift(1)" class="btn-secondary px-2.5" aria-label="Día siguiente"><x-icon name="chevron-right" class="size-5" /></button>
    </div>

    <x-push-toggle compact class="mb-4" />

    {{-- Accesos rápidos --}}
    @if ($canCollect || $cashPending !== null)
        <div class="mb-4 grid grid-cols-2 gap-3">
            @if ($canCollect)
                <a href="{{ route('admin.lessons.collect') }}" wire:navigate class="card flex items-center gap-3 p-4 hover:shadow-md">
                    <span class="grid size-10 place-items-center rounded-full bg-emerald-50 text-emerald-700"><x-icon name="banknotes" class="size-5" /></span>
                    <span class="font-medium text-slate-800">Cobrar</span>
                </a>
            @endif
            @if ($cashPending !== null)
                <a href="{{ route('admin.lessons.cash') }}" wire:navigate class="card flex items-center gap-3 p-4 hover:shadow-md">
                    <span class="grid size-10 place-items-center rounded-full bg-amber-50 text-amber-700"><x-icon name="credit-card" class="size-5" /></span>
                    <span class="min-w-0">
                        <span class="block text-xs text-slate-500">Efectivo a rendir</span>
                        <span class="block font-semibold tabular-nums text-slate-900">{{ money($cashPending) }}</span>
                    </span>
                </a>
            @endif
        </div>
    @endif

    <div class="space-y-3">
        @forelse ($lessons as $lesson)
            @php
                $open = $openLessonId === $lesson->id;
                $noticedCount = $lesson->students->where('pivot.attendance', \App\Enums\AttendanceStatus::Notified->value)->count();
            @endphp
            <div wire:key="t-{{ $lesson->id }}" @class([
                'card overflow-hidden',
                'opacity-70' => $lesson->status === \App\Enums\LessonStatus::Cancelled,
            ])>
                <button type="button" wire:click="toggle({{ $lesson->id }})" class="flex w-full items-center gap-3 p-4 text-left">
                    <div class="w-14 shrink-0 text-center">
                        <p class="font-display text-lg font-bold tabular-nums text-slate-900">{{ substr($lesson->start_time, 0, 5) }}</p>
                        <p class="text-xs tabular-nums text-slate-400">{{ substr($lesson->end_time, 0, 5) }}</p>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold text-slate-900">{{ $lesson->title() }}</p>
                        <p class="truncate text-sm text-slate-500">{{ collect([$lesson->placeName(), $seesAll ? $lesson->instructor->name : null])->filter()->implode(' · ') }}</p>
                        <p class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                            <x-badge :status="$lesson->status" />
                            <span class="text-slate-500">{{ $lesson->students->count() }} alumnos</span>
                            @if ($noticedCount)
                                <span class="font-medium text-sky-700">{{ $noticedCount }} avisaron que faltan</span>
                            @endif
                        </p>
                    </div>
                    <x-icon name="chevron-down" @class(['size-5 shrink-0 text-slate-400 transition', 'rotate-180' => $open]) />
                </button>

                @if ($open)
                    <div class="border-t border-slate-100">
                        @if ($lesson->status === \App\Enums\LessonStatus::Cancelled)
                            <p class="p-4 text-sm text-slate-600">Clase suspendida{{ $lesson->cancel_reason ? ': '.$lesson->cancel_reason : '' }}.</p>
                        @else
                            @if ($lesson->isScheduled() && $lesson->students->isNotEmpty())
                                @php($presentCount = collect($attendance[$lesson->id] ?? [])->filter(fn ($v) => $v === 'presente')->count())
                                <div class="flex items-center justify-between gap-2 bg-slate-50 px-4 py-2 text-sm">
                                    <span class="text-slate-600">Presentes <strong class="text-slate-900">{{ $presentCount }}</strong> de {{ $lesson->students->count() }}</span>
                                    <span class="flex gap-3">
                                        <button type="button" wire:click="setAll({{ $lesson->id }}, true)" class="font-medium text-brand-700">Todos</button>
                                        <button type="button" wire:click="setAll({{ $lesson->id }}, false)" class="font-medium text-slate-500">Ninguno</button>
                                    </span>
                                </div>
                            @endif
                            <ul class="divide-y divide-slate-100">
                                @forelse ($lesson->students as $student)
                                    @php($value = $attendance[$lesson->id][$student->id] ?? 'presente')
                                    @php($noticed = $student->pivot->notice_at !== null)
                                    @if ($lesson->isScheduled())
                                        {{-- Todos presentes por defecto: se destilda al que no vino. --}}
                                        <li wire:key="t-{{ $lesson->id }}-{{ $student->id }}">
                                            <label class="flex cursor-pointer items-center gap-3 px-4 py-3">
                                                <input type="checkbox" @checked($value === 'presente')
                                                       wire:click="setAttendance({{ $lesson->id }}, {{ $student->id }}, '{{ $value === 'presente' ? 'ausente' : 'presente' }}')"
                                                       class="size-6 shrink-0 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                                <span class="min-w-0 flex-1">
                                                    <span @class(['block truncate font-medium', 'text-slate-800' => $value === 'presente', 'text-slate-400 line-through' => $value !== 'presente'])>{{ $student->sortableName() }}</span>
                                                    @if ($noticed)
                                                        <span class="block truncate text-xs text-sky-700">Avisó que falta{{ $student->pivot->notice_reason ? ': '.$student->pivot->notice_reason : '' }}</span>
                                                    @endif
                                                </span>
                                                <span @class(['text-xs font-semibold', 'text-emerald-700' => $value === 'presente', 'text-red-600' => $value !== 'presente'])>{{ $value === 'presente' ? 'Presente' : 'Ausente' }}</span>
                                            </label>
                                        </li>
                                    @else
                                        <li class="flex items-center gap-3 px-4 py-3" wire:key="t-{{ $lesson->id }}-{{ $student->id }}">
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate font-medium text-slate-800">{{ $student->sortableName() }}</p>
                                                @if ($noticed)
                                                    <p class="truncate text-xs text-sky-700">Avisó que falta{{ $student->pivot->notice_reason ? ': '.$student->pivot->notice_reason : '' }}</p>
                                                @endif
                                            </div>
                                            <x-badge :status="\App\Enums\AttendanceStatus::from($student->pivot->attendance)" />
                                        </li>
                                    @endif
                                @empty
                                    <li class="p-4 text-sm text-slate-500">Sin alumnos.</li>
                                @endforelse
                            </ul>
                            <div class="flex flex-wrap gap-2 border-t border-slate-100 p-4">
                                @if ($lesson->isScheduled())
                                    @if (! $lesson->date->isFuture())
                                        <button type="button" wire:click="markGiven({{ $lesson->id }})" class="btn-primary flex-1"><x-icon name="check" class="size-5" /> Guardar asistencia</button>
                                    @endif
                                    @unless ($lesson->startsAt()->isPast())
                                        <button type="button" wire:click="askSuspend({{ $lesson->id }})" class="btn-secondary flex-1 text-red-600">Suspender</button>
                                    @endunless
                                @elseif ($lesson->status === \App\Enums\LessonStatus::Given)
                                    <button type="button" wire:click="reopen({{ $lesson->id }})" wire:confirm="¿Corregir la asistencia de esta clase?" class="btn-secondary flex-1">Corregir asistencia</button>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <div class="card"><x-empty-state icon="calendar" title="No hay clases este día" /></div>
        @endforelse
    </div>

    @if ($seesAll && $scheduledCount > 0 && ($day->isToday() || $day->isFuture()))
        <button type="button" wire:click="askSuspend" class="btn-ghost mt-6 w-full text-red-600"><x-icon name="ban" class="size-5" /> No hay clase {{ $day->isToday() ? 'hoy' : 'este día' }} para ningún grupo</button>
    @endif

    <p class="mt-6 text-center text-sm"><a href="{{ route('admin.lessons') }}" wire:navigate class="font-medium text-brand-700">Ver la agenda completa</a></p>

    {{-- Suspender --}}
    <div x-data="{ open: @entangle('showSuspend').live }" x-show="open" x-cloak class="fixed inset-0 z-50">
        <div class="absolute inset-0 bg-slate-900/50" x-on:click="open = false"></div>
        <form wire:submit="confirmSuspend" class="absolute inset-x-0 bottom-0 rounded-t-2xl bg-white p-5 pb-[calc(1.25rem+env(safe-area-inset-bottom))] shadow-xl sm:inset-auto sm:top-1/2 sm:left-1/2 sm:w-full sm:max-w-md sm:-translate-x-1/2 sm:-translate-y-1/2 sm:rounded-2xl">
            <h3 class="text-base font-semibold text-slate-900">{{ $suspendLessonId ? 'Suspender la clase' : 'Suspender todas las clases del día' }}</h3>
            <p class="mt-1 text-sm text-slate-500">Se avisa a los alumnos con una notificación en el teléfono y por correo.</p>
            <label for="suspendReason" class="form-label mt-4">Motivo (opcional)</label>
            <input id="suspendReason" wire:model="suspendReason" class="form-input" placeholder="Ej.: lluvia, feriado, profesor enfermo…" maxlength="200">
            <div class="mt-5 grid grid-cols-2 gap-2">
                <button type="button" class="btn-secondary" x-on:click="open = false">Volver</button>
                <button type="submit" class="btn-primary bg-red-600 hover:bg-red-700">Suspender</button>
            </div>
        </form>
    </div>
</div>

<div class="space-y-6">
    <div>
        <p class="text-sm text-slate-500">¡Hola!</p>
        <h1 class="font-display text-2xl font-bold text-slate-900">{{ $member->first_name }} {{ $member->last_name }}</h1>
        <p class="text-sm text-slate-500">Socio N° {{ $member->member_number }} · {{ $member->category->name }}</p>
    </div>

    @unless ($member->isActive())
        <div class="flex gap-3 rounded-xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-200">
            <x-icon name="warning" class="size-5 shrink-0" />
            <p>Tu condición de socio es <strong>{{ mb_strtolower($member->status->label()) }}</strong>. Comunicate con la secretaría del club.</p>
        </div>
    @endunless

    @foreach ($suspended as $lesson)
        <div class="flex gap-3 rounded-xl bg-red-50 p-4 text-sm text-red-900 ring-1 ring-red-200" wire:key="susp-{{ $lesson->id }}">
            <x-icon name="ban" class="size-5 shrink-0" />
            <p><strong>No hay clase</strong> {{ $lesson->date->isToday() ? 'hoy' : ($lesson->date->isTomorrow() ? 'mañana' : 'el '.$lesson->date->translatedFormat('l j/m')) }} de {{ $lesson->activity?->name ?? 'tu clase' }} ({{ substr($lesson->start_time, 0, 5) }}){{ $lesson->cancel_reason ? ': '.$lesson->cancel_reason : '' }}.</p>
        </div>
    @endforeach

    @foreach ($unreadMessages as $message)
        <a href="{{ route('portal.messages') }}" wire:navigate class="flex gap-3 rounded-xl bg-sky-50 p-4 text-sm text-sky-900 ring-1 ring-sky-200" wire:key="msg-{{ $message->id }}">
            <x-icon name="chat" class="size-5 shrink-0" />
            <span class="min-w-0"><strong class="block">{{ $message->title }}</strong><span class="line-clamp-2">{{ $message->body }}</span></span>
        </a>
    @endforeach

    <x-push-toggle compact />

    @if ($nextLesson)
        @php($noticed = $nextLesson->pivot->attendance === \App\Enums\AttendanceStatus::Notified->value)
        <a href="{{ route('portal.lessons') }}" wire:navigate class="card flex items-center justify-between gap-4 p-5 transition hover:shadow-md">
            <div class="min-w-0">
                <p class="text-sm text-slate-500">Tu próxima clase</p>
                <p class="font-display text-xl font-bold text-slate-900">{{ $nextLesson->activity?->name ?? 'Clase' }}</p>
                <p class="text-sm text-slate-500">{{ ucfirst($nextLesson->date->isToday() ? 'hoy' : ($nextLesson->date->isTomorrow() ? 'mañana' : $nextLesson->date->translatedFormat('l j/m'))) }} · {{ $nextLesson->timeRange() }}{{ $nextLesson->placeName() ? ' · '.$nextLesson->placeName() : '' }}</p>
                @if ($noticed)
                    <p class="mt-1 text-sm font-medium text-sky-700">Avisaste que no vas</p>
                @endif
            </div>
            <span class="shrink-0 text-sm font-medium text-brand-700">{{ $noticed ? 'Ver' : '¿No vas?' }}</span>
        </a>
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <a href="{{ route('portal.fees') }}" wire:navigate class="card block p-5 transition hover:shadow-md {{ bccomp($balance, '0', 2) > 0 ? 'ring-1 ring-red-200' : '' }}">
            <p class="text-sm text-slate-500">Saldo de tu cuenta</p>
            <p class="mt-1 font-display text-3xl font-bold tabular-nums {{ bccomp($balance, '0', 2) > 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ money($balance) }}</p>
            <p class="mt-1 text-sm {{ $overdue ? 'font-medium text-red-600' : 'text-slate-500' }}">
                @if ($overdue)
                    {{ $overdue }} {{ $overdue === 1 ? 'cuota vencida' : 'cuotas vencidas' }}
                @elseif ($nextFee)
                    Próximo vencimiento: {{ $nextFee->due_date->format('d/m/Y') }}
                @else
                    ¡Estás al día!
                @endif
            </p>
            @if (bccomp($balance, '0', 2) > 0 && setting('payments.receipts_enabled', true))
                <span class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-brand-700">¿Transferiste? Informá el pago <x-icon name="arrow-right" class="size-4" /></span>
            @endif
        </a>
        <a href="{{ route('portal.card') }}" wire:navigate class="relative block overflow-hidden rounded-xl bg-gradient-to-br from-brand-600 to-brand-900 p-5 text-white shadow-sm transition hover:shadow-md">
            <x-icon name="qr" class="absolute -right-4 -bottom-4 size-28 text-white/10" />
            <p class="text-sm text-white/70">Carnet digital</p>
            <p class="mt-1 font-display text-xl font-semibold">Mostralo en el ingreso</p>
            <p class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-accent-400">Ver carnet <x-icon name="arrow-right" class="size-4" /></p>
        </a>
    </div>

    @if (uses_gym())
        <a href="{{ route('portal.plans') }}" wire:navigate class="card flex items-center justify-between gap-4 p-5 transition hover:shadow-md">
            @if ($subscription)
                <div>
                    <p class="text-sm text-slate-500">Tu plan</p>
                    <p class="font-display text-xl font-bold text-slate-900">{{ $subscription->plan->name }}</p>
                    <p class="text-sm text-slate-500">Vence el {{ $subscription->end_date->format('d/m/Y') }}@if ($subscription->plan->visit_limit) · te quedan {{ $subscription->visitsRemaining() }} visitas @endif</p>
                </div>
                <span class="rounded-full {{ $subscription->daysLeft() <= 5 ? 'bg-red-50 text-red-700' : 'bg-brand-50 text-brand-700' }} px-3 py-1 text-sm font-semibold whitespace-nowrap">{{ $subscription->daysLeft() }} días</span>
            @else
                <div>
                    <p class="text-sm text-slate-500">Tu plan</p>
                    <p class="font-display text-xl font-bold text-slate-900">{{ $pendingPlan ? 'Pendiente de pago' : 'Sin plan vigente' }}</p>
                    <p class="text-sm text-slate-500">{{ $pendingPlan ? 'Se activa cuando se registre el pago.' : 'Contratá un plan para entrenar.' }}</p>
                </div>
                <x-icon name="arrow-right" class="size-5 text-brand-600" />
            @endif
        </a>
    @endif

    @if ($announcements->isNotEmpty())
        <section>
            <h2 class="mb-3 font-semibold text-slate-900">Avisos del club</h2>
            <div class="space-y-3">
                @foreach ($announcements as $a)
                    <div @class(['rounded-xl p-4 ring-1', 'bg-sky-50 ring-sky-200' => $a->level === 'info', 'bg-emerald-50 ring-emerald-200' => $a->level === 'success', 'bg-amber-50 ring-amber-200' => $a->level === 'warning'])>
                        <p class="font-medium text-slate-900">{{ $a->title }}</p>
                        <p class="mt-1 text-sm whitespace-pre-line text-slate-700">{{ $a->body }}</p>
                        <p class="mt-2 text-xs text-slate-500">{{ $a->published_at->format('d/m/Y') }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <div class="grid gap-6 md:grid-cols-2">
        <section class="card">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
                <h2 class="font-semibold text-slate-900">Mis actividades</h2>
                <a href="{{ route('portal.activities') }}" wire:navigate class="text-sm font-medium text-brand-700">Ver más</a>
            </div>
            <ul class="divide-y divide-slate-100">
                @forelse ($activities as $activity)
                    <li class="px-5 py-3">
                        <p class="font-medium text-slate-800">{{ $activity->name }}</p>
                        <p class="text-xs text-slate-500">{{ $activity->schedules->map(fn ($s) => mb_substr($s->dayName(), 0, 3).' '.substr($s->start_time, 0, 5))->implode(' · ') }}</p>
                    </li>
                @empty
                    <li class="px-5 py-6 text-center text-sm text-slate-500">No estás inscripto en actividades.</li>
                @endforelse
            </ul>
        </section>
        <section class="card">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
                <h2 class="font-semibold text-slate-900">Próximas reservas</h2>
                <a href="{{ route('portal.reservations') }}" wire:navigate class="text-sm font-medium text-brand-700">Reservar</a>
            </div>
            <ul class="divide-y divide-slate-100">
                @forelse ($reservations as $reservation)
                    <li class="flex items-center justify-between px-5 py-3">
                        <div>
                            <p class="font-medium text-slate-800">{{ $reservation->facility->name }}</p>
                            <p class="text-xs text-slate-500">{{ ucfirst($reservation->date->translatedFormat('l j/m')) }}</p>
                        </div>
                        <span class="text-sm text-slate-600 tabular-nums">{{ $reservation->timeRange() }}</span>
                    </li>
                @empty
                    <li class="px-5 py-6 text-center text-sm text-slate-500">No tenés reservas próximas.</li>
                @endforelse
            </ul>
        </section>
    </div>
</div>

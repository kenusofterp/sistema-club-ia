<div class="space-y-6">
    <h1 class="font-display text-2xl font-bold text-slate-900">Mi plan</h1>

    @forelse ($subscriptions as $s)
        @php($current = $s->isCurrent())
        <div @class(['card overflow-hidden', 'ring-2 ring-brand-500' => $current]) wire:key="ms-{{ $s->id }}">
            <div @class(['flex flex-wrap items-center justify-between gap-3 px-5 py-4', 'bg-brand-600 text-white' => $current, 'bg-slate-50' => ! $current])>
                <div>
                    <p class="font-display text-lg font-bold">{{ $s->plan->name }}</p>
                    <p @class(['text-sm', 'text-white/80' => $current, 'text-slate-500' => ! $current])>{{ $s->periodLabel() }}</p>
                </div>
                @if ($current)
                    <span class="rounded-full bg-white/15 px-3 py-1 text-sm font-semibold">{{ $s->daysLeft() }} días restantes</span>
                @elseif ($s->status === \App\Enums\SubscriptionStatus::Pending)
                    <x-badge :status="$s->status" />
                @else
                    <x-badge color="blue">Comienza el {{ $s->start_date->format('d/m') }}</x-badge>
                @endif
            </div>
            <div class="grid gap-4 p-5 sm:grid-cols-2">
                <ul class="space-y-2 text-sm text-slate-600">
                    <li class="flex gap-2"><x-icon name="key" class="size-4 shrink-0 text-brand-600" /> {{ $s->plan->access_type->label() }}</li>
                    @forelse ($s->plan->windowsLabels() as $label)
                        <li class="flex gap-2"><x-icon name="clock" class="size-4 shrink-0 text-brand-600" /> {{ $label }}</li>
                    @empty
                        <li class="flex gap-2"><x-icon name="clock" class="size-4 shrink-0 text-brand-600" /> Todo el horario</li>
                    @endforelse
                    @if ($s->plan->visit_limit)
                        <li class="flex gap-2"><x-icon name="refresh" class="size-4 shrink-0 text-brand-600" />
                            {{ $s->plan->visitLimitLabel() }} @if ($current)— <strong class="text-slate-900">te quedan {{ $s->visitsRemaining() }}</strong>@endif
                        </li>
                    @endif
                </ul>
                @if ($s->plan->activities->isNotEmpty())
                    <div class="rounded-lg bg-slate-50 p-3 text-sm">
                        <p class="mb-2 font-medium text-slate-800">Clases incluidas</p>
                        @foreach ($s->plan->activities as $activity)
                            <p class="text-slate-700">{{ $activity->name }}</p>
                            <p class="mb-1.5 text-xs text-slate-500">{{ $activity->schedules->map(fn ($sc) => mb_substr($sc->dayName(), 0, 3).' '.substr($sc->start_time, 0, 5))->implode(' · ') }}</p>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 px-5 py-3 text-sm">
                <label class="flex items-center gap-2 text-slate-600">
                    <input type="checkbox" @checked($s->auto_renew) wire:click="toggleAutoRenew({{ $s->id }})" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Renovar automáticamente
                </label>
                @if ($s->status === \App\Enums\SubscriptionStatus::Pending)
                    <div class="flex gap-2">
                        <a href="{{ route('portal.fees') }}" wire:navigate class="btn-primary btn-sm">Ver cómo pagar</a>
                        <button type="button" wire:click="cancelPending({{ $s->id }})" wire:confirm="¿Cancelar esta solicitud?" class="btn-ghost btn-sm text-red-600">Cancelar</button>
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="card"><x-empty-state icon="id-card" title="No tenés un plan vigente" description="Elegí uno de los planes disponibles para empezar a entrenar." /></div>
    @endforelse

    @if ($plans->isNotEmpty())
        <section>
            <h2 class="mb-3 font-semibold text-slate-900">Planes disponibles</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($plans as $plan)
                    <div @class(['card relative flex flex-col p-5', 'ring-2 ring-accent-500' => $plan->is_featured])>
                        @if ($plan->is_featured)
                            <span class="absolute -top-2.5 right-4 rounded-full bg-accent-500 px-2.5 py-0.5 text-xs font-semibold text-slate-900">El más elegido</span>
                        @endif
                        <p class="font-semibold text-slate-900">{{ $plan->name }}</p>
                        @if ($plan->description)<p class="text-sm text-slate-500">{{ $plan->description }}</p>@endif
                        <p class="mt-2"><span class="font-display text-2xl font-bold">{{ money($plan->price) }}</span> <span class="text-sm text-slate-500">/ {{ $plan->durationLabel() }}</span></p>
                        <ul class="mt-3 space-y-1 text-sm text-slate-600">
                            <li>· {{ $plan->access_type->label() }}</li>
                            <li>· {{ $plan->visitLimitLabel() }}</li>
                            @foreach ($plan->windowsLabels() as $label)<li>· {{ $label }}</li>@endforeach
                            @if ($plan->activities->isNotEmpty())<li>· {{ $plan->activities->pluck('name')->implode(', ') }}</li>@endif
                        </ul>
                        <button type="button" wire:click="purchase({{ $plan->id }})" wire:confirm="¿Contratar {{ $plan->name }} por {{ money($plan->price) }}?" class="btn-primary mt-4" @disabled(! $member->isActive())>Contratar</button>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($history->isNotEmpty())
        <section class="card divide-y divide-slate-100">
            <h2 class="px-5 py-3 font-semibold text-slate-900">Planes anteriores</h2>
            @foreach ($history as $s)
                <div class="flex items-center justify-between px-5 py-3 text-sm">
                    <span>{{ $s->plan->name }} <span class="text-slate-400">· {{ $s->periodLabel() }}</span></span>
                    <x-badge :status="$s->status" />
                </div>
            @endforeach
        </section>
    @endif
</div>

<div class="space-y-6">
    <div>
        <h1 class="font-display text-2xl font-bold text-slate-900">Torneos</h1>
        <p class="text-sm text-slate-500">Anotate en los torneos de tu {{ mb_strtolower(activity_label()) }}. El costo se suma a tu cuenta y lo pagás como la cuota.</p>
    </div>

    @if ($available->isNotEmpty())
        <section>
            <h2 class="mb-2 font-semibold text-slate-900">Para anotarte</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($available as $tournament)
                    <div class="card flex flex-col p-5" wire:key="av-{{ $tournament->id }}">
                        <h3 class="font-semibold text-slate-900">{{ $tournament->name }}</h3>
                        <ul class="mt-2 space-y-1 text-sm text-slate-600">
                            @foreach ($tournament->days as $day)
                                <li class="flex items-start gap-1.5"><x-icon name="calendar" class="mt-0.5 size-4 shrink-0 text-slate-400" /> <span>{{ ucfirst($day->date->translatedFormat('l d/m')) }} · {{ $day->activities->pluck('name')->implode(', ') }}{{ $day->notes ? ' · '.$day->notes : '' }}</span></li>
                            @endforeach
                        </ul>
                        @if ($tournament->description)<p class="mt-2 whitespace-pre-line text-sm text-slate-500">{{ $tournament->description }}</p>@endif
                        <div class="mt-auto flex items-center justify-between gap-2 pt-4">
                            <div>
                                <p class="font-display font-bold text-slate-900">{{ (float) $tournament->price > 0 ? money($tournament->price) : 'Sin cargo' }}</p>
                                <p class="text-xs text-slate-500">Pagar hasta el {{ $tournament->payment_due_date->format('d/m') }}</p>
                            </div>
                            <button type="button" wire:click="join({{ $tournament->id }})" wire:confirm="¿Te anotás en {{ $tournament->name }}{{ (float) $tournament->price > 0 ? ' por '.money($tournament->price) : '' }}?" class="btn-primary btn-sm">Participar</button>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section>
        <h2 class="mb-2 font-semibold text-slate-900">Mis torneos</h2>
        <div class="card divide-y divide-slate-100">
            @forelse ($mine as $tournament)
                @php($fee = $tournament->fees->first())
                <div class="flex items-center justify-between gap-3 p-4" wire:key="mine-{{ $tournament->id }}">
                    <div class="min-w-0">
                        <p class="font-medium text-slate-900">{{ $tournament->name }}</p>
                        <p class="text-xs text-slate-500">
                            {{ $tournament->startsOn()?->format('d/m/Y') }}@if ($tournament->days->count() > 1) al {{ $tournament->endsOn()?->format('d/m/Y') }}@endif
                            @if ($tournament->isCancelled()) · <span class="text-red-600">Cancelado</span>@endif
                        </p>
                    </div>
                    <div class="shrink-0 text-right">
                        @if ($fee)
                            <x-badge :status="$fee->status" />
                            @if ($fee->isOpen())
                                <a href="{{ route('portal.fees') }}" wire:navigate class="mt-1 block text-xs font-medium text-brand-700">Pagar {{ money($fee->balance()) }}</a>
                            @endif
                        @else
                            <x-badge color="green">Anotado</x-badge>
                        @endif
                    </div>
                </div>
            @empty
                <p class="p-6 text-center text-sm text-slate-500">Todavía no participaste de ningún torneo.</p>
            @endforelse
        </div>
    </section>
</div>

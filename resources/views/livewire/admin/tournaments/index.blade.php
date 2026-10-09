<div>
    <x-page-header title="Torneos" subtitle="Costo, fecha máxima de pago, participantes y quién debe">
        <x-slot:actions>
            <a href="{{ route('admin.tournaments.create') }}" wire:navigate class="btn-primary"><x-icon name="plus" class="size-4" /> Nuevo torneo</a>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 inline-flex rounded-lg bg-slate-100 p-1 text-sm">
        @foreach (['proximos' => 'Próximos', 'anteriores' => 'Anteriores', 'cancelados' => 'Cancelados'] as $value => $label)
            <button type="button" wire:click="$set('filter', '{{ $value }}')" @class(['rounded-md px-3 py-1.5 font-medium', 'bg-white text-slate-900 shadow-sm' => $filter === $value, 'text-slate-500' => $filter !== $value])>{{ $label }}</button>
        @endforeach
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($tournaments as $tournament)
            @php($owed = bcsub((string) ($tournament->owed_total ?? 0), (string) ($tournament->owed_paid ?? 0), 2))
            <a href="{{ route('admin.tournaments.show', $tournament) }}" wire:navigate class="card flex flex-col p-5 hover:shadow-md" wire:key="t-{{ $tournament->id }}">
                <div class="flex items-start justify-between gap-2">
                    <h3 class="font-semibold text-slate-900">{{ $tournament->name }}</h3>
                    @if ($tournament->isCancelled())
                        <x-badge color="red">Cancelado</x-badge>
                    @else
                        <x-badge :color="$tournament->is_open ? 'blue' : 'gray'">{{ $tournament->is_open ? 'Libre' : 'Por invitación' }}</x-badge>
                    @endif
                </div>
                <p class="mt-1 text-sm text-slate-500">
                    @if ($tournament->days->count() > 1)
                        {{ $tournament->startsOn()?->format('d/m') }} al {{ $tournament->endsOn()?->format('d/m/Y') }} · {{ $tournament->days->count() }} días
                    @else
                        {{ $tournament->startsOn()?->translatedFormat('l d/m/Y') }}
                    @endif
                </p>
                <p class="text-xs text-slate-500">{{ $tournament->days->flatMap->activities->pluck('name')->unique()->implode(', ') }}</p>
                <div class="mt-4 grid grid-cols-3 gap-2 border-t border-slate-100 pt-3 text-center text-xs">
                    <div><p class="font-display text-lg font-bold text-slate-900">{{ $tournament->participants_count }}</p><p class="text-slate-500">anotados</p></div>
                    <div><p class="font-display text-lg font-bold tabular-nums text-emerald-700">{{ money($tournament->paid_total ?? 0) }}</p><p class="text-slate-500">cobrado</p></div>
                    <div><p @class(['font-display text-lg font-bold tabular-nums', 'text-red-600' => (float) $owed > 0, 'text-slate-400' => (float) $owed <= 0])>{{ money($owed) }}</p><p class="text-slate-500">adeudado</p></div>
                </div>
                <p class="mt-3 text-xs text-slate-500">{{ money($tournament->price) }} por participante · pagar hasta el {{ $tournament->payment_due_date->format('d/m/Y') }}</p>
            </a>
        @empty
            <div class="card md:col-span-2 xl:col-span-3"><x-empty-state icon="flag" title="No hay torneos" /></div>
        @endforelse
    </div>
</div>

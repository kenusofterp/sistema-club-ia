<div class="flex h-full flex-col"
     x-data="{
         timer: null, rotation: null,
         init() {
             this.timer = setInterval(() => { if (window.Echo?.connector?.pusher?.connection?.state !== 'connected') $wire.$refresh() }, 3000);
             @if ($rotating) this.rotation = setInterval(() => $wire.next(), {{ $rotate * 1000 }}); @endif
         },
         destroy() { clearInterval(this.timer); clearInterval(this.rotation) },
     }">
    @php($fmt = fn ($n) => number_format((float) $n, 3, ',', '.'))

    <header class="flex items-center justify-between gap-6 bg-brand-900 px-10 py-5">
        <div class="flex items-center gap-4">
            <x-logo light :with-name="false" size="size-14" />
            <div>
                <p class="text-xl font-medium text-white/70">{{ $competition->name }}</p>
                <p class="font-display text-4xl font-extrabold">{{ $current['label'] ?? 'Resultados' }}</p>
            </div>
        </div>
        <div class="text-right">
            <p class="rounded-2xl bg-accent-500 px-6 py-2 font-display text-4xl font-extrabold text-slate-900">{{ $panel['label'] ?? '' }}</p>
            @if ($rotating)
                <p class="mt-2 text-lg text-white/50 tabular-nums">{{ $slideNumber }} / {{ $slideCount }}</p>
            @endif
        </div>
    </header>

    <main class="flex-1 overflow-hidden px-10 py-6">
        @if ($rows->isEmpty())
            <p class="mt-24 text-center font-display text-5xl font-bold text-white/40">Todavía no hay notas publicadas</p>
        @else
            <table class="w-full border-separate border-spacing-y-2 text-3xl">
                <thead>
                    <tr class="text-left text-xl font-semibold text-white/50 uppercase">
                        <th class="w-28 px-4 text-center">Puesto</th>
                        <th class="w-28 px-4 text-center">Dorsal</th>
                        <th class="px-4">Participante</th>
                        <th class="px-4">Club</th>
                        <th class="w-56 px-4 text-right">Puntaje</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php($podium = match ($row->rank) { 1 => 'bg-amber-400 text-slate-900', 2 => 'bg-slate-300 text-slate-900', 3 => 'bg-orange-400 text-slate-900', default => 'bg-white/10 text-white' })
                        <tr class="bg-white/5" wire:key="row-{{ $row->entry_id }}">
                            <td class="rounded-l-2xl px-4 py-3 text-center">
                                <span class="inline-grid size-16 place-items-center rounded-2xl font-display text-4xl font-black {{ $podium }}">{{ $row->rank }}</span>
                            </td>
                            <td class="px-4 py-3 text-center font-display font-bold text-white/70 tabular-nums">{{ $row->bib_number }}</td>
                            <td class="px-4 py-3 font-semibold">
                                {{ $row->first_name }} {{ $row->last_name }}
                                @if (! empty($row->incomplete))
                                    <span class="ml-2 rounded-lg bg-white/10 px-2 py-0.5 align-middle text-lg font-medium text-white/60">{{ $row->done }}/{{ $row->expected }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-white/70">{{ $row->institution }}</td>
                            <td class="rounded-r-2xl px-4 py-3 text-right font-display text-4xl font-extrabold tabular-nums">{{ $fmt($row->score) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </main>
</div>

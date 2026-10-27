<div class="flex h-full flex-col"
     x-data="{ timer: null, init() { this.timer = setInterval(() => { if (window.Echo?.connector?.pusher?.connection?.state !== 'connected') $wire.$refresh() }, 3000) }, destroy() { clearInterval(this.timer) } }"
     wire:poll.10s>
    @php($fmt = fn ($n) => $n === null ? '' : number_format((float) $n, 3, ',', '.'))

    <header class="flex items-center justify-between gap-6 bg-brand-900 px-10 py-5">
        <div class="flex items-center gap-4">
            <x-logo light :with-name="false" size="size-14" />
            <div>
                <p class="text-xl font-medium text-white/70">{{ $station->competition->name }}</p>
                <p class="font-display text-4xl font-extrabold">{{ $station->name }}</p>
            </div>
        </div>
        <p class="rounded-2xl bg-accent-500 px-6 py-2 font-display text-4xl font-extrabold text-slate-900">{{ $station->competitionExercise->name() }}</p>
    </header>

    <main class="flex flex-1 flex-col justify-center gap-10 px-10 py-8">
        @php($shown = $last ?? $current)
        @if (! $shown)
            <p class="text-center font-display text-6xl font-bold text-white/40">Esperando participantes…</p>
        @else
            @php($published = $shown->status === \App\Enums\EvaluationStatus::Published)
            <div class="flex items-center gap-10">
                <span class="grid size-48 shrink-0 place-items-center rounded-[2.5rem] bg-white font-display text-8xl font-black text-slate-900 tabular-nums">{{ $shown->entry->bib_number }}</span>
                <div class="min-w-0">
                    <p class="truncate font-display text-7xl leading-tight font-extrabold">{{ $shown->entry->competitor->fullName() }}</p>
                    <p class="mt-2 truncate text-4xl text-white/70">{{ $shown->entry->competitor->institution }}</p>
                    <p class="mt-1 text-3xl text-accent-400">{{ $shown->entry->categoryLabel() }}</p>
                </div>
            </div>

            <div class="grid grid-cols-4 gap-6">
                @foreach (\App\Livewire\Display\StationScreen::totals($shown) as $seat => $total)
                    <div class="rounded-3xl bg-white/5 p-6 text-center ring-1 ring-white/10">
                        <p class="text-2xl font-semibold text-white/60">Juez {{ $seat }}</p>
                        @if ($published)
                            <p class="mt-2 font-display text-6xl font-extrabold tabular-nums">{{ $fmt($total) }}</p>
                        @elseif ($total !== null)
                            <p class="mt-2 font-display text-6xl font-extrabold text-emerald-400">✓</p>
                        @else
                            <p class="mt-2 font-display text-6xl font-extrabold text-white/20">…</p>
                        @endif
                    </div>
                @endforeach
                <div @class(['rounded-3xl p-6 text-center', 'bg-accent-500 text-slate-900' => $published, 'bg-white/5 ring-1 ring-white/10' => ! $published])>
                    <p @class(['text-2xl font-semibold', 'text-slate-900/70' => $published, 'text-white/60' => ! $published])>Puntaje</p>
                    <p class="mt-2 font-display text-7xl font-black tabular-nums">{{ $published ? $fmt($shown->score) : '—' }}</p>
                </div>
            </div>
        @endif
    </main>

    @if ($next->isNotEmpty() || ($last && $current))
        <footer class="flex items-center gap-6 border-t border-white/10 px-10 py-5 text-2xl">
            <span class="font-semibold text-white/50 uppercase">Siguen</span>
            @if ($last && $current)
                <span class="rounded-xl bg-white/10 px-4 py-1 font-semibold">{{ $current->entry->bib_number }} · {{ $current->entry->competitor->fullName() }}</span>
            @endif
            @foreach ($next as $evaluation)
                <span class="text-white/70">{{ $evaluation->entry->bib_number }} · {{ $evaluation->entry->competitor->fullName() }}</span>
            @endforeach
        </footer>
    @endif
</div>

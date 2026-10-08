<a href="{{ route('site.activity', $activity) }}" class="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
    <div class="relative aspect-[4/3] overflow-hidden bg-brand-100">
        @if ($activity->imageUrl())
            <img src="{{ $activity->imageUrl() }}" alt="{{ $activity->name }}" loading="lazy" class="size-full object-cover transition duration-500 group-hover:scale-105">
        @else
            <div class="grid size-full place-items-center bg-gradient-to-br from-brand-500 to-brand-800 text-white/80">
                <x-icon name="trophy" class="size-14" />
            </div>
        @endif
        @if (($spots = $activity->availableSpots()) !== null)
            <span class="absolute top-3 right-3 rounded-full bg-white/95 px-2.5 py-1 text-xs font-semibold {{ $spots > 0 ? 'text-brand-700' : 'text-red-600' }}">
                {{ $spots > 0 ? $spots.' cupos' : 'Sin cupo' }}
            </span>
        @endif
    </div>
    <div class="flex flex-1 flex-col p-5">
        <h3 class="font-display text-lg font-semibold text-slate-900 group-hover:text-brand-700">{{ $activity->name }}</h3>
        @if ($activity->summary)
            <p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ $activity->summary }}</p>
        @endif
        @if ($activity->schedules->isNotEmpty())
            <p class="mt-3 flex items-center gap-1.5 text-xs text-slate-500">
                <x-icon name="clock" class="size-4" />
                {{ $activity->schedules->map(fn ($s) => mb_substr($s->dayName(), 0, 3))->unique()->implode(' · ') }}
            </p>
        @endif
        @if ((float) $activity->monthly_fee > 0)
            <p class="mt-auto pt-4 text-sm"><span class="font-display text-lg font-bold text-slate-900">{{ money($activity->monthly_fee) }}</span> <span class="text-slate-500">/ mes</span></p>
        @endif
    </div>
</a>

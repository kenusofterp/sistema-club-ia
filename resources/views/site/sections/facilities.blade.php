<section id="{{ $section->key }}" class="scroll-mt-20 py-20 sm:py-28 {{ $alt ? 'bg-slate-50' : 'bg-white' }}">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @include('site.partials.section-heading')
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($facilities as $facility)
                <div class="group relative isolate flex aspect-[4/3] items-end overflow-hidden rounded-2xl bg-brand-800 shadow-sm">
                    @if ($facility->imageUrl())
                        <img src="{{ $facility->imageUrl() }}" alt="{{ $facility->name }}" loading="lazy" class="absolute inset-0 -z-10 size-full object-cover transition duration-500 group-hover:scale-105">
                    @else
                        <div class="absolute inset-0 -z-10 bg-gradient-to-br from-brand-600 to-brand-900"></div>
                        <x-icon name="building" class="absolute top-6 right-6 -z-10 size-16 text-white/15" />
                    @endif
                    <div class="absolute inset-0 -z-10 bg-gradient-to-t from-slate-950/80 via-slate-950/20 to-transparent"></div>
                    <div class="p-6">
                        <h3 class="font-display text-xl font-semibold text-white">{{ $facility->name }}</h3>
                        @if ($facility->description)
                            <p class="mt-1 line-clamp-2 text-sm text-white/75">{{ $facility->description }}</p>
                        @endif
                        @if ($facility->is_bookable)
                            <span class="mt-3 inline-flex items-center gap-1 rounded-full bg-accent-500 px-2.5 py-0.5 text-xs font-semibold text-slate-900">Reservable online</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

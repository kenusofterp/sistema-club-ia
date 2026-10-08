<section id="{{ $section->key }}" class="relative isolate scroll-mt-20 overflow-hidden bg-brand-900 py-16 sm:py-20">
    @if ($section->imageUrl())
        <img src="{{ $section->imageUrl() }}" alt="" loading="lazy" class="absolute inset-0 -z-10 size-full object-cover opacity-20">
    @endif
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @if ($section->title)
            <h2 class="mb-10 text-center font-display text-2xl font-bold text-white sm:text-3xl">{{ $section->title }}</h2>
        @endif
        <dl class="grid grid-cols-2 gap-8 text-center lg:grid-cols-4">
            @foreach ($section->items ?? [] as $item)
                <div>
                    <dt class="order-2 mt-2 text-sm font-medium text-white/70">{{ $item['title'] ?? '' }}</dt>
                    <dd class="font-display text-4xl font-extrabold tracking-tight text-accent-400 sm:text-5xl">{{ $item['value'] ?? '' }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</section>

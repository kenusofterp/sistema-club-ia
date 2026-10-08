@if (setting('site.join_enabled', true))
<section id="{{ $section->key }}" class="scroll-mt-20 px-4 py-16 sm:px-6 lg:px-8">
    <div class="relative isolate mx-auto max-w-7xl overflow-hidden rounded-3xl bg-brand-700 px-6 py-16 shadow-xl sm:px-16 lg:flex lg:items-center lg:justify-between lg:gap-10">
        @if ($section->imageUrl())
            <img src="{{ $section->imageUrl() }}" alt="" loading="lazy" class="absolute inset-0 -z-10 size-full object-cover opacity-20">
        @endif
        <div class="absolute -top-24 -right-24 -z-10 size-80 rounded-full bg-accent-500/30 blur-3xl"></div>
        <div class="max-w-2xl">
            <h2 class="font-display text-3xl font-bold tracking-tight text-white sm:text-4xl">{{ $section->title }}</h2>
            @if ($section->subtitle)
                <p class="mt-4 text-lg text-white/80">{{ $section->subtitle }}</p>
            @endif
        </div>
        <div class="mt-8 flex shrink-0 gap-3 lg:mt-0">
            <a href="{{ route('site.join') }}" class="btn-accent px-6 py-3 text-base">{{ $section->content ?: 'Asociate' }} <x-icon name="arrow-right" class="size-5" /></a>
        </div>
    </div>
</section>
@endif

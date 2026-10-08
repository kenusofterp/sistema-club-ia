<section id="{{ $section->key }}" class="scroll-mt-20 py-20 sm:py-28 {{ $alt ? 'bg-slate-50' : 'bg-white' }}">
    <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:gap-16 lg:px-8">
        <div>
            @if ($section->subtitle)
                <p class="text-sm font-semibold tracking-wider text-brand-600 uppercase">{{ $section->subtitle }}</p>
            @endif
            <h2 class="mt-2 font-display text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">{{ $section->title }}</h2>
            <div class="mt-4 h-1 w-14 rounded-full bg-accent-500"></div>
            <div class="mt-6 space-y-4 text-lg leading-relaxed text-slate-600">
                @foreach (preg_split("/\n\s*\n/", (string) $section->content) as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </div>
            @if (setting('site.founded_year'))
                <div class="mt-8 inline-flex items-center gap-3 rounded-2xl bg-brand-50 px-5 py-3">
                    <x-icon name="trophy" class="size-6 text-brand-600" />
                    <span class="text-sm text-slate-700">Fundado en <strong class="font-display text-lg text-brand-700">{{ setting('site.founded_year') }}</strong></span>
                </div>
            @endif
        </div>
        <div class="relative">
            @if ($section->imageUrl())
                <img src="{{ $section->imageUrl() }}" alt="{{ $section->title }}" loading="lazy" class="aspect-[4/3] w-full rounded-3xl object-cover shadow-xl">
            @else
                <div class="grid aspect-[4/3] w-full place-items-center rounded-3xl bg-gradient-to-br from-brand-500 to-brand-800 shadow-xl">
                    <x-logo light :with-name="false" size="size-28" />
                </div>
            @endif
            <div class="absolute -bottom-6 -left-6 -z-10 size-40 rounded-3xl bg-accent-400/30"></div>
        </div>
    </div>
</section>

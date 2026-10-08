<x-layouts::site :transparent-header="true">
    {{-- HERO: diapositivas configurables desde Administración > Sitio web > Portada --}}
    @if ($slides->isNotEmpty())
        <section
            x-data="{
                current: 0,
                total: {{ $slides->count() }},
                timer: null,
                start() { if (this.total > 1) this.timer = setInterval(() => this.next(), 7000) },
                stop() { clearInterval(this.timer) },
                next() { this.current = (this.current + 1) % this.total },
                prev() { this.current = (this.current - 1 + this.total) % this.total },
                go(i) { this.current = i; this.stop(); this.start() },
            }"
            x-init="start()"
            class="relative isolate flex min-h-[640px] items-center overflow-hidden bg-brand-950 lg:min-h-[92vh]"
            aria-roledescription="carrusel"
        >
            @foreach ($slides as $i => $slide)
                <div
                    x-show="current === {{ $i }}"
                    x-transition:enter="transition ease-out duration-1000"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-700 absolute inset-0"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    @if ($i > 0) x-cloak @endif
                    class="absolute inset-0 -z-10"
                >
                    @if ($slide->imageUrl())
                        <img src="{{ $slide->imageUrl() }}" alt="" class="size-full object-cover" @if ($i === 0) fetchpriority="high" @else loading="lazy" @endif>
                    @else
                        <div class="size-full bg-gradient-to-br from-brand-700 via-brand-900 to-brand-950"></div>
                        <svg class="absolute inset-0 size-full opacity-[0.07]" aria-hidden="true">
                            <defs><pattern id="hero-grid-{{ $i }}" width="56" height="56" patternUnits="userSpaceOnUse"><path d="M56 0H0v56" fill="none" stroke="white" stroke-width="1"/></pattern></defs>
                            <rect width="100%" height="100%" fill="url(#hero-grid-{{ $i }})"/>
                        </svg>
                        <div class="absolute -right-40 -bottom-40 size-[36rem] rounded-full bg-accent-500/20 blur-3xl"></div>
                    @endif
                    <div class="absolute inset-0 bg-gradient-to-r from-slate-950 via-slate-950/60 to-transparent" style="opacity: {{ $slide->overlay_opacity / 100 }}"></div>
                </div>
            @endforeach

            <div class="mx-auto w-full max-w-7xl px-4 pt-28 pb-24 sm:px-6 lg:px-8">
                @foreach ($slides as $i => $slide)
                    <div x-show="current === {{ $i }}" @if ($i > 0) x-cloak @endif
                         x-transition:enter="transition ease-out duration-700 delay-200"
                         x-transition:enter-start="opacity-0 translate-y-6"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="max-w-3xl">
                        @if ($i === 0 && setting('site.tagline'))
                            <p class="mb-5 inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-sm font-medium text-white ring-1 ring-white/20 backdrop-blur">
                                <span class="size-2 rounded-full bg-accent-400"></span>{{ setting('site.tagline') }}
                            </p>
                        @endif
                        <h1 class="font-display text-5xl leading-[1.05] font-extrabold tracking-tight text-white sm:text-6xl lg:text-7xl">{{ $slide->title }}</h1>
                        @if ($slide->subtitle)
                            <p class="mt-6 max-w-2xl text-lg leading-relaxed text-white/80 sm:text-xl">{{ $slide->subtitle }}</p>
                        @endif
                        <div class="mt-10 flex flex-wrap gap-3">
                            @if ($slide->button_text && $slide->button_url)
                                <a href="{{ $slide->button_url }}" class="btn-accent px-6 py-3 text-base">{{ $slide->button_text }} <x-icon name="arrow-right" class="size-5" /></a>
                            @endif
                            @if ($slide->secondary_button_text && $slide->secondary_button_url)
                                <a href="{{ $slide->secondary_button_url }}" class="btn bg-white/10 px-6 py-3 text-base text-white ring-1 ring-white/30 backdrop-blur hover:bg-white/20">{{ $slide->secondary_button_text }}</a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($slides->count() > 1)
                <div class="absolute inset-x-0 bottom-8 mx-auto flex max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                    <div class="flex gap-2">
                        @foreach ($slides as $i => $slide)
                            <button type="button" x-on:click="go({{ $i }})" class="h-1.5 rounded-full transition-all duration-300"
                                    :class="current === {{ $i }} ? 'w-10 bg-accent-400' : 'w-5 bg-white/40 hover:bg-white/70'"
                                    aria-label="Ir a diapositiva {{ $i + 1 }}"></button>
                        @endforeach
                    </div>
                    <div class="flex gap-2">
                        <button type="button" x-on:click="prev(); stop(); start()" class="grid size-10 place-items-center rounded-full bg-white/10 text-white ring-1 ring-white/20 hover:bg-white/20" aria-label="Anterior"><x-icon name="chevron-left" class="size-5" /></button>
                        <button type="button" x-on:click="next(); stop(); start()" class="grid size-10 place-items-center rounded-full bg-white/10 text-white ring-1 ring-white/20 hover:bg-white/20" aria-label="Siguiente"><x-icon name="chevron-right" class="size-5" /></button>
                    </div>
                </div>
            @endif
        </section>
    @else
        <div class="h-20"></div>
    @endif

    {{-- Secciones configurables, en el orden definido en la administración --}}
    @foreach ($sections as $section)
        @includeIf('site.sections.'.$section->type, ['section' => $section, 'alt' => $loop->even])
    @endforeach
</x-layouts::site>

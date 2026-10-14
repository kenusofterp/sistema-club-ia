@php($groups = \App\Support\Manual::groups())

<div x-data="{
        q: '',
        active: 'primeros-pasos',
        showTop: false,
        matches(label) { return this.q.trim() === '' || label.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').includes(this.q.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim()); },
        init() {
            const io = new IntersectionObserver((entries) => {
                entries.forEach((e) => { if (e.isIntersecting) this.active = e.target.id; });
            }, { rootMargin: '-15% 0px -75% 0px' });
            this.$root.querySelectorAll('.manual section[id]').forEach((s) => io.observe(s));
            window.addEventListener('scroll', () => { this.showTop = window.scrollY > 600; }, { passive: true });
        },
     }">
    @include('help.styles')
    <style>
        .manual-nav a.is-active { background: color-mix(in srgb, var(--brand) 10%, #fff); color: var(--brand); font-weight: 600; }
        .manual-hero { background: radial-gradient(120% 160% at 100% 0%, color-mix(in srgb, var(--brand) 22%, #fff) 0%, #fff 60%); }
    </style>

    {{-- Encabezado --}}
    <div class="manual-hero card mb-6 overflow-hidden p-6 sm:p-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-2xl">
                <p class="text-xs font-semibold tracking-wider text-slate-500 uppercase">Manual de uso</p>
                <h1 class="mt-1 font-display text-3xl font-bold tracking-tight text-slate-900">¿Cómo te ayudamos?</h1>
                <p class="mt-2 text-slate-600">Todo el sistema paso a paso, con ejemplos y capturas. Buscá un tema o empezá por una guía de inicio.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.help.print') }}" target="_blank" class="btn-primary"><x-icon name="printer" class="size-4" /> Imprimir / PDF</a>
                <a href="{{ route('admin.dashboard') }}" wire:navigate class="btn-secondary"><x-icon name="arrow-left" class="size-4" /> Volver al tablero</a>
            </div>
        </div>
        <div class="relative mt-6 max-w-xl">
            <x-icon name="search" class="pointer-events-none absolute top-3 left-3 size-5 text-slate-400" />
            <input type="search" x-model="q" class="form-input py-2.5 pl-10" placeholder="Buscar un tema: cuotas, carnet, torneos, alquiler…" aria-label="Buscar en el manual">
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[17rem_1fr]">
        {{-- Índice --}}
        <nav class="manual-nav lg:sticky lg:top-20 lg:max-h-[calc(100vh-6rem)] lg:self-start lg:overflow-y-auto">
            <div class="card p-3">
                @foreach ($groups as $group)
                    <div x-show="{{ \Illuminate\Support\Js::from(array_values($group['items'])) }}.some((l) => matches(l))" class="mb-2 last:mb-0">
                        <p class="px-2 pt-1 pb-1 text-[11px] font-semibold tracking-wider text-slate-400 uppercase">{{ $group['title'] }}</p>
                        <ul class="space-y-0.5 text-sm">
                            @foreach ($group['items'] as $id => $label)
                                <li x-show="matches(@js($label))">
                                    <a href="#{{ $id }}" x-bind:class="active === @js($id) && 'is-active'" class="block rounded-md px-2 py-1.5 text-slate-600 hover:bg-slate-50 hover:text-slate-900">{{ $label }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
                <p x-show="! {{ \Illuminate\Support\Js::from(collect($groups)->flatMap(fn ($g) => array_values($g['items']))->values()) }}.some((l) => matches(l))" x-cloak class="px-2 py-3 text-sm text-slate-500">No hay temas con «<span x-text="q"></span>».</p>
            </div>
        </nav>

        <article class="manual card min-w-0 p-6 sm:p-10">
            @include('help.content')
        </article>
    </div>

    <button type="button" x-show="showTop" x-cloak x-transition.opacity x-on:click="window.scrollTo({ top: 0, behavior: 'smooth' })"
            class="btn-primary fixed right-5 bottom-5 z-30 rounded-full px-3 py-3 shadow-lg" aria-label="Volver arriba">
        <x-icon name="arrow-up" class="size-5" />
    </button>
</div>

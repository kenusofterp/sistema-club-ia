@props(['title' => null, 'description' => null, 'transparentHeader' => false])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    @include('partials.head', ['title' => $title ?? null, 'description' => $description ?? null])
</head>
<body class="bg-white font-sans text-slate-800 antialiased">
@php
    $navSections = \App\Models\SiteSection::active()->whereNotNull('menu_label')
        ->when(! uses_gym(), fn ($q) => $q->where('type', '!=', 'plans'))
        ->get(['key', 'menu_label']);
    $navPages = \App\Models\Page::published()->where('show_in_menu', true)->orderBy('menu_order')->get(['slug', 'title']);
    $transparent = $transparentHeader ?? false;
    $socials = collect(['facebook' => 'Facebook', 'instagram' => 'Instagram', 'x' => 'X', 'youtube' => 'YouTube', 'tiktok' => 'TikTok'])
        ->filter(fn ($label, $key) => setting("social.$key"));
@endphp

<header
    x-data="{ open: false, scrolled: false }"
    x-on:scroll.window="scrolled = window.scrollY > 40"
    x-init="scrolled = window.scrollY > 40"
    @if ($transparent)
        :class="scrolled || open ? 'bg-white/95 shadow-sm backdrop-blur' : 'bg-transparent'"
    @endif
    class="fixed inset-x-0 top-0 z-40 transition-colors duration-300 {{ $transparent ? '' : 'bg-white/95 shadow-sm backdrop-blur' }}"
>
    <nav class="mx-auto flex h-18 max-w-7xl items-center justify-between gap-6 px-4 py-3 sm:px-6 lg:px-8">
        <a href="{{ route('home') }}" class="shrink-0">
            @if ($transparent)
                <span x-show="!scrolled && !open"><x-logo light /></span>
                <span x-show="scrolled || open" x-cloak><x-logo /></span>
            @else
                <x-logo />
            @endif
        </a>

        <div class="hidden items-center gap-1 lg:flex">
            @foreach ($navSections as $section)
                <a href="{{ route('home') }}#{{ $section->key }}"
                   @if ($transparent) :class="scrolled ? 'text-slate-700 hover:text-brand-700' : 'text-white/90 hover:text-white'" @endif
                   class="rounded-lg px-3 py-2 text-sm font-medium {{ $transparent ? '' : 'text-slate-700 hover:text-brand-700' }}">{{ $section->menu_label }}</a>
            @endforeach
            @foreach ($navPages as $navPage)
                <a href="{{ route('site.page', $navPage) }}"
                   @if ($transparent) :class="scrolled ? 'text-slate-700 hover:text-brand-700' : 'text-white/90 hover:text-white'" @endif
                   class="rounded-lg px-3 py-2 text-sm font-medium {{ $transparent ? '' : 'text-slate-700 hover:text-brand-700' }}">{{ $navPage->title }}</a>
            @endforeach
        </div>

        <div class="hidden items-center gap-2 lg:flex">
            @if (setting('site.join_enabled', true))
                <a href="{{ route('site.join') }}" class="btn-accent">Asociate</a>
            @endif
            @if (setting('site.show_login_button', true))
                @auth
                    <a href="{{ auth()->user()->homeRoute() }}" class="btn-primary"><x-icon name="user" class="size-4" /> Mi cuenta</a>
                @else
                    <a href="{{ route('login') }}" class="btn-primary"><x-icon name="user" class="size-4" /> Ingresar</a>
                @endauth
            @endif
        </div>

        <button type="button" class="rounded-lg p-2 lg:hidden"
                @if ($transparent) :class="scrolled || open ? 'text-slate-700' : 'text-white'" @else class="text-slate-700" @endif
                x-on:click="open = !open">
            <x-icon name="menu" class="size-7" x-show="!open" />
            <x-icon name="x" class="size-7" x-show="open" x-cloak />
            <span class="sr-only">Menú</span>
        </button>
    </nav>

    <div x-show="open" x-cloak x-transition class="border-t border-slate-100 bg-white px-4 pb-6 lg:hidden">
        <div class="flex flex-col py-2">
            @foreach ($navSections as $section)
                <a href="{{ route('home') }}#{{ $section->key }}" x-on:click="open = false" class="rounded-lg px-3 py-3 text-base font-medium text-slate-700 hover:bg-slate-50">{{ $section->menu_label }}</a>
            @endforeach
            @foreach ($navPages as $navPage)
                <a href="{{ route('site.page', $navPage) }}" class="rounded-lg px-3 py-3 text-base font-medium text-slate-700 hover:bg-slate-50">{{ $navPage->title }}</a>
            @endforeach
        </div>
        <div class="grid grid-cols-2 gap-2">
            @if (setting('site.join_enabled', true))
                <a href="{{ route('site.join') }}" class="btn-accent">Asociate</a>
            @endif
            @auth
                <a href="{{ auth()->user()->homeRoute() }}" class="btn-primary">Mi cuenta</a>
            @else
                <a href="{{ route('login') }}" class="btn-primary">Ingresar</a>
            @endauth
        </div>
    </div>
</header>

<main>
    {{ $slot }}
</main>

<footer class="bg-brand-950 text-white/70">
    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-2 lg:grid-cols-4 lg:px-8">
        <div class="lg:col-span-2">
            <x-logo light />
            <p class="mt-4 max-w-md text-sm leading-relaxed">{{ setting('site.footer_text') }}</p>
            @if ($socials->isNotEmpty())
                <div class="mt-5 flex flex-wrap gap-2">
                    @foreach ($socials as $key => $label)
                        <a href="{{ setting("social.$key") }}" target="_blank" rel="noopener" class="rounded-full bg-white/10 px-3 py-1.5 text-xs font-medium text-white hover:bg-white/20">{{ $label }}</a>
                    @endforeach
                </div>
            @endif
        </div>
        <div>
            <h3 class="font-display text-sm font-semibold tracking-wide text-white uppercase">Contacto</h3>
            <ul class="mt-4 space-y-3 text-sm">
                @if (setting('contact.address'))
                    <li class="flex gap-2"><x-icon name="map-pin" class="size-5 shrink-0 text-accent-400" /> {{ setting('contact.address') }}</li>
                @endif
                @if (setting('contact.phone'))
                    <li class="flex gap-2"><x-icon name="phone" class="size-5 shrink-0 text-accent-400" /> {{ setting('contact.phone') }}</li>
                @endif
                @if (setting('contact.email'))
                    <li class="flex gap-2"><x-icon name="mail" class="size-5 shrink-0 text-accent-400" /> <a href="mailto:{{ setting('contact.email') }}" class="hover:text-white">{{ setting('contact.email') }}</a></li>
                @endif
                @if (setting('contact.hours'))
                    <li class="flex gap-2"><x-icon name="clock" class="size-5 shrink-0 text-accent-400" /> <span class="whitespace-pre-line">{{ setting('contact.hours') }}</span></li>
                @endif
            </ul>
        </div>
        <div>
            <h3 class="font-display text-sm font-semibold tracking-wide text-white uppercase">El club</h3>
            <ul class="mt-4 space-y-2 text-sm">
                <li><a href="{{ route('site.activities') }}" class="hover:text-white">Actividades</a></li>
                <li><a href="{{ route('site.news') }}" class="hover:text-white">Noticias</a></li>
                @foreach (\App\Models\Page::published()->orderBy('title')->get(['slug', 'title']) as $footerPage)
                    <li><a href="{{ route('site.page', $footerPage) }}" class="hover:text-white">{{ $footerPage->title }}</a></li>
                @endforeach
                <li><a href="{{ route('login') }}" class="hover:text-white">Portal del socio</a></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-white/10">
        <p class="mx-auto max-w-7xl px-4 py-5 text-xs text-white/40 sm:px-6 lg:px-8">© {{ date('Y') }} {{ setting('site.name') }}. Todos los derechos reservados.</p>
    </div>
</footer>

@if (setting('contact.whatsapp'))
    <a href="https://wa.me/{{ preg_replace('/\D/', '', setting('contact.whatsapp')) }}" target="_blank" rel="noopener"
       class="fixed right-5 bottom-5 z-30 grid size-14 place-items-center rounded-full bg-emerald-500 text-white shadow-lg transition hover:scale-105" aria-label="WhatsApp">
        <x-icon name="phone" class="size-7" />
    </a>
@endif

<x-toasts />
</body>
</html>

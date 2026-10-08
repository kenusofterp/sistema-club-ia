@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('partials.head', ['title' => $title ?? null])
</head>
<body class="h-full bg-slate-50 font-sans antialiased text-slate-800">
<div class="grid min-h-full lg:grid-cols-2">
    <div class="relative hidden overflow-hidden bg-brand-900 lg:block">
        @if ($img = \App\Models\HeroSlide::active()->whereNotNull('image_path')->first()?->imageUrl())
            <img src="{{ $img }}" alt="" class="absolute inset-0 size-full object-cover opacity-40">
        @endif
        <div class="absolute inset-0 bg-gradient-to-br from-brand-800/80 via-brand-900/70 to-brand-950"></div>
        <div class="relative flex h-full flex-col justify-between p-12">
            <a href="{{ route('home') }}"><x-logo light /></a>
            <div>
                <h2 class="font-display text-4xl font-bold text-white">{{ setting('site.tagline') }}</h2>
                <p class="mt-4 max-w-md text-white/70">Gestión del club y portal de socios: cuotas, actividades, reservas y carnet digital en un solo lugar.</p>
            </div>
            <p class="text-sm text-white/40">© {{ date('Y') }} {{ setting('site.name') }}</p>
        </div>
    </div>
    <div class="flex flex-col justify-center px-6 py-12 sm:px-12">
        <div class="mx-auto w-full max-w-sm">
            <a href="{{ route('home') }}" class="mb-10 inline-flex lg:hidden"><x-logo /></a>
            {{ $slot }}
        </div>
    </div>
</div>
<x-toasts />
</body>
</html>

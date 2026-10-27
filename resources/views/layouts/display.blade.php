@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-950">
<head>
    @include('partials.head', ['title' => $title ?? null, 'realtime' => true])
    <meta name="robots" content="noindex">
</head>
{{-- Pantallas públicas de competencia (TV / proyector): fondo oscuro, letras grandes, sin login. --}}
<body class="h-full overflow-hidden bg-slate-950 font-sans text-white antialiased"
      x-data="{ full: false }" x-on:dblclick="document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen?.()">
    {{ $slot }}
</body>
</html>

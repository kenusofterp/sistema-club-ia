@php
    $siteName = setting('site.name', config('app.name'));
    $pageTitle = isset($title) && $title ? $title.' · '.$siteName : $siteName;
    $favicon = \App\Models\Setting::imageUrl('site.favicon') ?? \App\Models\Setting::imageUrl('pwa.icon');
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $pageTitle }}</title>
<meta name="description" content="{{ $description ?? setting('seo.meta_description') }}">
<meta property="og:title" content="{{ $pageTitle }}">
<meta property="og:description" content="{{ $description ?? setting('seo.meta_description') }}">
@if ($og = \App\Models\Setting::imageUrl('seo.og_image'))
    <meta property="og:image" content="{{ $og }}">
@endif

{{-- PWA --}}
<link rel="manifest" href="{{ route('pwa.manifest') }}">
<meta name="theme-color" content="{{ setting('pwa.theme_color', setting('site.primary_color', '#0f5132')) }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="{{ setting('site.short_name', $siteName) }}">
<link rel="icon" href="{{ $favicon ?? route('pwa.icon', 192) }}">
<link rel="apple-touch-icon" href="{{ route('pwa.icon', 192) }}">

@fonts
@vite(['resources/css/app.css', 'resources/js/app.js'])

<style>
    :root {
        --brand: {{ setting('site.primary_color', '#0f5132') }};
        --accent: {{ setting('site.accent_color', '#f59e0b') }};
    }
</style>
